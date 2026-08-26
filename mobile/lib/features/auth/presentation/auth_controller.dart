import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/providers.dart';
import '../data/auth_repository.dart';
import '../models/user.dart';

enum AuthStatus { unknown, unauthenticated, authenticating, authenticated }

/// State autentikasi global yang mengendalikan routing (auth gate).
class AuthState {
  const AuthState({
    this.status = AuthStatus.unknown,
    this.user,
    this.error,
    this.isSessionVerified = true,
  });

  final AuthStatus status;
  final User? user;
  final String? error;

  /// False = `authenticated` ini dipulihkan dari cache lokal saat backend
  /// tak terjangkau (bukan dari `/auth/me` yang baru saja sukses). UI boleh
  /// pakai ini untuk menandai sesi "belum terverifikasi ulang".
  final bool isSessionVerified;

  bool get isAuthenticated => status == AuthStatus.authenticated;
  bool get isBusy => status == AuthStatus.authenticating;

  AuthState copyWith({
    AuthStatus? status,
    User? user,
    String? error,
    bool? isSessionVerified,
  }) {
    return AuthState(
      status: status ?? this.status,
      user: user ?? this.user,
      error: error,
      isSessionVerified: isSessionVerified ?? this.isSessionVerified,
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repo) : super(const AuthState()) {
    _bootstrap();
  }

  final AuthRepository _repo;

  /// Dipanggil sekali saat start: tentukan sesi dari token tersimpan.
  ///
  /// Token ada tapi backend tak terjangkau (`isNetwork`) → **tidak** logout
  /// paksa. Pulihkan dari cache lokal ([UserCacheStore] via repo) dan tandai
  /// `isSessionVerified: false`, supaya user tetap bisa pakai app (mis. di
  /// gerbang sekolah tanpa sinyal) — hanya `401` eksplisit yang menghapus sesi.
  Future<void> _bootstrap() async {
    final token = await _repo.currentToken();
    if (token == null || token.isEmpty) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }
    try {
      final user = await _repo.me();
      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await _repo.clearToken();
        state = const AuthState(status: AuthStatus.unauthenticated);
        return;
      }
      if (e.isNetwork) {
        final cached = await _repo.cachedUser();
        if (cached != null) {
          state = AuthState(
            status: AuthStatus.authenticated,
            user: cached,
            isSessionVerified: false,
            error: e.message,
          );
          return;
        }
      }
      state = AuthState(status: AuthStatus.unauthenticated, error: e.message);
    }
  }

  /// Coba verifikasi ulang sesi ke server (dipanggil saat koneksi pulih).
  /// Tak melakukan apa-apa bila bukan sesi "belum terverifikasi".
  Future<void> revalidateSession() async {
    if (state.status != AuthStatus.authenticated || state.isSessionVerified) {
      return;
    }

    // Sesi hasil login offline belum punya token sama sekali. Tukar sekarang
    // dengan login sungguhan memakai password yang masih dipegang di memori —
    // tanpa token, antrean absensi tak akan pernah bisa terkirim.
    final email = _pendingEmail;
    final password = _pendingPassword;
    if (password != null && email != null) {
      try {
        await _repo.login(email: email, password: password);
        final user = await _repo.me();
        await _repo.rememberForOffline(
            email: email, password: password, user: user);
        _pendingEmail = null;
        _pendingPassword = null;
        state = AuthState(status: AuthStatus.authenticated, user: user);
      } on ApiException {
        // Masih gagal (jaringan belum stabil / password sudah diubah di server)
        // — biarkan sesi offline berjalan, dicoba lagi saat status online
        // berikutnya.
      }
      return;
    }

    try {
      final user = await _repo.me();
      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await _repo.clearToken();
        state = const AuthState(status: AuthStatus.unauthenticated);
      }
      // Masih network error → biarkan unverified, akan dicoba lagi nanti.
    }
  }

  /// Berapa lama menunggu server saat login sebelum mencoba jalur offline.
  static const _onlineLoginTimeout = Duration(seconds: 6);

  static Never _timedOut() => throw ApiException(
        message: 'Server tidak menjawab.',
        isNetwork: true,
      );

  /// Password yang baru saja dipakai untuk masuk offline.
  ///
  /// Hanya di memori, tidak pernah ditulis ke disk: dipakai sekali untuk
  /// menukar sesi offline menjadi token asli begitu server terjangkau lagi.
  /// Hilang bila aplikasi ditutup — antrean tetap aman, hanya perlu login
  /// online sekali lagi untuk mengirimkannya.
  String? _pendingPassword;
  String? _pendingEmail;

  /// Login dengan email + password, lalu muat profil lengkap (permissions).
  ///
  /// Bila server tak terjangkau, kredensial diverifikasi terhadap catatan lokal
  /// dari login online sebelumnya. Aplikasi ini dipakai di gerbang sekolah yang
  /// sinyalnya putus-putus; terkunci di layar login justru saat server mati
  /// membuat seluruh premis offline-first tak ada artinya.
  Future<void> login({
    required String email,
    required String password,
    bool remember = true,
  }) async {
    state = const AuthState(status: AuthStatus.authenticating);
    try {
      // Batas tunggu sendiri, jauh lebih pendek dari `connectTimeout` global
      // 15 detik. Kalau server memang mati, menahan petugas menatap tombol
      // selama itu tak ada gunanya — lebih cepat jatuh ke verifikasi lokal.
      await _repo
          .login(email: email, password: password, remember: remember)
          .timeout(_onlineLoginTimeout, onTimeout: _timedOut);
      final user = await _repo.me().timeout(
            _onlineLoginTimeout,
            onTimeout: _timedOut,
          );
      if (await _rejectIfNotStaff(user)) return;

      await _repo.rememberForOffline(
          email: email, password: password, user: user);
      _pendingPassword = null;
      _pendingEmail = null;
      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      if (e.isNetwork) {
        await _loginOffline(email: email, password: password);
        return;
      }
      state = AuthState(status: AuthStatus.unauthenticated, error: e.message);
    }
  }

  /// Masuk memakai catatan kredensial lokal.
  Future<void> _loginOffline({
    required String email,
    required String password,
  }) async {
    final user = await _repo.verifyOffline(email: email, password: password);

    if (user == null) {
      final known = await _repo.offlineEmails();
      final isKnown = known.contains(email.trim().toLowerCase());

      state = AuthState(
        status: AuthStatus.unauthenticated,
        // Tiga keadaan yang berbeda, dan membedakannya menghemat waktu orang:
        // password salah, akun ini belum pernah masuk di sini, atau perangkat
        // ini memang belum pernah dipakai sama sekali.
        error: isKnown
            ? 'Tidak terhubung ke server, dan password tidak cocok dengan '
                'yang tersimpan untuk akun ini.'
            : known.isEmpty
                ? 'Tidak terhubung ke server. Perangkat ini belum pernah '
                    'dipakai login, jadi belum ada yang bisa diverifikasi '
                    'secara offline. Satu kali login online dibutuhkan lebih '
                    'dulu.'
                : 'Tidak terhubung ke server. Akun ini belum pernah masuk di '
                    'perangkat ini. Yang bisa masuk offline: '
                    '${known.join(', ')}.',
      );
      return;
    }

    if (await _rejectIfNotStaff(user)) return;

    // Password ditahan di memori supaya sesi ini bisa ditukar dengan token asli
    // begitu server hidup (lihat revalidateSession).
    _pendingEmail = email;
    _pendingPassword = password;

    state = AuthState(
      status: AuthStatus.authenticated,
      user: user,
      isSessionVerified: false,
      error: 'Masuk tanpa koneksi — memakai kredensial tersimpan. Absensi '
          'akan disinkronkan begitu server terjangkau.',
    );
  }

  /// True bila persona ini ditolak (dan state sudah diisi pesan penolakan).
  Future<bool> _rejectIfNotStaff(User user) async {
    if (user.canUseAttendanceApp) return false;
    await _repo.logout();
    state = const AuthState(
      status: AuthStatus.unauthenticated,
      error: 'Akun ini tidak memiliki akses ke aplikasi absensi petugas.',
    );
    return true;
  }

  /// Login dengan kode akses sekali-pakai dari administrator (lupa password /
  /// perangkat baru). Tetap butuh koneksi — hanya sesi berikutnya yang offline.
  Future<void> loginWithOtp({
    required String email,
    required String code,
  }) {
    return _signIn(() => _repo.loginWithOtp(email: email, code: code));
  }

  /// Login dengan token provisioning dari QR code.
  ///
  /// Admin men-generate token provisioning untuk user tertentu (berlaku 15
  /// menit), lalu user scan QR di perangkat baru untuk langsung login tanpa
  /// ketik password. Butuh koneksi untuk redeem token.
  Future<void> loginWithProvision({required String provisionToken}) {
    return _signIn(() => _repo.redeemProvision(provisionToken: provisionToken));
  }

  /// Alur bersama kedua cara masuk: terbitkan sesi, muat profil lengkap
  /// (permissions), lalu tegakkan guard persona.
  Future<void> _signIn(Future<User> Function() authenticate) async {
    state = const AuthState(status: AuthStatus.authenticating);
    try {
      await authenticate();
      final user = await _repo.me();

      if (await _rejectIfNotStaff(user)) return;

      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      state = AuthState(status: AuthStatus.unauthenticated, error: e.message);
    }
  }

  /// Keluar dari sesi.
  ///
  /// Catatan kredensial offline **sengaja tidak dihapus** — kalau dihapus,
  /// keluar lalu masuk lagi saat server mati menjadi mustahil, yaitu persis
  /// keadaan yang harus ditangani aplikasi ini.
  Future<void> logout() async {
    _pendingEmail = null;
    _pendingPassword = null;
    await _repo.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  /// Dibersihkan saat mendeteksi 401 di tempat lain.
  Future<void> forceLogout() async {
    await _repo.clearToken();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  final controller = AuthController(ref.watch(authRepositoryProvider));

  // Backend baru terjangkau lagi → coba sinkronkan ulang sesi yang tadinya
  // dipulihkan dari cache (lihat AuthController._bootstrap/revalidateSession).
  ref.listen<BackendStatus>(backendStatusProvider, (previous, next) {
    if (next == BackendStatus.online && previous != BackendStatus.online) {
      controller.revalidateSession();
    }
  });

  // Server menolak token (401 di endpoint terproteksi, mis. sesi dicabut admin)
  // → bersihkan sesi sekali di sini, apa pun layar yang sedang terbuka.
  ref.listen<int>(unauthorizedSignalProvider, (_, __) {
    controller.forceLogout();
  });

  return controller;
});
