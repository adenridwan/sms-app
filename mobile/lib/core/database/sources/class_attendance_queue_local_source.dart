import 'dart:convert';

import 'package:drift/drift.dart';

import '../../../features/attendance/models/class_attendance.dart';
import '../../../features/attendance/models/queued_class_attendance.dart';
import '../database.dart';
import '../tables.dart';

/// Local data source for class attendance queue.
///
/// Replaces SharedPreferences-based [ClassAttendanceQueueStore] with SQLite.
class ClassAttendanceQueueLocalSource {
  ClassAttendanceQueueLocalSource(this._db);

  final AppDatabase _db;

  /// Get all pending class attendances.
  Future<List<QueuedClassAttendance>> getPendingAttendances() async {
    final rows = await _db.getPendingClassAttendances();
    return rows.map(_rowToQueued).toList();
  }

  /// Get all queued class attendances (for display).
  Future<List<QueuedClassAttendance>> getAllAttendances() async {
    final rows = await _db.getAllQueuedClassAttendances();
    return rows.map(_rowToQueued).toList();
  }

  /// Get pending count.
  Future<int> getPendingCount() async {
    final counts = await _db.getPendingSyncCounts();
    return counts.classAttendances;
  }

  /// Add or replace class attendance in queue.
  ///
  /// If an entry for the same classroom + date already exists, it's replaced.
  Future<void> addAttendance(QueuedClassAttendance attendance) async {
    // Generate unique ID based on classroom + date to enable replacement.
    final id = '${attendance.classroomId}_${_fmtDate(attendance.date)}';

    await _db.queueClassAttendance(QueuedClassAttendancesCompanion(
      id: Value(id),
      classroomId: Value(attendance.classroomId),
      classroomName: Value(attendance.classroomName),
      date: Value(attendance.date),
      marksJson: Value(_encodeMarks(attendance.marks)),
      savedAt: Value(attendance.savedAt),
      syncStatus: const Value(SyncStatus.pending),
    ));
  }

  /// Mark attendances as syncing.
  Future<void> markAsSyncing(Set<String> ids) async {
    for (final id in ids) {
      await _db.updateClassAttendanceStatus(id, SyncStatus.syncing);
    }
  }

  /// Mark attendances as synced.
  Future<void> markAsSynced(Set<String> ids) async {
    for (final id in ids) {
      await _db.updateClassAttendanceStatus(id, SyncStatus.synced);
    }
  }

  /// Mark attendances as failed.
  Future<void> markAsFailed(Set<String> ids, String error) async {
    for (final id in ids) {
      await _db.updateClassAttendanceStatus(id, SyncStatus.failed, error: error);
    }
  }

  /// Remove synced attendances.
  Future<void> removeSyncedAttendances() async {
    await _db.removeSyncedClassAttendances();
  }

  /// Remove attendances by IDs.
  Future<void> removeByIds(Set<String> ids) async {
    await _db.removeClassAttendancesByIds(ids);
  }

  /// Clear all (for logout).
  Future<void> clearAll() async {
    await _db.clearQueuedClassAttendances();
  }

  QueuedClassAttendance _rowToQueued(QueuedClassAttendanceRow row) {
    return QueuedClassAttendance(
      id: row.id,
      classroomId: row.classroomId,
      classroomName: row.classroomName,
      date: row.date,
      marks: _decodeMarks(row.marksJson),
      savedAt: row.savedAt,
    );
  }

  String _encodeMarks(Map<String, AttendanceMark> marks) {
    return jsonEncode({
      for (final e in marks.entries) e.key: e.value.slug,
    });
  }

  Map<String, AttendanceMark> _decodeMarks(String json) {
    try {
      final map = jsonDecode(json) as Map;
      return {
        for (final e in map.entries)
          e.key.toString(): _markFromSlug(e.value.toString()),
      };
    } catch (_) {
      return {};
    }
  }

  AttendanceMark _markFromSlug(String slug) {
    return AttendanceMark.values.firstWhere(
      (m) => m.slug == slug,
      orElse: () => AttendanceMark.hadir,
    );
  }

  static String _fmtDate(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)}';
  }
}
