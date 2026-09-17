import 'package:drift/drift.dart';

import '../../../features/attendance/models/queued_scan.dart';
import '../../../features/attendance/models/scan_time.dart';
import '../database.dart';
import '../tables.dart';

/// Local data source for QR scan queue.
///
/// Replaces SharedPreferences-based [OfflineQueueStore] with SQLite.
class ScanQueueLocalSource {
  ScanQueueLocalSource(this._db);

  final AppDatabase _db;

  /// Get all pending scans (not yet synced).
  Future<List<QueuedScan>> getPendingScans() async {
    final rows = await _db.getPendingScans();
    return rows.map(_rowToQueuedScan).toList();
  }

  /// Get all queued scans (for display in queue screen).
  Future<List<QueuedScan>> getAllScans() async {
    final rows = await _db.getAllQueuedScans();
    return rows.map(_rowToQueuedScan).toList();
  }

  /// Get pending scan count.
  Future<int> getPendingCount() async {
    final counts = await _db.getPendingSyncCounts();
    return counts.scans;
  }

  /// Add a scan to the queue.
  Future<void> addScan(QueuedScan scan) async {
    await _db.queueScan(QueuedScansCompanion(
      id: Value(scan.id),
      uniqueCode: Value(scan.uniqueCode),
      waktu: Value(scan.waktu.name),
      scannedAt: Value(scan.scannedAt),
      latitude: Value(scan.latitude),
      longitude: Value(scan.longitude),
      syncStatus: const Value(SyncStatus.pending),
    ));
  }

  /// Mark scans as syncing.
  Future<void> markAsSyncing(Set<String> ids) async {
    for (final id in ids) {
      await _db.updateScanStatus(id, SyncStatus.syncing);
    }
  }

  /// Mark scans as synced.
  Future<void> markAsSynced(Set<String> ids) async {
    for (final id in ids) {
      await _db.updateScanStatus(id, SyncStatus.synced);
    }
  }

  /// Mark scans as failed.
  Future<void> markAsFailed(Set<String> ids, String error) async {
    for (final id in ids) {
      await _db.updateScanStatus(id, SyncStatus.failed, error: error);
    }
  }

  /// Remove synced scans.
  Future<void> removeSyncedScans() async {
    await _db.removeSyncedScans();
  }

  /// Remove scans by IDs.
  Future<void> removeByIds(Set<String> ids) async {
    await _db.removeScansByIds(ids);
  }

  /// Clear all scans (for logout).
  Future<void> clearAll() async {
    await _db.clearQueuedScans();
  }

  QueuedScan _rowToQueuedScan(QueuedScanRow row) {
    return QueuedScan(
      id: row.id,
      uniqueCode: row.uniqueCode,
      waktu: row.waktu == 'pulang' ? ScanTime.pulang : ScanTime.masuk,
      scannedAt: row.scannedAt,
      latitude: row.latitude,
      longitude: row.longitude,
    );
  }
}
