import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/location/location_service.dart';
import '../../../core/network/api_exception.dart';
import '../data/attendance_providers.dart';
import '../data/attendance_repository.dart';
import '../data/offline_queue_store.dart';
import '../models/bootstrap_data.dart';
import '../models/queued_scan.dart';
import '../models/scan_result.dart';
import '../models/scan_time.dart';

class ScanState {
  const ScanState({
    this.bootstrap,
    this.loadingBootstrap = true,
    this.bootstrapError,
    this.mode = ScanTime.masuk,
    this.queueCount = 0,
  });

  final BootstrapData? bootstrap;
  final bool loadingBootstrap;
  final String? bootstrapError;
  final ScanTime mode;
  final int queueCount;

  bool get requireLocation => bootstrap?.requireLocation ?? false;
  bool get isHoliday => bootstrap?.isHoliday ?? false;

  ScanState copyWith({
    BootstrapData? bootstrap,
    bool? loadingBootstrap,
    String? bootstrapError,
    ScanTime? mode,
    int? queueCount,
  }) {
    return ScanState(
      bootstrap: bootstrap ?? this.bootstrap,
      loadingBootstrap: loadingBootstrap ?? this.loadingBootstrap,
      bootstrapError: bootstrapError,
      mode: mode ?? this.mode,
      queueCount: queueCount ?? this.queueCount,
    );
  }
}

class ScanController extends StateNotifier<ScanState> {
  ScanController(this._repo, this._queue, this._location)
      : super(const ScanState()) {
    loadBootstrap();
    refreshQueueCount();
  }

  final AttendanceRepository _repo;
  final OfflineQueueStore _queue;
  final LocationService _location;

  Future<void> loadBootstrap() async {
    state = state.copyWith(loadingBootstrap: true, bootstrapError: null);
    try {
      final data = await _repo.bootstrap();
      state = state.copyWith(bootstrap: data, loadingBootstrap: false);
    } on ApiException catch (e) {
      state = state.copyWith(loadingBootstrap: false, bootstrapError: e.message);
    }
  }

  void setMode(ScanTime mode) => state = state.copyWith(mode: mode);

  Future<void> refreshQueueCount() async {
    final items = await _queue.load();
    state = state.copyWith(queueCount: items.length);
  }

  /// Submit satu kode. Menangani GPS (bila wajib) & fallback antre offline.
  Future<ScanResult> submit(String uniqueCode) async {
    if (state.isHoliday) {
      return ScanResult.failure('Hari ini hari libur — absensi dinonaktifkan.');
    }

    double? lat, lng;
    if (state.requireLocation) {
      final loc = await _location.current();
      if (!loc.ok) {
        return ScanResult.failure(loc.error ?? 'Lokasi tidak tersedia.');
      }
      lat = loc.latitude;
      lng = loc.longitude;
    }

    try {
      return await _repo.scan(
        uniqueCode: uniqueCode,
        waktu: state.mode,
        latitude: lat,
        longitude: lng,
      );
    } on ApiException catch (e) {
      if (e.isNetwork) {
        await _enqueue(uniqueCode, lat, lng);
        return ScanResult.queuedOffline();
      }
      return ScanResult.failure(e.message);
    }
  }

  Future<void> _enqueue(String code, double? lat, double? lng) async {
    final scan = QueuedScan(
      id: DateTime.now().microsecondsSinceEpoch.toString(),
      uniqueCode: code,
      waktu: state.mode,
      scannedAt: DateTime.now(),
      latitude: lat,
      longitude: lng,
    );
    final items = await _queue.add(scan);
    state = state.copyWith(queueCount: items.length);
  }

  /// Sinkron antrean offline. Mengembalikan ringkasan, atau null bila kosong.
  /// Melempar [ApiException] bila jaringan masih bermasalah.
  Future<({int total, int success, int failed})?> syncOffline() async {
    final items = await _queue.load();
    if (items.isEmpty) return null;

    final data = await _repo.syncOffline(items);
    final results = ((data['results'] as List?) ?? const [])
        .cast<Map>()
        .toList();

    final doneIds = <String>{};
    for (var i = 0; i < items.length && i < results.length; i++) {
      if (results[i]['success'] == true) doneIds.add(items[i].id);
    }
    final remaining = await _queue.removeIds(doneIds);
    state = state.copyWith(queueCount: remaining.length);

    return (
      total: (data['total'] as num?)?.toInt() ?? items.length,
      success: (data['success'] as num?)?.toInt() ?? doneIds.length,
      failed: (data['failed'] as num?)?.toInt() ??
          (items.length - doneIds.length),
    );
  }
}

final scanControllerProvider =
    StateNotifierProvider<ScanController, ScanState>((ref) {
  return ScanController(
    ref.watch(attendanceRepositoryProvider),
    ref.watch(offlineQueueStoreProvider),
    ref.watch(locationServiceProvider),
  );
});
