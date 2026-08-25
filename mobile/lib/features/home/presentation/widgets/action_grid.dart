import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../models/action_item.dart';

/// Grid aksi 2 kolom bergaya rujukan: kartu datar, **tanpa ikon**, judul tebal
/// dengan satu baris penjelas di bawahnya.
///
/// Ikon sengaja ditinggalkan — pada rujukan, pembeda antar kartu adalah kata,
/// bukan simbol. Itu membuat kartu terbaca lebih cepat dan tidak menuntut
/// ikon baru setiap kali modul bertambah.
///
/// Seluruh aksi peran ditampilkan apa adanya, tanpa penyaring pintasan:
/// rujukan hanya punya empat kartu per peran, jadi menyembunyikan sebagian di
/// balik lembar "Semua menu" justru menambah langkah tanpa menghemat ruang.
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

    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 1.75,
      children: [
        for (final a in actions)
          _ActionCard(
            title: a.title,
            caption: a.caption,
            enabled: a.isAvailable,
            onTap: a.route == null ? null : () => context.push(a.route!),
          ),
      ],
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.title,
    required this.caption,
    required this.enabled,
    this.onTap,
  });

  final String title;
  final String caption;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final active = enabled && onTap != null;

    return Material(
      color: scheme.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: SizedBox.expand(
          child: Opacity(
            opacity: active ? 1 : .5,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.2,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    caption,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 11,
                      height: 1.3,
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
