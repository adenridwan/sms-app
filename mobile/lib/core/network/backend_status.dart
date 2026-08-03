import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Status jangkauan backend, disimpulkan dari hasil request Dio terakhir.
///
/// `unknown` = belum ada request sama sekali sejak app dibuka.
/// `online` = request terakhir sampai ke server (termasuk error 4xx/5xx —
/// itu tetap bukti server terjangkau, cuma request-nya yang ditolak).
/// `offline` = request terakhir gagal karena koneksi/timeout (tak ada respons).
enum BackendStatus { unknown, online, offline }

class BackendStatusController extends StateNotifier<BackendStatus> {
  BackendStatusController() : super(BackendStatus.unknown);

  void markOnline() {
    if (state != BackendStatus.online) state = BackendStatus.online;
  }

  void markOffline() {
    if (state != BackendStatus.offline) state = BackendStatus.offline;
  }
}

final backendStatusProvider =
    StateNotifierProvider<BackendStatusController, BackendStatus>(
  (ref) => BackendStatusController(),
);

/// Penanda "server menolak token kita" (HTTP 401 di endpoint terproteksi).
///
/// Dinaikkan oleh interceptor Dio, didengarkan `AuthController` untuk
/// membersihkan sesi. Sengaja lewat sinyal netral seperti ini — bukan
/// `dioProvider` memanggil `authControllerProvider` langsung — agar lapisan
/// `core/` tidak bergantung pada `features/` (dan tak ada siklus provider).
class UnauthorizedSignal extends StateNotifier<int> {
  UnauthorizedSignal() : super(0);

  void fire() => state = state + 1;
}

final unauthorizedSignalProvider =
    StateNotifierProvider<UnauthorizedSignal, int>((ref) => UnauthorizedSignal());
