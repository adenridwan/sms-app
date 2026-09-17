import '../../../core/database/sources/scan_queue_local_source.dart';
import '../models/queued_scan.dart';

/// Penyimpanan persisten antrean scan offline.
///
/// Sekarang menggunakan SQLite (via [ScanQueueLocalSource]) sebagai pengganti
/// SharedPreferences. Interface tetap sama untuk kompatibilitas.
class OfflineQueueStore {
  OfflineQueueStore(this._source);

  final ScanQueueLocalSource _source;

  Future<List<QueuedScan>> load() => _source.getAllScans();

  Future<List<QueuedScan>> add(QueuedScan scan) async {
    await _source.addScan(scan);
    return await _source.getAllScans();
  }

  Future<List<QueuedScan>> removeIds(Set<String> ids) async {
    await _source.removeByIds(ids);
    return await _source.getAllScans();
  }

  Future<void> clear() => _source.clearAll();
}
