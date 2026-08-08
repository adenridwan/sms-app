import 'package:flutter/material.dart';

import 'attendance_bar.dart' show kAttendanceColors, kAttendanceLabels;

/// Empat ubin ringkasan bertinta lembut — angka besar + label kecil.
///
/// Ini menggantikan legenda teks: proporsi memang tetap perlu dibaca, tapi
/// angka utamanya kini terbaca dari jarak pandang normal, bukan dengan
/// menyipitkan mata ke baris "Hadir 486 · Sakit 12 · …".
class StatChips extends StatelessWidget {
  const StatChips({super.key, required this.summary});

  /// `{hadir: n, sakit: n, izin: n, alfa: n, belum_scan: n}`.
  final Map<String, int> summary;

  @override
  Widget build(BuildContext context) {
    // Urutan tetap agar posisinya tidak berpindah antar-muat.
    const order = ['hadir', 'izin', 'sakit', 'alfa'];
    final entries = [
      for (final k in order)
        if (summary.containsKey(k)) MapEntry(k, summary[k]!),
    ];

    if (entries.isEmpty) return const SizedBox.shrink();

    return Row(
      children: [
        for (var i = 0; i < entries.length; i++) ...[
          if (i > 0) const SizedBox(width: 8),
          Expanded(
            child: _Chip(
              value: entries[i].value,
              label: kAttendanceLabels[entries[i].key] ?? entries[i].key,
              color: kAttendanceColors[entries[i].key] ?? Colors.grey,
            ),
          ),
        ],
      ],
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 13),
      decoration: BoxDecoration(
        color: color.withValues(alpha: dark ? .18 : .12),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        children: [
          Text(
            '$value',
            style: TextStyle(
              color: color,
              fontSize: 20,
              fontWeight: FontWeight.w700,
              letterSpacing: -.5,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
          const SizedBox(height: 1),
          Text(
            label.toUpperCase(),
            style: TextStyle(
              color: color.withValues(alpha: dark ? .85 : .9),
              fontSize: 9,
              fontWeight: FontWeight.w700,
              letterSpacing: .7,
            ),
          ),
        ],
      ),
    );
  }
}
