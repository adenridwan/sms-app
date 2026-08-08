import 'package:flutter/material.dart';

/// Judul bagian dengan teks pelengkap di kanan.
///
/// Menggantikan label huruf-kapital-kecil sebelumnya: pada rujukan desain,
/// judul bagian berukuran normal dan tebal, dengan tautan/keterangan di ujung
/// kanan pada garis dasar yang sama.
class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.trailing,
    this.onTrailingTap,
  });

  final String title;
  final String? trailing;
  final VoidCallback? onTrailingTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Flexible(
          child: Text(
            title,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              letterSpacing: -.25,
            ),
          ),
        ),
        if (trailing != null)
          GestureDetector(
            onTap: onTrailingTap,
            child: Text(
              trailing!,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: onTrailingTap == null
                    ? scheme.onSurfaceVariant
                    : scheme.primary,
              ),
            ),
          ),
      ],
    );
  }
}
