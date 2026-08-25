import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import 'backend_status.dart';

/// Mengetuk server secara berkala selama status backend belum `online`.
///
/// [BackendStatus] bersifat **pasif** — ia hanya berubah sebagai efek samping
/// request Dio. Akibatnya, petugas yang mencatat absensi saat server mati lalu
/// membiarkan aplikasi terbuka tidak akan pernah tahu server sudah hidup:
/// tidak ada request yang jalan, status tetap `offline`, dan antrean
/// menganggur sampai ia sengaja menarik-untuk-muat-ulang.
///
/// Pemeriksa ini menutup celah itu. Satu respons apa pun sudah membuktikan
/// server terjangkau (interceptor di `providers.dart` menandai `online` bahkan
/// untuk 4xx), dan perubahan status itulah yang memicu sinkron otomatis lewat
/// listener yang sudah ada di `ScanController`.
///
/// Berhenti sendiri begitu `online` supaya tak ada lalu lintas sia-sia.
const Duration _interval = Duration(seconds: 15);

/// Endpoint publik paling murah: tanpa auth dan tanpa query database.
const String _path = '/ping';

final backendProbeProvider = Provider<void>((ref) {
  Timer? timer;

  Future<void> knock() async {
    try {
      await ref.read(dioProvider).get(
            _path,
            options: Options(
              // Jangan menunggu lama: ini cuma ketukan, bukan data yang dipakai.
              receiveTimeout: const Duration(seconds: 5),
              sendTimeout: const Duration(seconds: 5),
            ),
          );
    } catch (_) {
      // Interceptor sudah memperbarui status; kegagalan di sini wajar saat
      // memang masih offline dan tidak perlu dilaporkan ke pengguna.
    }
  }

  void start() {
    timer ??= Timer.periodic(_interval, (_) => knock());
  }

  void stop() {
    timer?.cancel();
    timer = null;
  }

  ref.listen<BackendStatus>(
    backendStatusProvider,
    (_, next) => next == BackendStatus.online ? stop() : start(),
    fireImmediately: true,
  );

  ref.onDispose(stop);
});
