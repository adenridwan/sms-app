import 'package:drift/drift.dart';

import '../../../features/attendance/models/my_attendance.dart';
import '../database.dart';

/// Local data source for personal attendance data.
class AttendanceLocalSource {
  AttendanceLocalSource(this._db);

  final AppDatabase _db;

  /// Staleness threshold for my attendance today.
  static const staleDuration = Duration(minutes: 5);

  /// Get cached attendance for today.
  Future<MyAttendanceToday?> getMyAttendanceToday(
    String userId,
    String date,
  ) async {
    final row = await _db.getMyAttendanceToday(userId, date);
    if (row == null) return null;
    return MyAttendanceToday(
      status: row.status,
      dateLabel: row.dateLabel,
      checkInTime: row.checkInTime,
      checkOutTime: row.checkOutTime,
      lateMinutes: row.lateMinutes,
      identityNumber: row.identityNumber,
      uniqueCode: row.uniqueCode,
      rfidCode: row.rfidCode,
    );
  }

  /// Check if attendance today cache is stale.
  Future<bool> isStale(String userId, String date) async {
    return await _db.isEntityStale('my_attendance_${userId}_$date', staleDuration);
  }

  /// Save attendance today to cache.
  Future<void> saveMyAttendanceToday(
    String userId,
    String date,
    MyAttendanceToday attendance,
  ) async {
    await _db.upsertMyAttendanceToday(MyAttendanceCacheCompanion(
      userId: Value(userId),
      date: Value(date),
      status: Value(attendance.status),
      dateLabel: Value(attendance.dateLabel),
      checkInTime: Value(attendance.checkInTime),
      checkOutTime: Value(attendance.checkOutTime),
      lateMinutes: Value(attendance.lateMinutes),
      identityNumber: Value(attendance.identityNumber),
      uniqueCode: Value(attendance.uniqueCode),
      rfidCode: Value(attendance.rfidCode),
      syncedAt: Value(DateTime.now()),
    ));

    // Update sync metadata.
    await _db.updateSyncMetadata(SyncMetadataCompanion(
      entityType: Value('my_attendance_${userId}_$date'),
      lastSyncedAt: Value(DateTime.now()),
    ));
  }

  /// Get cached attendance history.
  Future<List<MyAttendanceDay>> getAttendanceHistory(String userId) async {
    final rows = await _db.getMyAttendanceHistory(userId);
    return rows
        .map((r) => MyAttendanceDay(
              date: r.date,
              status: r.status,
              label: r.label,
            ))
        .toList();
  }

  /// Save attendance history.
  Future<void> saveAttendanceHistory(
    String userId,
    List<MyAttendanceDay> history,
  ) async {
    final companions = history
        .map((h) => MyAttendanceHistoryCompanion(
              userId: Value(userId),
              date: Value(h.date),
              status: Value(h.status),
              label: Value(h.label),
              syncedAt: Value(DateTime.now()),
            ))
        .toList();

    await _db.upsertAttendanceHistory(userId, companions);
  }
}
