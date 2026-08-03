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

  /// Login dengan email + password, lalu muat profil lengkap (permissions).
  Future<void> login({
    required String email,
    required String password,
    bool remember = true,
  }) {
    return _signIn(
      () => _repo.login(email: email, password: password, remember: remember),
    );
  }

  /// Login dengan kode akses sekali-pakai dari administrator (lupa password /
  /// perangkat baru). Tetap butuh koneksi — hanya sesi berikutnya yang offline.
  Future<void> loginWithOtp({
    required String email,
    required String code,
  }) {
    return _signIn(() => _repo.loginWithOtp(email: email, code: code));
  }

  /// Alur bersama kedua cara masuk: terbitkan sesi, muat profil lengkap
  /// (permissions), lalu tegakkan guard persona.
  Future<void> _signIn(Future<User> Function() authenticate) async {
    state = const AuthState(status: AuthStatus.authenticating);
    try {
      await authenticate();
      final user = await _repo.me();

      if (!user.canUseAttendanceApp) {
        // Persona tak berhak (mis. siswa/orang tua) — tolak di klien.
        await _repo.logout();
        state = const AuthState(
          status: AuthStatus.unauthenticated,
          error:
              'Akun ini tidak memiliki akses ke aplikasi absensi petugas.',
        );
        return;
      }

      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      state = AuthState(status: AuthStatus.unauthenticated, error: e.message);
    }
  }

  Future<void> logout() async {
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
