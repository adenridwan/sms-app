import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/data/auth_repository.dart';
import '../../features/auth/models/user.dart';
import '../config/app_config.dart';
import '../database/database.dart';
import '../database/database_providers.dart';
import '../network/api_exception.dart';
import '../providers.dart';
import 'local_auth_service.dart';

/// Session state for local-first authentication.
/// Catatan sekolah yang terhubung di perangkat ini.
///
/// **Bukan** sesi login. Sejak gerbang autentikasi dipegang
/// `authControllerProvider`, kelas ini hanya menyimpan ke server mana
/// perangkat menunjuk dan identitas yang diberikan server saat provisioning.
/// Dulu ia juga punya tabel akun lokal berikut passwordnya sendiri; password
/// itu tidak pernah bisa diverifikasi server, sehingga pemiliknya selalu
/// ditolak begitu menyentuh API.
class LocalSession {
  const LocalSession({
    this.status = LocalSessionStatus.unknown,
    this.connection,
    this.error,
  });

  final LocalSessionStatus status;
  final BackendConnectionData? connection;
  final String? error;

  bool get isConnectedToBackend => connection != null;

  String? get schoolName => connection?.schoolName;
  String? get backendUserName => connection?.backendUserName;

  LocalSession copyWith({
    LocalSessionStatus? status,
    BackendConnectionData? connection,
    String? error,
    bool clearError = false,
    bool clearConnection = false,
  }) {
    return LocalSession(
      status: status ?? this.status,
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

      final connection = await _authService.getConnection();
      state = state.copyWith(connection: connection, clearError: true);
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
final isConnectedToBackendProvider = Provider<bool>((ref) {
  return ref.watch(localSessionProvider).isConnectedToBackend;
});

final backendConnectionProvider = Provider<BackendConnectionData?>((ref) {
  return ref.watch(localSessionProvider).connection;
});
