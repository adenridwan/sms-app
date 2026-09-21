import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/data/auth_repository.dart';
import '../config/app_config.dart';
import '../database/database.dart';
import '../database/database_providers.dart';
import '../network/api_exception.dart';
import '../providers.dart';
import 'local_auth_service.dart';

/// Session state for local-first authentication.
class LocalSession {
  const LocalSession({
    this.status = LocalSessionStatus.unknown,
    this.account,
    this.connection,
    this.error,
  });

  final LocalSessionStatus status;
  final LocalAccount? account;
  final BackendConnectionData? connection;
  final String? error;

  bool get isLoggedIn => status == LocalSessionStatus.authenticated;
  bool get isConnectedToBackend => connection != null;

  String? get schoolName => connection?.schoolName;
  String? get backendUserName => connection?.backendUserName;

  LocalSession copyWith({
    LocalSessionStatus? status,
    LocalAccount? account,
    BackendConnectionData? connection,
    String? error,
    bool clearError = false,
    bool clearConnection = false,
  }) {
    return LocalSession(
      status: status ?? this.status,
      account: account ?? this.account,
      connection: clearConnection ? null : (connection ?? this.connection),
      error: clearError ? null : (error ?? this.error),
    );
  }
}

enum LocalSessionStatus {
  unknown,
  unauthenticated,
  authenticating,
  authenticated,
}

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
    state = state.copyWith(status: LocalSessionStatus.unauthenticated);
  }

  /// Create new local account.
  Future<bool> signUp({
    required String email,
    required String fullName,
    required String password,
  }) async {
    state = state.copyWith(
      status: LocalSessionStatus.authenticating,
      clearError: true,
    );

    try {
      final account = await _authService.createAccount(
        email: email,
        fullName: fullName,
        password: password,
      );

      state = state.copyWith(
        status: LocalSessionStatus.authenticated,
        account: account,
      );
      return true;
    } on LocalAuthException catch (e) {
      state = state.copyWith(
        status: LocalSessionStatus.unauthenticated,
        error: e.message,
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        status: LocalSessionStatus.unauthenticated,
        error: 'Gagal membuat akun: $e',
      );
      return false;
    }
  }

  /// Login with local credentials.
  Future<bool> login({
    required String email,
    required String password,
  }) async {
    state = state.copyWith(
      status: LocalSessionStatus.authenticating,
      clearError: true,
    );

    try {
      final account = await _authService.login(email, password);

      if (account == null) {
        state = state.copyWith(
          status: LocalSessionStatus.unauthenticated,
          error: 'Email atau password salah.',
        );
        return false;
      }

      // Also load connection if exists
      final connection = await _authService.getConnection();

      state = state.copyWith(
        status: LocalSessionStatus.authenticated,
        account: account,
        connection: connection,
      );
      return true;
    } catch (e) {
      state = state.copyWith(
        status: LocalSessionStatus.unauthenticated,
        error: 'Gagal masuk: $e',
      );
      return false;
    }
  }

  /// Logout.
  Future<void> logout() async {
    state = const LocalSession(status: LocalSessionStatus.unauthenticated);
  }

  /// Change password.
  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    if (state.account == null) return false;

    try {
      await _authService.changePassword(
        id: state.account!.id,
        currentPassword: currentPassword,
        newPassword: newPassword,
      );
      return true;
    } on LocalAuthException catch (e) {
      state = state.copyWith(error: e.message);
      return false;
    }
  }

  /// Update profile.
  Future<bool> updateProfile({required String fullName}) async {
    if (state.account == null) return false;

    try {
      await _authService.updateProfile(
        id: state.account!.id,
        fullName: fullName,
      );

      // Reload account
      final account = await _authService.getAccountById(state.account!.id);
      if (account != null) {
        state = state.copyWith(account: account);
      }
      return true;
    } catch (e) {
      state = state.copyWith(error: 'Gagal update profil: $e');
      return false;
    }
  }

  /// Connect to backend via QR data.
  Future<bool> connectToBackend({
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

      final connection = await _authService.getConnection();
      state = state.copyWith(connection: connection, clearError: true);
      return true;
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
      return false;
    } catch (e) {
      await _restoreServerUrl(previousUrl, wasProvisioned);
      state = state.copyWith(error: 'Gagal terhubung: $e');
      return false;
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
final isLoggedInProvider = Provider<bool>((ref) {
  return ref.watch(localSessionProvider).isLoggedIn;
});

final isConnectedToBackendProvider = Provider<bool>((ref) {
  return ref.watch(localSessionProvider).isConnectedToBackend;
});

final currentLocalAccountProvider = Provider<LocalAccount?>((ref) {
  return ref.watch(localSessionProvider).account;
});

final backendConnectionProvider = Provider<BackendConnectionData?>((ref) {
  return ref.watch(localSessionProvider).connection;
});
