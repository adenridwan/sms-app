import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/data/auth_repository.dart';
import '../../features/auth/models/user.dart';
import '../config/app_config.dart';
import '../database/database.dart';
import '../database/database_providers.dart';
import '../network/api_exception.dart';
import '../providers.dart';
import 'local_auth_service.dart';

/// Catatan sekolah yang terhubung di perangkat ini, **plus** sesi akun lokal.
///
/// Akun lokal adalah akun yang hanya ada di perangkat: dipakai untuk menyiapkan
/// dan menguji perangkat sebelum ada sekolah yang dituju — mengatur alamat API,
/// mencoba tampilan, memastikan aplikasi jalan. Passwordnya tidak pernah bisa
/// diverifikasi server.
///
/// Karena itu ia **dibatasi**: [canUseLocalAccount] hanya benar selama
/// perangkat belum tersambung ke sekolah mana pun. Tanpa batas itu, dua sistem
/// autentikasi hidup berdampingan dan gerbang aplikasi bisa terbuka untuk akun
/// yang ditolak server di setiap panggilan API.
class LocalSession {
  const LocalSession({
    this.status = LocalSessionStatus.unknown,
    this.account,
    this.connection,
    this.error,
  });

  final LocalSessionStatus status;

  /// Akun lokal yang sedang masuk, bila ada.
  final LocalAccount? account;

  final BackendConnectionData? connection;
  final String? error;

  bool get isConnectedToBackend => connection != null;

  /// Sedang masuk memakai akun lokal.
  bool get isLocalLoggedIn => account != null;

  /// Akun lokal masih boleh dipakai di perangkat ini.
  ///
  /// `false` selama perangkat tersambung ke sekolah. Kalau koneksinya diputus,
  /// jawabannya kembali `true` — tapi akun-akun **lama sudah dihapus** saat
  /// menyambung, jadi yang terbuka adalah kesempatan membuat akun **baru**,
  /// bukan menghidupkan yang lama.
  bool get canUseLocalAccount => connection == null;

  String? get schoolName => connection?.schoolName;
  String? get backendUserName => connection?.backendUserName;

  LocalSession copyWith({
    LocalSessionStatus? status,
    LocalAccount? account,
    BackendConnectionData? connection,
    String? error,
    bool clearError = false,
    bool clearConnection = false,
    bool clearAccount = false,
  }) {
    return LocalSession(
      status: status ?? this.status,
      account: clearAccount ? null : (account ?? this.account),
      connection: clearConnection ? null : (connection ?? this.connection),
      error: clearError ? null : (error ?? this.error),
    );
  }
}

/// Sekadar penanda bahwa catatan koneksi sudah selesai dibaca dari disk.
enum LocalSessionStatus { unknown, ready }

/// Controller for local session management.
class LocalSessionController extends StateNotifier<LocalSession> {
  LocalSessionController(this._authService, this._authRepository)
      : super(const LocalSession()) {
    _init();
  }

  final LocalAuthService _authService;

  /// Dipakai menebus token provisioning saat menghubungkan ke sekolah.
  final AuthRepository _authRepository;

  Future<void> _init() async {
    // Check for existing connection
    final connection = await _authService.getConnection();
    if (connection != null) {
      state = state.copyWith(connection: connection);
    }
    state = state.copyWith(status: LocalSessionStatus.ready);
  }

  /// Buat akun lokal baru.
  ///
  /// Ditolak bila perangkat sudah tersambung ke sekolah: di situ akun yang
  /// berlaku adalah akun sekolah, dan membuat akun perangkat hanya akan
  /// menghasilkan kredensial yang ditolak server di setiap panggilan API.
  Future<bool> signUp({
    required String email,
    required String fullName,
    required String password,
  }) async {
    if (!state.canUseLocalAccount) {
      state = state.copyWith(error: _connectedMessage);
      return false;
    }

    state = state.copyWith(clearError: true);

    try {
      final account = await _authService.createAccount(
        email: email,
        fullName: fullName,
        password: password,
      );

      state = state.copyWith(account: account);
      return true;
    } on LocalAuthException catch (e) {
      state = state.copyWith(error: e.message);
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Gagal membuat akun: $e');
      return false;
    }
  }

  /// Masuk dengan akun lokal.
  Future<bool> localLogin({
    required String email,
    required String password,
  }) async {
    if (!state.canUseLocalAccount) {
      state = state.copyWith(error: _connectedMessage);
      return false;
    }

    state = state.copyWith(clearError: true);

    try {
      final account = await _authService.login(email, password);
      if (account == null) {
        state = state.copyWith(error: 'Email atau password salah.');
        return false;
      }

      state = state.copyWith(account: account);
      return true;
    } catch (e) {
      state = state.copyWith(error: 'Gagal masuk: $e');
      return false;
    }
  }

  /// Keluar dari akun lokal. Catatan koneksi tidak disentuh.
  void localLogout() {
    state = state.copyWith(clearAccount: true, clearError: true);
  }

  /// Ganti password akun lokal.
  Future<bool> changeLocalPassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    final account = state.account;
    if (account == null) return false;

    try {
      await _authService.changePassword(
        id: account.id,
        currentPassword: currentPassword,
        newPassword: newPassword,
      );
      return true;
    } on LocalAuthException catch (e) {
      state = state.copyWith(error: e.message);
      return false;
    }
  }

  static const _connectedMessage =
      'Perangkat ini sudah terhubung ke sekolah. Gunakan user dan password '
      'akun sekolah Anda — akun lokal tidak berlaku lagi.';

  /// Connect to backend via QR data.
  ///
  /// Mengembalikan user hasil penebusan token bila berhasil, `null` bila
  /// gagal. Usernya dikembalikan — bukan sekadar `true` — karena penebusan di
  /// sini sudah menerbitkan token sesi yang sah, dan token provisioning cuma
  /// sekali pakai: pemanggil harus bisa menyerahkan sesi itu ke
  /// `AuthController.adoptSession` tanpa menebus ulang.
  Future<User?> connectToBackend({
    required String apiUrl,
    required String syncToken,
    String? schoolName,
    String? backendUserId,
    String? backendUserName,
    String? backendUserEmail,
    List<String> permissions = const [],
    List<String> roles = const [],
  }) async {
    // Alamat server harus dipasang LEBIH DULU: Dio membaca AppConfig.baseUrl,
    // dan token hanya bisa ditebus ke server yang menerbitkannya.
    final previousUrl = AppConfig.baseUrl;
    final wasProvisioned = AppConfig.isProvisioned;
    await AppConfig.setServerUrl(apiUrl);

    try {
      // Tebus token provisioning jadi token sesi sungguhan. Tanpa langkah ini
      // koneksi cuma tercatat di perangkat: aplikasi mengaku "terhubung"
      // padahal tidak punya akses apa pun, dan tombol Sinkron akan gagal 401.
      final user = await _authRepository.redeemProvision(
        provisionToken: syncToken,
      );

      await _authService.saveConnection(
        apiUrl: apiUrl,
        syncToken: syncToken,
        schoolName: schoolName,
        // Identitas dari server lebih tepercaya daripada yang tertulis di QR.
        backendUserId: user.id.isNotEmpty ? user.id : backendUserId,
        backendUserName: user.fullName.isNotEmpty ? user.fullName : backendUserName,
        backendUserEmail: user.email.isNotEmpty ? user.email : backendUserEmail,
        permissions: user.permissions.isNotEmpty ? user.permissions : permissions,
        // Peran dipakai Beranda untuk memilih set Menu Cepat tanpa harus
        // memanggil server lagi.
        roles: user.roles.isNotEmpty ? user.roles : roles,
      );

      // Akun lokal dihapus, bukan sekadar dikunci. Mengunci saja menyisakan
      // barisnya berikut hash password, sehingga memutus koneksi nanti akan
      // menghidupkannya kembali — dan catatan absensi berikutnya bisa
      // terkirim atas nama siapa pun yang kebetulan menyambungkan perangkat.
      //
      // Antrean absensi ada di tabel lain dan tidak ikut terhapus.
      await _authService.deleteAllAccounts();

      final connection = await _authService.getConnection();
      state = state.copyWith(
        connection: connection,
        clearAccount: true,
        clearError: true,
      );
      return user;
    } on ApiException catch (e) {
      // Gagal menebus = JANGAN ditandai terhubung, dan kembalikan alamat lama
      // supaya request berikutnya tidak tertuju ke server yang salah.
      await _restoreServerUrl(previousUrl, wasProvisioned);
      state = state.copyWith(
        error: e.isNetwork
            ? 'Server tidak terjangkau di $apiUrl. Pastikan perangkat satu '
                'jaringan dengan server sekolah.'
            : 'QR ditolak server: ${e.message} Token berlaku 15 menit dan '
                'sekali pakai — minta QR baru ke admin.',
      );
      return null;
    } catch (e) {
      await _restoreServerUrl(previousUrl, wasProvisioned);
      state = state.copyWith(error: 'Gagal terhubung: $e');
      return null;
    }
  }

  /// Kembalikan alamat server ke nilai sebelumnya setelah percobaan gagal.
  ///
  /// Kalau sebelumnya belum pernah di-provisioning, alamatnya dikosongkan agar
  /// kembali ke bawaan build (`--dart-define`) — bukan disetel ke nilai bawaan
  /// itu secara eksplisit, supaya build berikutnya tetap bisa menggantinya.
  Future<void> _restoreServerUrl(String previous, bool wasProvisioned) async {
    if (wasProvisioned) {
      await AppConfig.setServerUrl(previous);
    } else {
      await AppConfig.resetServerUrl();
    }
  }

  /// Disconnect from backend.
  Future<void> disconnectFromBackend() async {
    await _authService.disconnect();
    state = state.copyWith(clearConnection: true);
  }

  /// Update connection after sync.
  Future<void> markSynced() async {
    await _authService.updateLastSyncTime();
    final connection = await _authService.getConnection();
    state = state.copyWith(connection: connection);
  }

  /// Clear error.
  void clearError() {
    state = state.copyWith(clearError: true);
  }
}

/// Provider for local session.
final localSessionProvider =
    StateNotifierProvider<LocalSessionController, LocalSession>((ref) {
  return LocalSessionController(
    ref.watch(localAuthServiceProvider),
    ref.watch(authRepositoryProvider),
  );
});

/// Convenience providers.
final isLocalLoggedInProvider = Provider<bool>((ref) {
  return ref.watch(localSessionProvider).isLocalLoggedIn;
});

final canUseLocalAccountProvider = Provider<bool>((ref) {
  return ref.watch(localSessionProvider).canUseLocalAccount;
});

final currentLocalAccountProvider = Provider<LocalAccount?>((ref) {
  return ref.watch(localSessionProvider).account;
});

final isConnectedToBackendProvider = Provider<bool>((ref) {
  return ref.watch(localSessionProvider).isConnectedToBackend;
});

final backendConnectionProvider = Provider<BackendConnectionData?>((ref) {
  return ref.watch(localSessionProvider).connection;
});
