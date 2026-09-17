import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/database/sources/sources.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/providers.dart';
import '../../home/data/dashboard_repository.dart';
import '../models/app_notification.dart';

/// Akses `GET/POST /notifications` dengan offline-first pattern.
class NotificationRepository {
  NotificationRepository({
    required this.dio,
    required this.local,
    required this.ref,
  });

  final Dio dio;
  final NotificationLocalSource local;
  final Ref ref;

  bool get _isOnline => ref.read(backendStatusProvider) == BackendStatus.online;

  /// List notifications with offline-first pattern.
  Future<List<AppNotification>> list(String userId) async {
    // Try cache first.
    final cached = await local.getNotifications(userId);

    if (cached.isNotEmpty) {
      // Have cache - refresh in background if stale.
      if (_isOnline) {
        final isStale = await local.isStale(userId);
        if (isStale) {
          _refreshFromServer(userId);
        }
      }
      return cached;
    }

    // No cache - try to fetch.
    if (!_isOnline) {
      return const []; // Return empty list if offline.
    }

    return await _fetchAndCache(userId);
  }

  /// Force refresh from server.
  Future<List<AppNotification>> refresh(String userId) async {
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak dapat memuat ulang saat offline.',
        isNetwork: true,
      );
    }

    return await _fetchAndCache(userId);
  }

  /// Mark notification as read.
  ///
  /// Updates local cache immediately (optimistic) and queues API call.
  Future<void> markRead(String id, String userId) async {
    // Optimistic update - mark as read locally first.
    await local.markAsRead(id);

    if (_isOnline) {
      // Try to sync immediately.
      try {
        await dio.post('/notifications/$id/read');
      } catch (_) {
        // If fails, queue for later sync.
        await local.queueMarkAsRead(id);
      }
    } else {
      // Queue for sync when online.
      await local.queueMarkAsRead(id);
    }
  }

  /// Mark all notifications as read.
  Future<void> markAllRead(String userId) async {
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak dapat menandai saat offline.',
        isNetwork: true,
      );
    }

    try {
      await dio.post('/notifications/read-all');
      // Refresh cache to get updated read status.
      await _fetchAndCache(userId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Get unread count.
  Future<int> getUnreadCount(String userId) async {
    return await local.getUnreadCount(userId);
  }

  Future<List<AppNotification>> _fetchAndCache(String userId) async {
    try {
      final res = await dio.get('/notifications');
      final payload = (res.data as Map)['data'];
      final items = payload is Map ? payload['data'] : payload;
      if (items is! List) return const [];

      final notifications = items
          .whereType<Map>()
          .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      await local.saveNotifications(userId, notifications);
      return notifications;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  void _refreshFromServer(String userId) {
    _fetchAndCache(userId).ignore();
  }
}

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(
    dio: ref.watch(dioProvider),
    local: ref.watch(notificationLocalSourceProvider),
    ref: ref,
  ),
);

/// Notifications for current user.
final notificationsFamily =
    FutureProvider.autoDispose.family<List<AppNotification>, String?>(
  (ref, userId) {
    if (userId == null) return Future.value(const []);
    return ref.watch(notificationRepositoryProvider).list(userId);
  },
);

/// What the screen watches.
final notificationsProvider =
    Provider.autoDispose<AsyncValue<List<AppNotification>>>(
  (ref) => ref.watch(notificationsFamily(ref.watch(currentUserIdProvider))),
);

/// Unread count for badge.
final unreadNotificationCountProvider = FutureProvider.autoDispose<int>((ref) {
  final userId = ref.watch(currentUserIdProvider);
  if (userId == null) return 0;
  return ref.watch(notificationLocalSourceProvider).getUnreadCount(userId);
});

/// Refresh notifications.
void invalidateNotifications(WidgetRef ref) =>
    ref.invalidate(notificationsFamily(ref.read(currentUserIdProvider)));
