import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../database/database.dart';
import '../database/sources/sources.dart';
import '../database/tables.dart';
import '../network/api_exception.dart';
import '../network/backend_status.dart';
import 'sync_config.dart';
import 'sync_result.dart';

/// Centralized sync orchestrator for offline-first architecture.
///
/// Handles:
/// 1. Pushing local transactions (scans, class attendance) to server
/// 2. Pushing pending actions (notification read, payment verify)
/// 3. Pulling fresh data from server when cache is stale
///
/// Sync is triggered automatically when:
/// - Device comes back online
/// - User manually triggers refresh
/// - App enters foreground after being in background
class SyncService extends StateNotifier<SyncProgress> {
  SyncService({
    required this.dio,
    required this.db,
    required this.scanQueue,
    required this.classAttendanceQueue,
    required Ref ref,
  })  : _ref = ref,
        super(SyncProgress.initial);

  final Dio dio;
  final AppDatabase db;
  final ScanQueueLocalSource scanQueue;
  final ClassAttendanceQueueLocalSource classAttendanceQueue;
  final Ref _ref;

  bool _isSyncing = false;
  DateTime? _lastSyncAttempt;

  /// Check if sync is currently in progress.
  bool get isSyncing => _isSyncing;

  /// Check if we're online.
  bool get _isOnline =>
      _ref.read(backendStatusProvider) == BackendStatus.online;

  /// Full sync: push all pending transactions, then pull fresh data.
  Future<SyncResult> syncAll() async {
    if (_isSyncing) {
      return const SyncResult(
        success: false,
        error: 'Sync already in progress',
      );
    }

    // Cooldown check.
    if (_lastSyncAttempt != null) {
      final elapsed = DateTime.now().difference(_lastSyncAttempt!);
      if (elapsed < SyncConfig.syncCooldown) {
        return const SyncResult(
          success: false,
          error: 'Sync cooldown not elapsed',
        );
      }
    }

    if (!_isOnline) {
      return const SyncResult(
        success: false,
        error: 'Device is offline',
      );
    }

    _isSyncing = true;
    _lastSyncAttempt = DateTime.now();
    state = state.copyWith(state: SyncState.syncing);

    try {
      var result = SyncResult.empty;

      // Phase 1: Push transactions (most critical).
      state = state.copyWith(currentPhase: 'Mengirim scan...');
      result = result.merge(await _syncScans());

      state = state.copyWith(currentPhase: 'Mengirim absen kelas...');
      result = result.merge(await _syncClassAttendances());

      // Phase 2: Push pending actions.
      state = state.copyWith(currentPhase: 'Mengirim perubahan...');
      result = result.merge(await _syncPendingActions());

      // Phase 3: Clean up synced items.
      await scanQueue.removeSyncedScans();
      await classAttendanceQueue.removeSyncedAttendances();
      await db.removeSyncedActions();

      state = state.copyWith(
        state: result.hasFailed ? SyncState.failed : SyncState.synced,
        currentPhase: null,
        lastSyncAt: DateTime.now(),
        lastResult: result,
      );

      return result;
    } catch (e) {
      final error = e is DioException ? ApiException.fromDio(e).message : '$e';
      state = state.copyWith(
        state: SyncState.failed,
        currentPhase: null,
        lastResult: SyncResult(success: false, error: error),
      );
      return SyncResult(success: false, error: error);
    } finally {
      _isSyncing = false;
    }
  }

  /// Sync only pending scans.
  Future<SyncResult> syncScansOnly() async {
    if (!_isOnline || _isSyncing) {
      return const SyncResult(success: false, error: 'Cannot sync now');
    }

    _isSyncing = true;
    try {
      return await _syncScans();
    } finally {
      _isSyncing = false;
    }
  }

  /// Sync only pending class attendances.
  Future<SyncResult> syncClassAttendancesOnly() async {
    if (!_isOnline || _isSyncing) {
      return const SyncResult(success: false, error: 'Cannot sync now');
    }

    _isSyncing = true;
    try {
      return await _syncClassAttendances();
    } finally {
      _isSyncing = false;
    }
  }

  Future<SyncResult> _syncScans() async {
    final pending = await scanQueue.getPendingScans();
    if (pending.isEmpty) return SyncResult.empty;

    final ids = pending.map((s) => s.id).toSet();
    await scanQueue.markAsSyncing(ids);

    try {
      final payload = pending.map((s) => s.toSyncJson()).toList();

      await dio.post(
        '/scan/sync-offline',
        data: {'scans': payload},
      );

      await scanQueue.markAsSynced(ids);
      return SyncResult(success: true, syncedScans: pending.length);
    } catch (e) {
      final error = e is DioException ? ApiException.fromDio(e).message : '$e';
      await scanQueue.markAsFailed(ids, error);
      return SyncResult(
        success: false,
        failedScans: pending.length,
        error: error,
      );
    }
  }

  Future<SyncResult> _syncClassAttendances() async {
    final pending = await classAttendanceQueue.getPendingAttendances();
    if (pending.isEmpty) return SyncResult.empty;

    var synced = 0;
    var failed = 0;
    String? lastError;

    // Process one at a time (different classrooms, different endpoints).
    for (final item in pending) {
      try {
        await classAttendanceQueue.markAsSyncing({item.id});

        // Build payload for bulk attendance API.
        final payload = {
          'classroom_id': item.classroomId,
          'date': _fmtDate(item.date),
          'attendance': [
            for (final e in item.marks.entries)
              {'student_id': e.key, 'status': e.value.slug},
          ],
        };

        await dio.post('/attendance/students/bulk', data: payload);
        await classAttendanceQueue.markAsSynced({item.id});
        synced++;
      } catch (e) {
        final error = e is DioException ? ApiException.fromDio(e).message : '$e';
        await classAttendanceQueue.markAsFailed({item.id}, error);
        lastError = error;
        failed++;

        // If network error, stop trying more items.
        if (e is DioException && ApiException.isNetworkError(e)) {
          break;
        }
      }
    }

    return SyncResult(
      success: failed == 0,
      syncedClassAttendances: synced,
      failedClassAttendances: failed,
      error: lastError,
    );
  }

  Future<SyncResult> _syncPendingActions() async {
    final pending = await db.getPendingActions();
    if (pending.isEmpty) return SyncResult.empty;

    var synced = 0;
    var failed = 0;
    String? lastError;

    for (final action in pending) {
      try {
        await db.updatePendingActionStatus(action.id, SyncStatus.syncing);

        // Dispatch based on action type.
        switch (action.actionType) {
          case 'notification_read':
            await _syncNotificationRead(action);
            break;
          case 'payment_verify':
            await _syncPaymentVerify(action);
            break;
          default:
            // Unknown action type, just mark as synced.
            break;
        }

        await db.updatePendingActionStatus(action.id, SyncStatus.synced);
        synced++;
      } catch (e) {
        final error = e is DioException ? ApiException.fromDio(e).message : '$e';
        await db.updatePendingActionStatus(
          action.id,
          SyncStatus.failed,
          error: error,
        );
        lastError = error;
        failed++;

        // If network error, stop trying more items.
        if (e is DioException && ApiException.isNetworkError(e)) {
          break;
        }
      }
    }

    return SyncResult(
      success: failed == 0,
      syncedActions: synced,
      failedActions: failed,
      error: lastError,
    );
  }

  Future<void> _syncNotificationRead(PendingAction action) async {
    // Parse notification ID from payload.
    // Payload format: {"notification_id": "123"}
    final payload = action.payloadJson;
    final match = RegExp(r'"notification_id":\s*"([^"]+)"').firstMatch(payload);
    if (match == null) return;

    final notificationId = match.group(1);
    await dio.post('/notifications/$notificationId/read');
  }

  Future<void> _syncPaymentVerify(PendingAction action) async {
    // Parse payment ID from payload.
    // Payload format: {"payment_id": "123"}
    final payload = action.payloadJson;
    final match = RegExp(r'"payment_id":\s*"([^"]+)"').firstMatch(payload);
    if (match == null) return;

    final paymentId = match.group(1);
    await dio.post('/finance/payments/$paymentId/verify');
  }

  static String _fmtDate(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)}';
  }
}
