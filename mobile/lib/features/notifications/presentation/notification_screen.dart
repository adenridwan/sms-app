import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/ui_kit.dart';
import '../models/app_notification.dart';
import 'notification_controller.dart';

/// Tab Notifikasi.
///
/// Rujukan desain menampilkan daftar sebagai kartu putih terpisah, bukan
/// [ListTile] berikon dengan garis pemisah: yang belum dibaca ditandai titik
/// aksen kecil dan judul tebal — tanpa ikon lonceng yang mengulang konteks tab.
class NotificationScreen extends ConsumerWidget {
  const NotificationScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationControllerProvider);
    final notifier = ref.read(notificationControllerProvider.notifier);

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
          const SizedBox(width: 20),
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
                children: [
                  const SizedBox(height: 40),
                  EmptyNote(
                    title: state.error == null
                        ? 'Belum ada notifikasi'
                        : 'Tidak bisa memuat',
                    body: state.error ??
                        'Pemberitahuan absensi, izin, dan pengumuman akan '
                            'muncul di sini.',
                  ),
                ],
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
              itemCount: state.items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) => _NotificationTile(
                item: state.items[i],
                onTap: state.items[i].isRead
                    ? null
                    : () => notifier.markRead(state.items[i].id),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.item, this.onTap});

  final AppNotification item;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final unread = !item.isRead;

    return Panel(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Titik aksen menggantikan ikon: cukup untuk membedakan belum/sudah
          // dibaca tanpa menambah bobot visual pada tiap baris.
          Container(
            margin: const EdgeInsets.only(top: 5, right: 12),
            width: 7,
            height: 7,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: unread ? scheme.primary : scheme.outlineVariant,
            ),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title.isEmpty ? '(tanpa judul)' : item.title,
                  style: TextStyle(
                    fontSize: 14,
                    letterSpacing: -.2,
                    fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
                    color: unread ? scheme.onSurface : scheme.onSurfaceVariant,
                  ),
                ),
                if (item.body.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    item.body,
                    style: TextStyle(
                      fontSize: 12,
                      height: 1.4,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
