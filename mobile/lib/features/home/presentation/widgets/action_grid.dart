import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/shortcut_store.dart';
import '../../models/action_item.dart';
import 'shortcut_sheet.dart';

/// Grid aksi datar — ikon dalam ubin lembut, tanpa bingkai kartu.
///
/// Menampilkan maksimal [ShortcutController.maxVisible] pintasan plus satu
/// ubin "Semua menu", sehingga tinggi Beranda tetap sama berapa pun menu
/// bertambah di katalog.
class ActionGrid extends ConsumerWidget {
  const ActionGrid({super.key, required this.actions});

  /// Aksi yang boleh dilihat user (sudah tersaring izin).
  final List<ActionItem> actions;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scheme = Theme.of(context).colorScheme;
    final pinned = ref.watch(shortcutControllerProvider);
    final shown = visibleShortcuts(actions, pinned);

    if (actions.isEmpty) {
      return Text(
        'Belum ada aksi yang tersedia untuk akun Anda. '
        'Hubungi administrator bila ini tidak sesuai.',
        style: TextStyle(fontSize: 12.5, color: scheme.onSurfaceVariant),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('AKSI',
            style: TextStyle(
              fontSize: 10,
              letterSpacing: 1.3,
              fontWeight: FontWeight.w700,
              color: scheme.onSurfaceVariant,
            )),
        const SizedBox(height: 12),
        GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: 4,
          mainAxisSpacing: 14,
          crossAxisSpacing: 6,
          childAspectRatio: 0.82,
          children: [
            for (final a in shown)
              _Tile(
                icon: a.icon,
                label: a.title,
                enabled: a.isAvailable,
                onTap: a.route == null ? null : () => context.push(a.route!),
              ),
            _Tile(
              icon: Icons.apps_rounded,
              label: 'Semua menu',
              enabled: true,
              muted: true,
              onTap: () => showShortcutSheet(context, actions),
            ),
          ],
        ),
      ],
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({
    required this.icon,
    required this.label,
    required this.enabled,
    this.onTap,
    this.muted = false,
  });

  final IconData icon;
  final String label;
  final bool enabled;
  final bool muted;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final active = enabled && onTap != null;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: active && !muted
                  ? scheme.primaryContainer
                  : scheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              icon,
              size: 21,
              color: active && !muted
                  ? scheme.onPrimaryContainer
                  : scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 9.5,
              height: 1.25,
              color: active ? scheme.onSurface : scheme.onSurfaceVariant,
            ),
          ),
          if (!enabled)
            Text('Segera',
                style: TextStyle(
                    fontSize: 8.5, color: scheme.onSurfaceVariant)),
        ],
      ),
    );
  }
}
