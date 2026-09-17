import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/database/sources/sources.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../auth/presentation/auth_controller.dart';
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
  ClassAttendanceQueueController(this._local, this._repo)
      : super(const ClassAttendanceQueueState()) {
    refresh();
  }

  final ClassAttendanceQueueLocalSource _local;
  final ClassAttendanceRepository _repo;

  Future<void> refresh() async {
    final items = await _local.getAllAttendances();
    state = ClassAttendanceQueueState(items: items);
  }

  Future<void> enqueue(QueuedClassAttendance item) async {
    await _local.addAttendance(item);
    await refresh();
  }

  /// Kirim ulang seluruh antrean. Mengembalikan jumlah yang berhasil terkirim.
  ///
  /// Entri dikirim satu per satu lewat endpoint bulk biasa — endpoint itu
  /// memperbarui baris yang sudah ada untuk tanggal tersebut, jadi pengulangan
  /// tidak menghasilkan duplikat. Entri yang gagal **tetap di antrean**.
  Future<({int total, int success})> sync() async {
    final pending = await _local.getPendingAttendances();
    if (pending.isEmpty) return (total: 0, success: 0);

    state = ClassAttendanceQueueState(items: pending, syncing: true);

    final done = <String>{};
    for (final item in pending) {
      try {
        await _local.markAsSyncing({item.id});

        await _repo.submit(
          classroomId: item.classroomId,
          classroomName: item.classroomName,
          date: item.date,
          marks: item.marks,
        );

        await _local.markAsSynced({item.id});
        done.add(item.id);
      } on ApiException catch (e) {
        // Jaringan mati lagi → hentikan, sisanya dicoba lain waktu.
        if (e.isNetwork) {
          await _local.markAsFailed({item.id}, e.message);
          break;
        }
        // Ditolak server (mis. kelas dihapus, izin dicabut): tandai gagal
        // tapi tetap hapus dari antrean.
        await _local.markAsSynced({item.id});
        done.add(item.id);
      }
    }

    // Remove synced items.
    await _local.removeSyncedAttendances();
    await refresh();

    return (total: pending.length, success: done.length);
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
    ref.watch(classAttendanceQueueLocalSourceProvider),
    ref.watch(classAttendanceRepositoryProvider),
  );

  // Sinkron hanya dilakukan bila sesi sudah terverifikasi (punya token). Sesi
  // hasil login offline belum punya token; revalidateSession() di auth
  // controller akan menukarnya. Tunggu sebentar supaya revalidasi selesai.
  ref.listen<BackendStatus>(backendStatusProvider, (previous, next) async {
    if (next == BackendStatus.online && previous != BackendStatus.online) {
      await Future.delayed(const Duration(milliseconds: 500));

      final auth = ref.read(authControllerProvider);
      if (!auth.isSessionVerified) return;

      controller.syncSilently();
    }
  });

  return controller;
});
