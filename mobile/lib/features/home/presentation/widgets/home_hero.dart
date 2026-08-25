import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

/// Kepala Beranda: tanggal, sapaan, dan lencana peran.
///
/// Berbeda dari versi sebelumnya yang memakai bidang biru bergradien — rujukan
/// desain bergaya editorial: latar tetap terang, hierarki dibangun dari ukuran
/// dan bobot huruf, bukan dari blok warna.
class HomeHero extends StatelessWidget {
  const HomeHero({
    super.key,
    required this.greeting,
    required this.roleLabel,
    this.dateLabel,
    this.schoolName,
  });

  /// Mis. "Selamat pagi, Sari".
  final String greeting;

  /// Peran **dari akun yang login**, bukan pilihan pengguna.
  final String roleLabel;

  final String? dateLabel;
  final String? schoolName;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final chipText = [roleLabel, if (schoolName != null) schoolName!]
        .where((e) => e.isNotEmpty)
        .join(' · ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (dateLabel != null)
          Text(dateLabel!,
              style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant)),
        const SizedBox(height: 2),
        Text(
          greeting,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        if (chipText.isNotEmpty) ...[
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
            decoration: BoxDecoration(
              color: scheme.primaryContainer,
              borderRadius: BorderRadius.circular(999),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: scheme.onPrimaryContainer,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  chipText,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: scheme.onPrimaryContainer,
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// Kartu ringkasan gelap dengan tiga angka besar.
///
/// Satu-satunya bidang gelap di layar — itulah yang membuatnya jadi pusat
/// perhatian tanpa perlu warna aksen.
class SummaryCard extends StatelessWidget {
  const SummaryCard({
    super.key,
    required this.title,
    required this.figures,
  });

  final String title;
  final List<SummaryFigure> figures;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final onDark = scheme.surface;
    final mutedOnDark = onDark.withValues(alpha: .62);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: scheme.onSurface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title.toUpperCase(),
            style: TextStyle(
              fontSize: 11,
              letterSpacing: 1.1,
              fontWeight: FontWeight.w600,
              color: mutedOnDark,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              for (var i = 0; i < figures.length; i++) ...[
                if (i > 0) const SizedBox(width: 22),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      figures[i].value,
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.6,
                        color: figures[i].tint ?? onDark,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      figures[i].label,
                      style: TextStyle(fontSize: 11, color: mutedOnDark),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class SummaryFigure {
  const SummaryFigure({required this.value, required this.label, this.tint});

  final String value;
  final String label;

  /// Aksen dipakai untuk angka yang perlu perhatian (terlambat / tidak hadir).
  final Color? tint;

  /// Nuansa aksen di atas bidang gelap, seperti pada rujukan.
  static Color get warn => AppTheme.accentSoft;
  static Color get bad => AppTheme.accent200;
}
