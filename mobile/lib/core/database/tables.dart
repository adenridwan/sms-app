import 'package:drift/drift.dart';

// ============================================================================
// MASTER DATA TABLES
// ============================================================================

/// Users table - cached profile of current/previous users.
@DataClassName('CachedUser')
class CachedUsers extends Table {
  TextColumn get id => text()();
  TextColumn get email => text()();
  TextColumn get fullName => text()();
  TextColumn get username => text().nullable()();
  TextColumn get userType => text().nullable()();
  TextColumn get avatarUrl => text().nullable()();
  TextColumn get tenantId => text().nullable()();
  TextColumn get rolesJson => text().withDefault(const Constant('[]'))();
  TextColumn get permissionsJson => text().withDefault(const Constant('[]'))();
  DateTimeColumn get syncedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Classrooms accessible to user.
class Classrooms extends Table {
  TextColumn get id => text()();
  TextColumn get name => text()();
  TextColumn get academicYear => text().nullable()();
  BoolColumn get isActive => boolean().withDefault(const Constant(true))();
  IntColumn get studentsCount => integer().withDefault(const Constant(0))();
  DateTimeColumn get syncedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Students in classrooms.
class Students extends Table {
  TextColumn get id => text()();
  TextColumn get classroomId => text().references(Classrooms, #id)();
  TextColumn get nis => text().nullable()();
  TextColumn get name => text()();
  DateTimeColumn get syncedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

// ============================================================================
// CACHE TABLES
// ============================================================================

/// Dashboard cache - stores JSON per user/package.
class DashboardCache extends Table {
  TextColumn get userId => text()();
  TextColumn get package => text()();
  TextColumn get jsonData => text()();
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {userId, package};
}

/// My attendance today cache.
class MyAttendanceCache extends Table {
  TextColumn get userId => text()();
  TextColumn get date => text()();
  TextColumn get status => text()();
  TextColumn get checkInTime => text().nullable()();
  TextColumn get checkOutTime => text().nullable()();
  IntColumn get lateMinutes => integer().withDefault(const Constant(0))();
  TextColumn get identityNumber => text().nullable()();
  TextColumn get uniqueCode => text().nullable()();
  TextColumn get rfidCode => text().nullable()();
  TextColumn get dateLabel => text().nullable()();
  DateTimeColumn get syncedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {userId, date};
}

/// My attendance history cache.
class MyAttendanceHistory extends Table {
  IntColumn get rowId => integer().autoIncrement()();
  TextColumn get userId => text()();
  TextColumn get date => text()();
  TextColumn get status => text()();
  TextColumn get label => text().nullable()();
  DateTimeColumn get syncedAt => dateTime()();
}

/// Notifications cache.
@DataClassName('CachedNotification')
class CachedNotifications extends Table {
  TextColumn get id => text()();
  TextColumn get userId => text()();
  TextColumn get title => text()();
  TextColumn get body => text()();
  BoolColumn get isRead => boolean().withDefault(const Constant(false))();
  DateTimeColumn get createdAt => dateTime().nullable()();
  DateTimeColumn get syncedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Fee summary cache.
class FeeSummaryCache extends Table {
  TextColumn get userId => text()();
  TextColumn get billed => text()();
  TextColumn get paid => text()();
  TextColumn get remaining => text()();
  IntColumn get countOverdue => integer().withDefault(const Constant(0))();
  IntColumn get countPartial => integer().withDefault(const Constant(0))();
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {userId};
}

/// Payment entries cache.
@DataClassName('PaymentEntryRow')
class PaymentEntries extends Table {
  TextColumn get id => text()();
  TextColumn get studentName => text()();
  TextColumn get amount => text()();
  TextColumn get classroom => text().nullable()();
  TextColumn get method => text().nullable()();
  TextColumn get invoiceNumber => text().nullable()();
  TextColumn get statusLabel => text().nullable()();
  TextColumn get paidAt => text().nullable()();
  BoolColumn get canVerify => boolean().withDefault(const Constant(false))();
  DateTimeColumn get syncedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

// ============================================================================
// TRANSACTION QUEUES
// ============================================================================

/// Sync status enum stored as text.
class SyncStatusConverter extends TypeConverter<SyncStatus, String> {
  const SyncStatusConverter();

  @override
  SyncStatus fromSql(String fromDb) {
    return SyncStatus.values.firstWhere(
      (e) => e.name == fromDb,
      orElse: () => SyncStatus.pending,
    );
  }

  @override
  String toSql(SyncStatus value) => value.name;
}

enum SyncStatus {
  pending,
  syncing,
  synced,
  failed,
}

/// QR scan queue - replaces SharedPreferences storage.
@DataClassName('QueuedScanRow')
class QueuedScans extends Table {
  TextColumn get id => text()();
  TextColumn get uniqueCode => text()();
  TextColumn get waktu => text()(); // masuk | pulang
  DateTimeColumn get scannedAt => dateTime()();
  RealColumn get latitude => real().nullable()();
  RealColumn get longitude => real().nullable()();
  TextColumn get syncStatus =>
      text().map(const SyncStatusConverter()).withDefault(
            const Constant('pending'),
          )();
  TextColumn get syncError => text().nullable()();
  IntColumn get syncAttempts => integer().withDefault(const Constant(0))();

  @override
  Set<Column> get primaryKey => {id};
}

/// Class attendance queue - replaces SharedPreferences storage.
@DataClassName('QueuedClassAttendanceRow')
class QueuedClassAttendances extends Table {
  TextColumn get id => text()();
  TextColumn get classroomId => text()();
  TextColumn get classroomName => text()();
  DateTimeColumn get date => dateTime()();
  TextColumn get marksJson => text()(); // JSON: {studentId: status}
  DateTimeColumn get savedAt => dateTime()();
  TextColumn get syncStatus =>
      text().map(const SyncStatusConverter()).withDefault(
            const Constant('pending'),
          )();
  TextColumn get syncError => text().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Pending actions (notification read, payment verify).
class PendingActions extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get actionType => text()(); // notification_read, payment_verify
  TextColumn get payloadJson => text()();
  DateTimeColumn get createdAt => dateTime()();
  TextColumn get syncStatus =>
      text().map(const SyncStatusConverter()).withDefault(
            const Constant('pending'),
          )();
  TextColumn get syncError => text().nullable()();
  IntColumn get syncAttempts => integer().withDefault(const Constant(0))();
}

// ============================================================================
// SYNC METADATA
// ============================================================================

/// Tracks last sync time per entity type.
class SyncMetadata extends Table {
  TextColumn get entityType => text()();
  DateTimeColumn get lastSyncedAt => dateTime().nullable()();
  DateTimeColumn get lastFullSyncAt => dateTime().nullable()();
  TextColumn get syncCursor => text().nullable()();

  @override
  Set<Column> get primaryKey => {entityType};
}

// ============================================================================
// LOCAL AUTHENTICATION (Independent from backend)
// ============================================================================

/// Local user accounts - fully independent from backend.
/// Users can create accounts and login without any server connection.
@DataClassName('LocalAccount')
class LocalAccounts extends Table {
  TextColumn get id => text()();
  TextColumn get email => text().unique()();
  TextColumn get fullName => text()();
  TextColumn get passwordHash => text()(); // Base64 encoded PBKDF2
  TextColumn get passwordSalt => text()(); // Base64 encoded
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Backend connection info - from QR scan.
/// Links local app to a school's backend for sync.
@DataClassName('BackendConnectionData')
class BackendConnection extends Table {
  IntColumn get id => integer().withDefault(const Constant(1))();
  TextColumn get apiUrl => text()();
  TextColumn get syncToken => text()();
  TextColumn get schoolName => text().nullable()();
  TextColumn get backendUserId => text().nullable()();
  TextColumn get backendUserName => text().nullable()();
  TextColumn get backendUserEmail => text().nullable()();
  TextColumn get permissionsJson => text().withDefault(const Constant('[]'))();
  DateTimeColumn get connectedAt => dateTime()();
  DateTimeColumn get lastSyncAt => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}
