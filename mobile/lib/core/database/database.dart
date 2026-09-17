import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as p;

import 'tables.dart';

part 'database.g.dart';

/// Main SQLite database for offline-first architecture.
///
/// All data is stored locally; API is used only for sync operations.
/// Uses Drift for compile-time type safety and migration support.
@DriftDatabase(tables: [
  // Master data
  CachedUsers,
  Classrooms,
  Students,
  // Cache
  DashboardCache,
  MyAttendanceCache,
  MyAttendanceHistory,
  CachedNotifications,
  FeeSummaryCache,
  PaymentEntries,
  // Transaction queues
  QueuedScans,
  QueuedClassAttendances,
  PendingActions,
  // Sync metadata
  SyncMetadata,
  // Local authentication (independent)
  LocalAccounts,
  BackendConnection,
])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());

  /// For testing with in-memory database.
  AppDatabase.forTesting(super.e);

  @override
  int get schemaVersion => 1;

  @override
  MigrationStrategy get migration {
    return MigrationStrategy(
      onCreate: (Migrator m) async {
        await m.createAll();
      },
      onUpgrade: (Migrator m, int from, int to) async {
        // Future migrations go here.
        // Example:
        // if (from < 2) {
        //   await m.addColumn(users, users.newColumn);
        // }
      },
      beforeOpen: (details) async {
        // Enable foreign keys for referential integrity.
        await customStatement('PRAGMA foreign_keys = ON');
      },
    );
  }

  // ===========================================================================
  // USER OPERATIONS
  // ===========================================================================

  /// Get cached user by ID.
  Future<CachedUser?> getUserById(String id) {
    return (select(cachedUsers)..where((t) => t.id.equals(id))).getSingleOrNull();
  }

  /// Insert or update user cache.
  Future<void> upsertUser(CachedUsersCompanion user) {
    return into(cachedUsers).insertOnConflictUpdate(user);
  }

  /// Delete user cache.
  Future<int> deleteUser(String id) {
    return (delete(cachedUsers)..where((t) => t.id.equals(id))).go();
  }

  /// Clear all users (for logout).
  Future<int> clearUsers() => delete(cachedUsers).go();

  // ===========================================================================
  // CLASSROOM OPERATIONS
  // ===========================================================================

  /// Get all classrooms.
  Future<List<Classroom>> getAllClassrooms() => select(classrooms).get();

  /// Get classroom by ID.
  Future<Classroom?> getClassroomById(String id) {
    return (select(classrooms)..where((t) => t.id.equals(id))).getSingleOrNull();
  }

  /// Insert or update classroom.
  Future<void> upsertClassroom(ClassroomsCompanion classroom) {
    return into(classrooms).insertOnConflictUpdate(classroom);
  }

  /// Batch upsert classrooms.
  Future<void> upsertClassrooms(List<ClassroomsCompanion> items) {
    return batch((b) {
      b.insertAllOnConflictUpdate(classrooms, items);
    });
  }

  /// Clear all classrooms.
  Future<int> clearClassrooms() => delete(classrooms).go();

  // ===========================================================================
  // STUDENT OPERATIONS
  // ===========================================================================

  /// Get students by classroom ID.
  Future<List<Student>> getStudentsByClassroom(String classroomId) {
    return (select(students)
          ..where((t) => t.classroomId.equals(classroomId))
          ..orderBy([(t) => OrderingTerm.asc(t.name)]))
        .get();
  }

  /// Batch upsert students.
  Future<void> upsertStudents(List<StudentsCompanion> items) {
    return batch((b) {
      b.insertAllOnConflictUpdate(students, items);
    });
  }

  /// Clear students by classroom.
  Future<int> clearStudentsByClassroom(String classroomId) {
    return (delete(students)..where((t) => t.classroomId.equals(classroomId)))
        .go();
  }

  /// Clear all students.
  Future<int> clearStudents() => delete(students).go();

  // ===========================================================================
  // DASHBOARD CACHE OPERATIONS
  // ===========================================================================

  /// Get dashboard cache for user.
  Future<DashboardCacheData?> getDashboardCache(String userId) {
    return (select(dashboardCache)..where((t) => t.userId.equals(userId)))
        .getSingleOrNull();
  }

  /// Insert or update dashboard cache.
  Future<void> upsertDashboardCache(DashboardCacheCompanion cache) {
    return into(dashboardCache).insertOnConflictUpdate(cache);
  }

  /// Clear dashboard cache for user.
  Future<int> clearDashboardCache(String userId) {
    return (delete(dashboardCache)..where((t) => t.userId.equals(userId))).go();
  }

  // ===========================================================================
  // MY ATTENDANCE OPERATIONS
  // ===========================================================================

  /// Get my attendance for today.
  Future<MyAttendanceCacheData?> getMyAttendanceToday(
    String userId,
    String date,
  ) {
    return (select(myAttendanceCache)
          ..where((t) => t.userId.equals(userId) & t.date.equals(date)))
        .getSingleOrNull();
  }

  /// Insert or update my attendance today.
  Future<void> upsertMyAttendanceToday(MyAttendanceCacheCompanion cache) {
    return into(myAttendanceCache).insertOnConflictUpdate(cache);
  }

  /// Get my attendance history.
  Future<List<MyAttendanceHistoryData>> getMyAttendanceHistory(String userId) {
    return (select(myAttendanceHistory)
          ..where((t) => t.userId.equals(userId))
          ..orderBy([(t) => OrderingTerm.desc(t.date)]))
        .get();
  }

  /// Batch upsert attendance history.
  Future<void> upsertAttendanceHistory(
    String userId,
    List<MyAttendanceHistoryCompanion> items,
  ) async {
    // Clear existing history for user first.
    await (delete(myAttendanceHistory)..where((t) => t.userId.equals(userId)))
        .go();
    await batch((b) {
      b.insertAll(myAttendanceHistory, items);
    });
  }

  // ===========================================================================
  // NOTIFICATION OPERATIONS
  // ===========================================================================

  /// Get notifications for user.
  Future<List<CachedNotification>> getNotifications(String userId) {
    return (select(cachedNotifications)
          ..where((t) => t.userId.equals(userId))
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .get();
  }

  /// Get unread notification count.
  Future<int> getUnreadNotificationCount(String userId) async {
    final query = selectOnly(cachedNotifications)
      ..addColumns([cachedNotifications.id.count()])
      ..where(cachedNotifications.userId.equals(userId) &
          cachedNotifications.isRead.not());
    final result = await query.getSingle();
    return result.read(cachedNotifications.id.count()) ?? 0;
  }

  /// Insert or update notification.
  Future<void> upsertNotification(CachedNotificationsCompanion notification) {
    return into(cachedNotifications).insertOnConflictUpdate(notification);
  }

  /// Batch upsert notifications.
  Future<void> upsertNotifications(List<CachedNotificationsCompanion> items) {
    return batch((b) {
      b.insertAllOnConflictUpdate(cachedNotifications, items);
    });
  }

  /// Mark notification as read locally.
  Future<bool> markNotificationRead(String id) async {
    final rows =
        await (update(cachedNotifications)..where((t) => t.id.equals(id)))
            .write(const CachedNotificationsCompanion(isRead: Value(true)));
    return rows > 0;
  }

  /// Clear notifications for user.
  Future<int> clearNotifications(String userId) {
    return (delete(cachedNotifications)..where((t) => t.userId.equals(userId)))
        .go();
  }

  // ===========================================================================
  // FINANCE CACHE OPERATIONS
  // ===========================================================================

  /// Get fee summary for user.
  Future<FeeSummaryCacheData?> getFeeSummary(String userId) {
    return (select(feeSummaryCache)..where((t) => t.userId.equals(userId)))
        .getSingleOrNull();
  }

  /// Insert or update fee summary.
  Future<void> upsertFeeSummary(FeeSummaryCacheCompanion summary) {
    return into(feeSummaryCache).insertOnConflictUpdate(summary);
  }

  /// Get payment entries.
  Future<List<PaymentEntryRow>> getPaymentEntries() {
    return select(paymentEntries).get();
  }

  /// Batch upsert payment entries.
  Future<void> upsertPaymentEntries(List<PaymentEntriesCompanion> items) {
    return batch((b) {
      b.insertAllOnConflictUpdate(paymentEntries, items);
    });
  }

  /// Clear finance cache.
  Future<void> clearFinanceCache(String userId) async {
    await (delete(feeSummaryCache)..where((t) => t.userId.equals(userId))).go();
    await delete(paymentEntries).go();
  }

  // ===========================================================================
  // QUEUED SCANS OPERATIONS
  // ===========================================================================

  /// Get all pending scans.
  Future<List<QueuedScanRow>> getPendingScans() {
    return (select(queuedScans)
          ..where((t) => t.syncStatus.equalsValue(SyncStatus.pending))
          ..orderBy([(t) => OrderingTerm.asc(t.scannedAt)]))
        .get();
  }

  /// Get all queued scans (including synced).
  Future<List<QueuedScanRow>> getAllQueuedScans() {
    return (select(queuedScans)
          ..orderBy([(t) => OrderingTerm.desc(t.scannedAt)]))
        .get();
  }

  /// Add scan to queue.
  Future<void> queueScan(QueuedScansCompanion scan) {
    return into(queuedScans).insert(scan);
  }

  /// Update scan sync status.
  Future<void> updateScanStatus(
    String id,
    SyncStatus status, {
    String? error,
  }) {
    return (update(queuedScans)..where((t) => t.id.equals(id))).write(
      QueuedScansCompanion(
        syncStatus: Value(status),
        syncError: Value(error),
        syncAttempts: const Value.absentIfNull(null), // Will increment in batch
      ),
    );
  }

  /// Remove synced scans.
  Future<int> removeSyncedScans() {
    return (delete(queuedScans)
          ..where((t) => t.syncStatus.equalsValue(SyncStatus.synced)))
        .go();
  }

  /// Remove scans by IDs.
  Future<int> removeScansByIds(Set<String> ids) {
    return (delete(queuedScans)..where((t) => t.id.isIn(ids))).go();
  }

  /// Clear all scans.
  Future<int> clearQueuedScans() => delete(queuedScans).go();

  // ===========================================================================
  // QUEUED CLASS ATTENDANCE OPERATIONS
  // ===========================================================================

  /// Get all pending class attendances.
  Future<List<QueuedClassAttendanceRow>> getPendingClassAttendances() {
    return (select(queuedClassAttendances)
          ..where((t) => t.syncStatus.equalsValue(SyncStatus.pending))
          ..orderBy([(t) => OrderingTerm.asc(t.savedAt)]))
        .get();
  }

  /// Get all queued class attendances.
  Future<List<QueuedClassAttendanceRow>> getAllQueuedClassAttendances() {
    return (select(queuedClassAttendances)
          ..orderBy([(t) => OrderingTerm.desc(t.savedAt)]))
        .get();
  }

  /// Add or replace class attendance to queue.
  /// Replaces existing entry for same classroom + date.
  Future<void> queueClassAttendance(QueuedClassAttendancesCompanion item) {
    return into(queuedClassAttendances).insertOnConflictUpdate(item);
  }

  /// Update class attendance sync status.
  Future<void> updateClassAttendanceStatus(
    String id,
    SyncStatus status, {
    String? error,
  }) {
    return (update(queuedClassAttendances)..where((t) => t.id.equals(id)))
        .write(
      QueuedClassAttendancesCompanion(
        syncStatus: Value(status),
        syncError: Value(error),
      ),
    );
  }

  /// Remove synced class attendances.
  Future<int> removeSyncedClassAttendances() {
    return (delete(queuedClassAttendances)
          ..where((t) => t.syncStatus.equalsValue(SyncStatus.synced)))
        .go();
  }

  /// Remove class attendances by IDs.
  Future<int> removeClassAttendancesByIds(Set<String> ids) {
    return (delete(queuedClassAttendances)..where((t) => t.id.isIn(ids))).go();
  }

  /// Clear all class attendances.
  Future<int> clearQueuedClassAttendances() =>
      delete(queuedClassAttendances).go();

  // ===========================================================================
  // PENDING ACTIONS OPERATIONS
  // ===========================================================================

  /// Get all pending actions.
  Future<List<PendingAction>> getPendingActions() {
    return (select(pendingActions)
          ..where((t) => t.syncStatus.equalsValue(SyncStatus.pending))
          ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
        .get();
  }

  /// Add pending action.
  Future<int> addPendingAction(PendingActionsCompanion action) {
    return into(pendingActions).insert(action);
  }

  /// Update pending action status.
  Future<void> updatePendingActionStatus(
    int id,
    SyncStatus status, {
    String? error,
  }) {
    return (update(pendingActions)..where((t) => t.id.equals(id))).write(
      PendingActionsCompanion(
        syncStatus: Value(status),
        syncError: Value(error),
      ),
    );
  }

  /// Remove synced actions.
  Future<int> removeSyncedActions() {
    return (delete(pendingActions)
          ..where((t) => t.syncStatus.equalsValue(SyncStatus.synced)))
        .go();
  }

  // ===========================================================================
  // SYNC METADATA OPERATIONS
  // ===========================================================================

  /// Get sync metadata for entity type.
  Future<SyncMetadataData?> getSyncMetadata(String entityType) {
    return (select(syncMetadata)..where((t) => t.entityType.equals(entityType)))
        .getSingleOrNull();
  }

  /// Update sync metadata.
  Future<void> updateSyncMetadata(SyncMetadataCompanion metadata) {
    return into(syncMetadata).insertOnConflictUpdate(metadata);
  }

  /// Check if entity is stale.
  Future<bool> isEntityStale(String entityType, Duration threshold) async {
    final meta = await getSyncMetadata(entityType);
    if (meta?.lastSyncedAt == null) return true;
    return DateTime.now().difference(meta!.lastSyncedAt!) > threshold;
  }

  // ===========================================================================
  // UTILITY OPERATIONS
  // ===========================================================================

  /// Clear all data (for logout).
  Future<void> clearAllData() async {
    await delete(cachedUsers).go();
    await delete(classrooms).go();
    await delete(students).go();
    await delete(dashboardCache).go();
    await delete(myAttendanceCache).go();
    await delete(myAttendanceHistory).go();
    await delete(cachedNotifications).go();
    await delete(feeSummaryCache).go();
    await delete(paymentEntries).go();
    await delete(queuedScans).go();
    await delete(queuedClassAttendances).go();
    await delete(pendingActions).go();
    await delete(syncMetadata).go();
  }

  /// Get pending sync counts for UI indicator.
  Future<({int scans, int classAttendances, int actions})>
      getPendingSyncCounts() async {
    final scanCount = await (selectOnly(queuedScans)
          ..addColumns([queuedScans.id.count()])
          ..where(queuedScans.syncStatus.equalsValue(SyncStatus.pending)))
        .map((row) => row.read(queuedScans.id.count()) ?? 0)
        .getSingle();

    final classCount = await (selectOnly(queuedClassAttendances)
          ..addColumns([queuedClassAttendances.id.count()])
          ..where(queuedClassAttendances.syncStatus
              .equalsValue(SyncStatus.pending)))
        .map((row) => row.read(queuedClassAttendances.id.count()) ?? 0)
        .getSingle();

    final actionCount = await (selectOnly(pendingActions)
          ..addColumns([pendingActions.id.count()])
          ..where(pendingActions.syncStatus.equalsValue(SyncStatus.pending)))
        .map((row) => row.read(pendingActions.id.count()) ?? 0)
        .getSingle();

    return (
      scans: scanCount,
      classAttendances: classCount,
      actions: actionCount
    );
  }
}

/// Opens database connection.
LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dbFolder = await getApplicationDocumentsDirectory();
    final file = File(p.join(dbFolder.path, 'sms_absensi.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}
