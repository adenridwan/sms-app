import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/shortcut_store.dart';
import '../../models/action_item.dart';

/// Laci "Semua menu": daftar penuh aksi yang boleh dilihat user, sekaligus
/// tempat menyematkan pintasan ke Beranda.
Future<void> showShortcutSheet(
  BuildContext context,
  List<ActionItem> actions,
) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _ShortcutSheet(actions: actions),
  );
}

class _ShortcutSheet extends ConsumerWidget {
  const _ShortcutSheet({required this.actions});

  final List<ActionItem> actions;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scheme = Theme.of(context).colorScheme;
    final controller = ref.read(shortcutControllerProvider.notifier);
    final pinned = ref.watch(shortcutControllerProvider);
    final pinnedCount = pinned?.length ?? 0;
    final atLimit = pinnedCount >= ShortcutController.maxVisible;

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * .78,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 4),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Semua menu',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 2),
                        Text(
                          pinned == null
                              ? 'Ketuk bintang untuk menaikkan ke Beranda'
                              : '$pinnedCount dari ${ShortcutController.maxVisible} pintasan dipakai',
                          style: TextStyle(
                              fontSize: 11, color: scheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  if (pinned != null)
                    TextButton(
                      onPressed: controller.reset,
                      child: const Text('Bawaan'),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 4),
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                padding: const EdgeInsets.only(bottom: 8),
                itemCount: actions.length,
                separatorBuilder: (_, __) => Divider(
                  height: 1,
                  color: scheme.outlineVariant,
                  indent: 20,
                  endIndent: 20,
                ),
                itemBuilder: (context, i) {
                  final a = actions[i];
                  final isPinned = controller.isPinned(a.key);
                  // Batas hanya menghalangi penambahan; melepas selalu boleh.
                  final canPin = isPinned || !atLimit;

                  return ListTile(
                    onTap: a.route == null
                        ? null
                        : () {
                            Navigator.pop(context);
                            context.push(a.route!);
                          },
                    leading: Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: a.isAvailable
                            ? scheme.primaryContainer
                            : scheme.surfaceContainerHighest,
                        borderRadius: BorderRadius.circular(11),
                      ),
                      child: Icon(a.icon,
                          size: 19,
                          color: a.isAvailable
                              ? scheme.onPrimaryContainer
                              : scheme.onSurfaceVariant),
                    ),
                    title: Text(a.title,
                        style: TextStyle(
                          fontSize: 13.5,
                          color: a.isAvailable
                              ? scheme.onSurface
                              : scheme.onSurfaceVariant,
                        )),
                    subtitle: a.isAvailable
                        ? null
                        : Text('Belum tersedia',
                            style: TextStyle(
                                fontSize: 11, color: scheme.onSurfaceVariant)),
                    trailing: IconButton(
                      tooltip: isPinned
                          ? 'Lepas dari Beranda'
                          : canPin
                              ? 'Sematkan ke Beranda'
                              : 'Pintasan sudah penuh',
                      onPressed:
                          canPin ? () => controller.toggle(a.key) : null,
                      icon: Icon(
                        isPinned ? Icons.star_rounded : Icons.star_border_rounded,
                        size: 21,
                        color: isPinned
                            ? scheme.primary
                            : scheme.onSurfaceVariant.withValues(alpha: .5),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
