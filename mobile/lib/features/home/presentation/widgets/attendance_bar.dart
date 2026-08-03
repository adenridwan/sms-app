import 'package:flutter/material.dart';

/// Warna status kehadiran — semantik, sengaja terpisah dari warna aksen app
/// supaya "hadir/sakit/izin/alfa" terbaca sebagai keadaan, bukan sebagai merek.
const Map<String, Color> kAttendanceColors = {
  'hadir': Color(0xFF16A34A),
  'sakit': Color(0xFFD97706),
  'izin': Color(0xFF2563EB),
  'alfa': Color(0xFFDC2626),
  'belum_scan': Color(0xFF9CA3AF),
};

const Map<String, String> kAttendanceLabels = {
  'hadir': 'Hadir',
  'sakit': 'Sakit',
  'izin': 'Izin',
  'alfa': 'Alfa',
  'belum_scan': 'Belum scan',
};

/// Rincian kehadiran sebagai satu bilah tersegmen + legenda.
///
/// Menggantikan deretan teks "Hadir: 486 · Sakit: 12 · …" yang harus dibaca —
/// proporsinya kini terbaca sekilas tanpa memproses angka satu per satu.
class AttendanceBar extends StatelessWidget {
  const AttendanceBar({
    super.key,
    required this.title,
    required this.summary,
    this.percentage,
  });

  final String title;
  final num? percentage;
  final Map<String, int> summary;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    // Urutan tetap agar bilah tidak berubah-ubah susunannya antar-muat.
    const order = ['hadir', 'sakit', 'izin', 'alfa', 'belum_scan'];
    final entries = [
      for (final k in order)
        if ((summary[k] ?? 0) > 0) MapEntry(k, summary[k]!),
    ];
    final total = entries.fold<int>(0, (sum, e) => sum + e.value);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(title,
                style: const TextStyle(
                    fontSize: 12.5, fontWeight: FontWeight.w600)),
            if (percentage != null)
              Text(
                '${percentage!.toStringAsFixed(percentage! % 1 == 0 ? 0 : 1)}%',
                style: TextStyle(
                  fontSize: 21,
                  fontWeight: FontWeight.w700,
                  letterSpacing: -.6,
                  color: scheme.primary,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: SizedBox(
            height: 8,
            child: total == 0
                ? ColoredBox(color: scheme.surfaceContainerHighest)
                : Row(
                    children: [
                      for (final e in entries)
                        Expanded(
                          flex: e.value,
                          child: ColoredBox(
                            color: kAttendanceColors[e.key] ?? scheme.outline,
                          ),
                        ),
                    ],
                  ),
          ),
        ),
        const SizedBox(height: 9),
        Wrap(
          spacing: 12,
          runSpacing: 5,
          children: [
            for (final e in entries)
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                      color: kAttendanceColors[e.key] ?? scheme.outline,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(width: 5),
                  Text(
                    '${kAttendanceLabels[e.key] ?? e.key} ${e.value}',
                    style: TextStyle(
                        fontSize: 10.5, color: scheme.onSurfaceVariant),
                  ),
                ],
              ),
          ],
        ),
      ],
    );
  }
}
