import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/backend_status.dart';
import '../sync/sync_providers.dart';
import '../sync/sync_result.dart';

/// Banner showing sync status and offline indicator.
///
/// Place at top of scaffold body to show:
/// - Offline indicator when device is disconnected
/// - Sync progress when syncing
/// - Sync result summary after sync completes
class SyncStatusBanner extends ConsumerWidget {
  const SyncStatusBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final backendStatus = ref.watch(backendStatusProvider);
    final syncProgress = ref.watch(syncServiceProvider);

    // Offline banner takes priority.
    if (backendStatus == BackendStatus.offline) {
      return _OfflineBanner();
    }

    // Show sync progress.
    if (syncProgress.state == SyncState.syncing) {
      return _SyncingBanner(phase: syncProgress.currentPhase);
    }

    // Show sync result briefly after completion.
    if (syncProgress.state == SyncState.synced &&
        syncProgress.lastSyncAt != null) {
      final elapsed = DateTime.now().difference(syncProgress.lastSyncAt!);
      if (elapsed < const Duration(seconds: 5) &&
          syncProgress.lastResult?.hasSynced == true) {
        return _SyncedBanner(result: syncProgress.lastResult!);
      }
    }

    return const SizedBox.shrink();
  }
}

class _OfflineBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: Colors.orange.shade100,
      child: Row(
        children: [
          Icon(Icons.cloud_off, size: 18, color: Colors.orange.shade800),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Tidak ada koneksi. Data tersimpan lokal.',
              style: TextStyle(
                fontSize: 13,
                color: Colors.orange.shade900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SyncingBanner extends StatelessWidget {
  const _SyncingBanner({this.phase});

  final String? phase;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: Colors.blue.shade50,
      child: Row(
        children: [
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              phase ?? 'Menyinkronkan...',
              style: TextStyle(
                fontSize: 13,
                color: Colors.blue.shade900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SyncedBanner extends StatelessWidget {
  const _SyncedBanner({required this.result});

  final SyncResult result;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: Colors.green.shade50,
      child: Row(
        children: [
          Icon(Icons.check_circle, size: 18, color: Colors.green.shade700),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Berhasil menyinkronkan ${result.totalSynced} item',
              style: TextStyle(
                fontSize: 13,
                color: Colors.green.shade900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Compact offline indicator dot for app bar.
class OfflineIndicatorDot extends ConsumerWidget {
  const OfflineIndicatorDot({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(backendStatusProvider);

    if (status == BackendStatus.online) {
      return const SizedBox.shrink();
    }

    return Container(
      width: 10,
      height: 10,
      margin: const EdgeInsets.only(right: 8),
      decoration: BoxDecoration(
        color: status == BackendStatus.offline ? Colors.orange : Colors.grey,
        shape: BoxShape.circle,
      ),
    );
  }
}
