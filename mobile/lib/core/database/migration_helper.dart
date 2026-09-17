import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/attendance/models/class_attendance.dart';
import '../../features/attendance/models/queued_class_attendance.dart';
import '../../features/attendance/models/queued_scan.dart';
import '../../features/auth/models/user.dart';
import 'database.dart';
import 'tables.dart';

/// Helper to migrate data from SharedPreferences to SQLite.
///
/// This should be run once after app update. After migration completes,
/// the old SharedPreferences keys are cleaned up.
class MigrationHelper {
  MigrationHelper(this._db);

  final AppDatabase _db;

  static const _migrationKey = 'sqlite_migration_complete_v1';
  static const _oldScanQueueKey = 'offline_scans';
  static const _oldClassQueueKey = 'offline_class_attendance';
  static const _oldUserCacheKey = 'cached_user';

  /// Check if migration is needed.
  Future<bool> needsMigration() async {
    final prefs = await SharedPreferences.getInstance();
    return !prefs.containsKey(_migrationKey);
  }

  /// Run the full migration.
  ///
  /// Returns the number of items migrated.
  Future<MigrationResult> migrate() async {
    final prefs = await SharedPreferences.getInstance();

    if (prefs.containsKey(_migrationKey)) {
      return const MigrationResult.skipped();
    }

    var scans = 0;
    var classAttendances = 0;
    var users = 0;

    // 1. Migrate scan queue.
    scans = await _migrateScanQueue(prefs);

    // 2. Migrate class attendance queue.
    classAttendances = await _migrateClassAttendanceQueue(prefs);

    // 3. Migrate user cache.
    users = await _migrateUserCache(prefs);

    // 4. Mark migration complete.
    await prefs.setBool(_migrationKey, true);

    // 5. Clean up old keys (optional - keeps SharedPrefs clean).
    await _cleanupOldKeys(prefs);

    return MigrationResult(
      scans: scans,
      classAttendances: classAttendances,
      users: users,
    );
  }

  Future<int> _migrateScanQueue(SharedPreferences prefs) async {
    final raw = prefs.getString(_oldScanQueueKey);
    if (raw == null || raw.isEmpty) return 0;

    try {
      final list = (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
      for (final json in list) {
        final scan = QueuedScan.fromJson(json);
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
      return list.length;
    } catch (_) {
      return 0;
    }
  }

  Future<int> _migrateClassAttendanceQueue(SharedPreferences prefs) async {
    final raw = prefs.getString(_oldClassQueueKey);
    if (raw == null || raw.isEmpty) return 0;

    try {
      final list = (jsonDecode(raw) as List)
          .whereType<Map>()
          .map((e) => QueuedClassAttendance.fromJson(
              Map<String, dynamic>.from(e)))
          .toList();

      for (final item in list) {
        await _db.queueClassAttendance(QueuedClassAttendancesCompanion(
          id: Value(item.id),
          classroomId: Value(item.classroomId),
          classroomName: Value(item.classroomName),
          date: Value(item.date),
          marksJson: Value(_encodeMarks(item.marks)),
          savedAt: Value(item.savedAt),
          syncStatus: const Value(SyncStatus.pending),
        ));
      }
      return list.length;
    } catch (_) {
      return 0;
    }
  }

  Future<int> _migrateUserCache(SharedPreferences prefs) async {
    final raw = prefs.getString(_oldUserCacheKey);
    if (raw == null || raw.isEmpty) return 0;

    try {
      final json = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      final user = User.fromJson(json);

      await _db.upsertUser(CachedUsersCompanion(
        id: Value(user.id),
        email: Value(user.email),
        fullName: Value(user.fullName),
        username: Value(user.username),
        userType: Value(user.userType),
        avatarUrl: Value(user.avatarUrl),
        tenantId: Value(user.tenantId),
        rolesJson: Value(jsonEncode(user.roles)),
        permissionsJson: Value(jsonEncode(user.permissions)),
        syncedAt: Value(DateTime.now()),
      ));
      return 1;
    } catch (_) {
      return 0;
    }
  }

  Future<void> _cleanupOldKeys(SharedPreferences prefs) async {
    // Remove old keys to keep SharedPreferences clean.
    // Keep them for now in case we need to rollback.
    // await prefs.remove(_oldScanQueueKey);
    // await prefs.remove(_oldClassQueueKey);
    // await prefs.remove(_oldUserCacheKey);
  }

  String _encodeMarks(Map<String, AttendanceMark> marks) {
    return jsonEncode({
      for (final e in marks.entries) e.key: e.value.slug,
    });
  }
}

/// Result of migration operation.
class MigrationResult {
  const MigrationResult({
    this.skipped = false,
    this.scans = 0,
    this.classAttendances = 0,
    this.users = 0,
  });

  const MigrationResult.skipped() : this(skipped: true);

  final bool skipped;
  final int scans;
  final int classAttendances;
  final int users;

  int get totalMigrated => scans + classAttendances + users;
  bool get hasMigrated => !skipped && totalMigrated > 0;
}
