/// Configuration for data freshness thresholds.
class SyncConfig {
  SyncConfig._();

  /// Dashboard stats staleness threshold.
  static const dashboardStale = Duration(hours: 1);

  /// Notifications staleness threshold.
  static const notificationsStale = Duration(minutes: 30);

  /// Finance data staleness threshold.
  static const financeStale = Duration(hours: 1);

  /// Classrooms data staleness threshold.
  static const classroomsStale = Duration(hours: 24);

  /// Students data staleness threshold.
  static const studentsStale = Duration(hours: 24);

  /// My attendance today staleness threshold.
  static const myAttendanceStale = Duration(minutes: 5);

  /// Minimum interval between sync attempts.
  static const syncCooldown = Duration(seconds: 30);

  /// Maximum retry attempts for failed syncs.
  static const maxRetryAttempts = 3;

  /// Delay between retry attempts.
  static const retryDelay = Duration(seconds: 5);
}

/// Entity types for sync tracking.
abstract class SyncEntity {
  static const dashboard = 'dashboard';
  static const notifications = 'notifications';
  static const finance = 'finance';
  static const classrooms = 'classrooms';
  static const students = 'students';
  static const myAttendance = 'my_attendance';
}
