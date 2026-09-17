import 'package:drift/drift.dart';

import '../../../features/notifications/models/app_notification.dart';
import '../database.dart';

/// Local data source for notifications.
class NotificationLocalSource {
  NotificationLocalSource(this._db);

  final AppDatabase _db;

  /// Staleness threshold for notifications cache.
  static const staleDuration = Duration(minutes: 30);

  /// Get cached notifications for user.
  Future<List<AppNotification>> getNotifications(String userId) async {
    final rows = await _db.getNotifications(userId);
    return rows.map(_rowToNotification).toList();
  }

  /// Get unread notification count.
  Future<int> getUnreadCount(String userId) async {
    return await _db.getUnreadNotificationCount(userId);
  }

  /// Check if cache is stale.
  Future<bool> isStale(String userId) async {
    return await _db.isEntityStale('notifications_$userId', staleDuration);
  }

  /// Save notifications to cache.
  Future<void> saveNotifications(
    String userId,
    List<AppNotification> notifications,
  ) async {
    final companions = notifications.map((n) => CachedNotificationsCompanion(
          id: Value(n.id),
          userId: Value(userId),
          title: Value(n.title),
          body: Value(n.body),
          isRead: Value(n.isRead),
          createdAt: Value(n.createdAt),
          syncedAt: Value(DateTime.now()),
        ));

    await _db.upsertNotifications(companions.toList());

    // Update sync metadata.
    await _db.updateSyncMetadata(SyncMetadataCompanion(
      entityType: Value('notifications_$userId'),
      lastSyncedAt: Value(DateTime.now()),
    ));
  }

  /// Mark notification as read locally.
  /// Returns true if notification was found and updated.
  Future<bool> markAsRead(String notificationId) async {
    return await _db.markNotificationRead(notificationId);
  }

  /// Queue a "mark as read" action for sync.
  Future<void> queueMarkAsRead(String notificationId) async {
    await _db.addPendingAction(PendingActionsCompanion(
      actionType: const Value('notification_read'),
      payloadJson: Value('{"notification_id": "$notificationId"}'),
      createdAt: Value(DateTime.now()),
    ));
  }

  /// Clear notifications for user.
  Future<void> clearCache(String userId) async {
    await _db.clearNotifications(userId);
  }

  AppNotification _rowToNotification(CachedNotification row) {
    return AppNotification(
      id: row.id,
      title: row.title,
      body: row.body,
      isRead: row.isRead,
      createdAt: row.createdAt,
    );
  }
}
