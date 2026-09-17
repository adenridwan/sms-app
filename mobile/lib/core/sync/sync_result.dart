/// Result of a sync operation.
class SyncResult {
  const SyncResult({
    required this.success,
    this.syncedScans = 0,
    this.syncedClassAttendances = 0,
    this.syncedActions = 0,
    this.failedScans = 0,
    this.failedClassAttendances = 0,
    this.failedActions = 0,
    this.error,
  });

  final bool success;
  final int syncedScans;
  final int syncedClassAttendances;
  final int syncedActions;
  final int failedScans;
  final int failedClassAttendances;
  final int failedActions;
  final String? error;

  int get totalSynced => syncedScans + syncedClassAttendances + syncedActions;
  int get totalFailed => failedScans + failedClassAttendances + failedActions;

  bool get hasSynced => totalSynced > 0;
  bool get hasFailed => totalFailed > 0;

  /// Merge multiple results into one.
  SyncResult merge(SyncResult other) {
    return SyncResult(
      success: success && other.success,
      syncedScans: syncedScans + other.syncedScans,
      syncedClassAttendances:
          syncedClassAttendances + other.syncedClassAttendances,
      syncedActions: syncedActions + other.syncedActions,
      failedScans: failedScans + other.failedScans,
      failedClassAttendances:
          failedClassAttendances + other.failedClassAttendances,
      failedActions: failedActions + other.failedActions,
      error: error ?? other.error,
    );
  }

  static const empty = SyncResult(success: true);
}

/// Current state of the sync service.
enum SyncState {
  /// Idle, no sync in progress.
  idle,

  /// Sync in progress.
  syncing,

  /// Last sync completed successfully.
  synced,

  /// Last sync failed.
  failed,
}

/// Sync progress for UI feedback.
class SyncProgress {
  const SyncProgress({
    required this.state,
    this.currentPhase,
    this.lastSyncAt,
    this.lastResult,
  });

  final SyncState state;
  final String? currentPhase;
  final DateTime? lastSyncAt;
  final SyncResult? lastResult;

  static const initial = SyncProgress(state: SyncState.idle);

  SyncProgress copyWith({
    SyncState? state,
    String? currentPhase,
    DateTime? lastSyncAt,
    SyncResult? lastResult,
  }) {
    return SyncProgress(
      state: state ?? this.state,
      currentPhase: currentPhase ?? this.currentPhase,
      lastSyncAt: lastSyncAt ?? this.lastSyncAt,
      lastResult: lastResult ?? this.lastResult,
    );
  }
}
