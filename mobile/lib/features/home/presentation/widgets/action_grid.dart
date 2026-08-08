import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/shortcut_store.dart';
import '../../models/action_item.dart';
import 'shortcut_sheet.dart';

/// Kartu aksi 2 kolom. Kartu **pertama** diisi warna aksen sebagai tugas
/// utama; sisanya putih dengan ikon bertinta — hierarki terbaca sekali lihat,
/// tanpa perlu membaca label satu per satu.
class ActionGrid extends ConsumerWidget {
  const ActionGrid({super.key, required this.actions, this.primaryKey});

  /// Aksi yang boleh dilihat user (sudah tersaring izin).
  final List<ActionItem> actions;

  /// Kunci aksi yang ditonjolkan (kartu biru). Bila null, yang pertama.
  final String? primaryKey;

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

    // Ubin "Semua menu" selalu menutup daftar, sehingga tinggi Beranda tidak
    // ikut tumbuh saat katalog menu bertambah.
    final tiles = <Widget>[
      for (final a in shown)
        _ActionCard(
          action: a,
          primary: primaryKey == null
              ? a.key == shown.first.key
              : a.key == primaryKey,
          onTap: a.route == null ? null : () => context.push(a.route!),
        ),
      _MoreCard(onTap: () => showShortcutSheet(context, actions)),
    ];

    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 11,
      crossAxisSpacing: 11,
      childAspectRatio: 1.62,
      children: tiles,
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.action,
    required this.primary,
    this.onTap,
  });

  final ActionItem action;
  final bool primary;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final enabled = action.isAvailable && onTap != null;
    final filled = primary && enabled;

    final bg = filled ? scheme.primary : scheme.surface;
    final fg = filled ? Colors.white : scheme.onSurface;
    final sub = filled
        ? Colors.white.withValues(alpha: .8)
        : scheme.onSurfaceVariant;

    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Opacity(
          opacity: enabled ? 1 : .55,
          child: Padding(
            padding: const EdgeInsets.all(13),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: filled
                        ? Colors.white.withValues(alpha: .22)
                        : action.tint.withValues(alpha: .14),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    action.icon,
                    size: 20,
                    color: filled ? Colors.white : action.tint,
                  ),
                ),
                const Spacer(),
                Text(
                  action.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: fg,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -.2,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  enabled ? action.caption : 'Segera hadir',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: sub, fontSize: 11.5),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MoreCard extends StatelessWidget {
  const _MoreCard({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: DottedBorderBox(
          child: Padding(
            padding: const EdgeInsets.all(13),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.apps_rounded,
                      size: 20, color: scheme.onSurfaceVariant),
                ),
                const Spacer(),
                Text('Semua menu',
                    style: TextStyle(
                      color: scheme.onSurface,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      letterSpacing: -.2,
                    )),
                const SizedBox(height: 1),
                Text('Atur pintasan',
                    style: TextStyle(
                        color: scheme.onSurfaceVariant, fontSize: 11.5)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Kotak bergaris putus-putus — menandai "ini bukan aksi, ini pintu ke daftar
/// penuh" tanpa perlu warna tambahan.
class DottedBorderBox extends StatelessWidget {
  const DottedBorderBox({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        color: scheme.surface.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant, width: 1.4),
      ),
      child: child,
    );
  }
}
