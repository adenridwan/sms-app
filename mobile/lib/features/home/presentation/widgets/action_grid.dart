import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../models/action_item.dart';

/// Grid aksi dengan ikon modern — 5 menu shortcut ditampilkan dalam grid
/// responsif dengan ikon bulat berwarna di atas label.
///
/// Layout: 5 item dalam grid, baris pertama 3 kolom, baris kedua 2 kolom
/// (centered).
class ActionGrid extends StatelessWidget {
  const ActionGrid({super.key, required this.actions});

  /// Aksi yang boleh dilihat user (sudah tersaring izin peran).
  final List<ActionItem> actions;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    if (actions.isEmpty) {
      return Text(
        'Belum ada aksi yang tersedia untuk akun Anda. '
        'Hubungi administrator bila ini tidak sesuai.',
        style: TextStyle(fontSize: 12.5, color: scheme.onSurfaceVariant),
      );
    }

    // Responsive: gunakan wrap untuk layout yang lebih fleksibel
    return Wrap(
      spacing: 12,
      runSpacing: 16,
      alignment: WrapAlignment.center,
      children: [
        for (final a in actions)
          _ActionIconCard(
            title: a.title,
            caption: a.caption,
            icon: a.icon,
            enabled: a.isAvailable,
            onTap: a.route == null ? null : () => context.push(a.route!),
          ),
      ],
    );
  }
}

/// Kartu aksi dengan ikon bulat berwarna modern.
class _ActionIconCard extends StatelessWidget {
  const _ActionIconCard({
    required this.title,
    required this.caption,
    required this.icon,
    required this.enabled,
    this.onTap,
  });

  final String title;
  final String caption;
  final IconData icon;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final active = enabled && onTap != null;

    // Lebar kartu responsif: sekitar 1/3 layar dengan spacing
    final screenWidth = MediaQuery.of(context).size.width;
    final cardWidth = (screenWidth - 40 - 24) / 3; // 40 = padding, 24 = spacing

    return SizedBox(
      width: cardWidth.clamp(90.0, 110.0),
      child: Opacity(
        opacity: active ? 1 : .5,
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(16),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Icon container dengan gradient modern
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: active
                            ? [
                                scheme.primary,
                                scheme.primary.withValues(alpha: 0.8),
                              ]
                            : [
                                scheme.surfaceContainerHighest,
                                scheme.surfaceContainerHighest,
                              ],
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: active
                          ? [
                              BoxShadow(
                                color: scheme.primary.withValues(alpha: 0.3),
                                blurRadius: 12,
                                offset: const Offset(0, 4),
                              ),
                            ]
                          : null,
                    ),
                    child: Icon(
                      icon,
                      size: 26,
                      color: active ? Colors.white : scheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 10),
                  // Title
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      letterSpacing: -.1,
                      color: scheme.onSurface,
                    ),
                  ),
                  const SizedBox(height: 2),
                  // Caption
                  Text(
                    caption,
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 10,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
