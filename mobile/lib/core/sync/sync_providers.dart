import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../database/database_providers.dart';
import '../network/backend_status.dart';
import '../providers.dart';
import 'sync_result.dart';
import 'sync_service.dart';

/// Sync service provider.
final syncServiceProvider =
    StateNotifierProvider<SyncService, SyncProgress>((ref) {
  final service = SyncService(
    dio: ref.watch(dioProvider),
    db: ref.watch(databaseProvider),
    scanQueue: ref.watch(scanQueueLocalSourceProvider),
    classAttendanceQueue: ref.watch(classAttendanceQueueLocalSourceProvider),
    ref: ref,
  );

  // Auto-sync when coming back online.
  ref.listen<BackendStatus>(
    backendStatusProvider,
    (previous, next) {
      if (previous == BackendStatus.offline && next == BackendStatus.online) {
        // Small delay to let network stabilize.
        Future.delayed(const Duration(milliseconds: 500), () {
          service.syncAll();
        });
      }
    },
  );

  return service;
});

/// Convenience method to trigger sync.
void triggerSync(Ref ref) {
  ref.read(syncServiceProvider.notifier).syncAll();
}

/// Check if sync is in progress.
final isSyncingProvider = Provider<bool>((ref) {
  final progress = ref.watch(syncServiceProvider);
  return progress.state == SyncState.syncing;
});

/// Last sync time for UI display.
final lastSyncTimeProvider = Provider<DateTime?>((ref) {
  return ref.watch(syncServiceProvider).lastSyncAt;
});
