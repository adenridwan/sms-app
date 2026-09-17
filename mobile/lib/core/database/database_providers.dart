import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/local_auth_service.dart';
import 'database.dart';
import 'sources/sources.dart';

/// Global database instance.
///
/// Initialized once at app startup (in main.dart) and shared across all providers.
/// Access via `ref.watch(databaseProvider)`.
///
/// Note: This provider should be overridden in main.dart with the pre-initialized
/// database instance to ensure migration completes before app starts.
final databaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(() => db.close());
  return db;
});

// =============================================================================
// LOCAL DATA SOURCES
// =============================================================================

/// User local source.
final userLocalSourceProvider = Provider<UserLocalSource>((ref) {
  return UserLocalSource(ref.watch(databaseProvider));
});

/// Dashboard local source.
final dashboardLocalSourceProvider = Provider<DashboardLocalSource>((ref) {
  return DashboardLocalSource(ref.watch(databaseProvider));
});

/// Notification local source.
final notificationLocalSourceProvider = Provider<NotificationLocalSource>((ref) {
  return NotificationLocalSource(ref.watch(databaseProvider));
});

/// Finance local source.
final financeLocalSourceProvider = Provider<FinanceLocalSource>((ref) {
  return FinanceLocalSource(ref.watch(databaseProvider));
});

/// Attendance local source.
final attendanceLocalSourceProvider = Provider<AttendanceLocalSource>((ref) {
  return AttendanceLocalSource(ref.watch(databaseProvider));
});

/// Classroom local source.
final classroomLocalSourceProvider = Provider<ClassroomLocalSource>((ref) {
  return ClassroomLocalSource(ref.watch(databaseProvider));
});

/// Scan queue local source (replaces OfflineQueueStore).
final scanQueueLocalSourceProvider = Provider<ScanQueueLocalSource>((ref) {
  return ScanQueueLocalSource(ref.watch(databaseProvider));
});

/// Class attendance queue local source (replaces ClassAttendanceQueueStore).
final classAttendanceQueueLocalSourceProvider =
    Provider<ClassAttendanceQueueLocalSource>((ref) {
  return ClassAttendanceQueueLocalSource(ref.watch(databaseProvider));
});

// =============================================================================
// SYNC STATUS PROVIDERS
// =============================================================================

/// Pending sync counts stream for UI badge.
final pendingSyncCountsProvider =
    StreamProvider<({int scans, int classAttendances, int actions})>((ref) {
  final db = ref.watch(databaseProvider);
  // Poll every 2 seconds for changes.
  return Stream.periodic(const Duration(seconds: 2), (_) async {
    return await db.getPendingSyncCounts();
  }).asyncMap((future) => future);
});

/// Total pending items for simple badge display.
final totalPendingSyncCountProvider = Provider<AsyncValue<int>>((ref) {
  final counts = ref.watch(pendingSyncCountsProvider);
  return counts.whenData(
    (c) => c.scans + c.classAttendances + c.actions,
  );
});

// =============================================================================
// LOCAL AUTH
// =============================================================================

/// Local authentication service for offline-first login.
final localAuthServiceProvider = Provider<LocalAuthService>((ref) {
  return LocalAuthService(ref.watch(databaseProvider));
});
