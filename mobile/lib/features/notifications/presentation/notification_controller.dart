import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../home/data/dashboard_repository.dart';
import '../data/notification_repository.dart';
import '../models/app_notification.dart';

class NotificationState {
  const NotificationState({
    this.items = const [],
    this.loading = true,
    this.error,
  });

  final List<AppNotification> items;
  final bool loading;
  final String? error;

  int get unreadCount => items.where((e) => !e.isRead).length;
}

class NotificationController extends StateNotifier<NotificationState> {
  NotificationController(this._repo, this._userId)
      : super(const NotificationState()) {
    load();
  }

  final NotificationRepository _repo;
  final String? _userId;

  Future<void> load() async {
    if (_userId == null) {
      state = const NotificationState(loading: false);
      return;
    }

    state = NotificationState(items: state.items, loading: true);
    try {
      state = NotificationState(
        items: await _repo.list(_userId),
        loading: false,
      );
    } on ApiException catch (e) {
      state = NotificationState(
        items: state.items,
        loading: false,
        error: e.message,
      );
    }
  }

  /// Tandai satu notifikasi terbaca. State diperbarui optimistis; kegagalan
  /// jaringan tidak membalikkan karena kita sudah queue action untuk sync.
  Future<void> markRead(String id) async {
    if (_userId == null) return;

    final before = state.items;
    state = NotificationState(
      items: [
        for (final n in before) n.id == id ? n.copyWith(isRead: true) : n,
      ],
      loading: false,
    );
    try {
      await _repo.markRead(id, _userId);
    } on ApiException {
      // Don't revert - we've queued the action for later sync.
    }
  }

  Future<void> markAllRead() async {
    if (_userId == null) return;

    final before = state.items;
    state = NotificationState(
      items: [for (final n in before) n.copyWith(isRead: true)],
      loading: false,
    );
    try {
      await _repo.markAllRead(_userId);
    } on ApiException catch (e) {
      state = NotificationState(items: before, loading: false, error: e.message);
    }
  }
}

/// Bergantung pada id user agar notifikasi tidak terbawa antar akun saat
/// berganti login (lihat catatan serupa di `dashboardStatsProvider`).
final notificationControllerProvider =
    StateNotifierProvider<NotificationController, NotificationState>((ref) {
  final userId = ref.watch(currentUserIdProvider);
  return NotificationController(
    ref.watch(notificationRepositoryProvider),
    userId,
  );
});
