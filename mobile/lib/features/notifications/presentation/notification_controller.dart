import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
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
  NotificationController(this._repo) : super(const NotificationState()) {
    load();
  }

  final NotificationRepository _repo;

  Future<void> load() async {
    state = NotificationState(items: state.items, loading: true);
    try {
      state = NotificationState(items: await _repo.list(), loading: false);
    } on ApiException catch (e) {
      state = NotificationState(
        items: state.items,
        loading: false,
        error: e.message,
      );
    }
  }

  /// Tandai satu notifikasi terbaca. State diperbarui optimistis; kegagalan
  /// jaringan dikembalikan ke semula agar tidak menipu (badge harus jujur).
  Future<void> markRead(String id) async {
    final before = state.items;
    state = NotificationState(
      items: [
        for (final n in before) n.id == id ? n.copyWith(isRead: true) : n,
      ],
      loading: false,
    );
    try {
      await _repo.markRead(id);
    } on ApiException catch (e) {
      state = NotificationState(items: before, loading: false, error: e.message);
    }
  }

  Future<void> markAllRead() async {
    final before = state.items;
    state = NotificationState(
      items: [for (final n in before) n.copyWith(isRead: true)],
      loading: false,
    );
    try {
      await _repo.markAllRead();
    } on ApiException catch (e) {
      state = NotificationState(items: before, loading: false, error: e.message);
    }
  }
}

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(ref.watch(dioProvider)),
);

final notificationControllerProvider =
    StateNotifierProvider<NotificationController, NotificationState>(
  (ref) => NotificationController(ref.watch(notificationRepositoryProvider)),
);
