import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../../core/storage/sync_status_store.dart';
import '../data/class_attendance_queue_store.dart';
import '../data/class_attendance_repository.dart';
import '../models/queued_class_attendance.dart';

class ClassAttendanceQueueState {
  const ClassAttendanceQueueState({
    this.items = const [],
    this.syncing = false,
  });

  final List<QueuedClassAttendance> items;
  final bool syncing;

  int get count => items.length;
}

/// Pemilik antrean absen kelas offline: memuat, menambah, dan mengirim ulang.
///
/// Dipisahkan dari `ClassAttendanceController` (yang ber-`family` per kelas)
/// karena antrean bersifat global — satu untuk seluruh aplikasi.
class ClassAttendanceQueueController
    extends StateNotifier<ClassAttendanceQueueState> {
  ClassAttendanceQueueController(this._store, this._repo)
      : super(const ClassAttendanceQueueState()) {
    refresh();
  }

  final ClassAttendanceQueueStore _store;
  final ClassAttendanceRepository _repo;

  Future<void> refresh() async {
    state = ClassAttendanceQueueState(items: await _store.load());
  }

  Future<void> enqueue(QueuedClassAttendance item) async {
    final items = await _store.add(item);
    state = ClassAttendanceQueueState(items: items);
  }

  /// Kirim ulang seluruh antrean. Mengembalikan jumlah yang berhasil terkirim.
  ///
  /// Entri dikirim satu per satu lewat endpoint bulk biasa — endpoint itu
  /// memperbarui baris yang sudah ada untuk tanggal tersebut, jadi pengulangan
  /// tidak menghasilkan duplikat. Entri yang gagal **tetap di antrean**.
  Future<({int total, int success})> sync() async {
    final items = await _store.load();
    if (items.isEmpty) return (total: 0, success: 0);

    state = ClassAttendanceQueueState(items: items, syncing: true);

    final done = <String>{};
    for (final item in items) {
      try {
        await _repo.submit(
          classroomId: item.classroomId,
          date: item.date,
          marks: item.marks,
        );
        done.add(item.id);
      } on ApiException catch (e) {
        // Jaringan mati lagi → hentikan, sisanya dicoba lain waktu.
        if (e.isNetwork) break;
        // Ditolak server (mis. kelas dihapus, izin dicabut): membiarkannya
        // di antrean hanya akan gagal selamanya, jadi dibuang.
        done.add(item.id);
      }
    }

    final remaining = await _store.removeIds(done);
    state = ClassAttendanceQueueState(items: remaining);
    await SyncStatusStore().markSynced();
    return (total: items.length, success: done.length);
  }

  /// Versi senyap untuk pemulihan koneksi — kegagalan tidak ditampilkan.
  Future<void> syncSilently() async {
    if (state.count == 0) return;
    try {
      await sync();
    } catch (_) {
      // biarkan tetap di antrean
    }
  }
}

final classAttendanceQueueProvider = StateNotifierProvider<
    ClassAttendanceQueueController, ClassAttendanceQueueState>((ref) {
  // Antrean adalah data perangkat, tapi controllernya dibangun ulang saat ganti
  // akun agar tidak memakai token pengguna sebelumnya saat sinkron.
  ref.watch(authControllerProvider.select((s) => s.user?.id));

  final controller = ClassAttendanceQueueController(
    ref.watch(classAttendanceQueueStoreProvider),
    ref.watch(classAttendanceRepositoryProvider),
  );

  ref.listen<BackendStatus>(backendStatusProvider, (previous, next) {
    if (next == BackendStatus.online && previous != BackendStatus.online) {
      controller.syncSilently();
    }
  });

  return controller;
});

final classAttendanceQueueStoreProvider =
    Provider<ClassAttendanceQueueStore>((ref) => ClassAttendanceQueueStore());
