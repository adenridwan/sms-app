import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/backend_status_dot.dart';
import 'notification_controller.dart';

/// Tab Notifikasi: daftar notifikasi + tandai baca.
class NotificationScreen extends ConsumerWidget {
  const NotificationScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationControllerProvider);
    final notifier = ref.read(notificationControllerProvider.notifier);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifikasi'),
        actions: [
          if (state.unreadCount > 0)
            TextButton(
              onPressed: notifier.markAllRead,
              child: const Text('Tandai semua'),
            ),
          const Center(child: BackendStatusDot()),
          const SizedBox(width: 12),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: notifier.load,
        child: Builder(
          builder: (context) {
            if (state.loading && state.items.isEmpty) {
              return const Center(child: CircularProgressIndicator());
            }

            if (state.items.isEmpty) {
              return ListView(
                padding: const EdgeInsets.all(32),
                children: [
                  const SizedBox(height: 60),
                  Icon(Icons.notifications_none_rounded,
                      size: 48, color: scheme.onSurfaceVariant),
                  const SizedBox(height: 12),
                  Text(
                    state.error ?? 'Belum ada notifikasi.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: scheme.onSurfaceVariant),
                  ),
                ],
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: state.items.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, i) {
                final n = state.items[i];
                return ListTile(
                  leading: Icon(
                    n.isRead
                        ? Icons.notifications_none_rounded
                        : Icons.notifications_active_rounded,
                    color: n.isRead ? scheme.onSurfaceVariant : scheme.primary,
                  ),
                  title: Text(
                    n.title.isEmpty ? '(tanpa judul)' : n.title,
                    style: TextStyle(
                      fontWeight:
                          n.isRead ? FontWeight.normal : FontWeight.w600,
                    ),
                  ),
                  subtitle: n.body.isEmpty ? null : Text(n.body),
                  onTap: n.isRead ? null : () => notifier.markRead(n.id),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
