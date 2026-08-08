import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

/// Blok header Beranda: sapaan, identitas, avatar, dan satu kartu status
/// bersarang di dalam bidang biru.
///
/// Kartu bersarang itu penting: ia mengangkat **satu** informasi terpenting
/// hari ini ke tempat paling menonjol, tanpa menambah kartu baru di badan
/// halaman.
class HomeHero extends StatelessWidget {
  const HomeHero({
    super.key,
    required this.greeting,
    required this.name,
    required this.subtitle,
    required this.statusTitle,
    this.statusDate,
    this.statusCaption,
    this.statusColor,
    this.statusIcon,
  });

  final String greeting;
  final String name;
  final String subtitle;

  /// Baris utama kartu status, mis. "486 dari 540 sudah hadir".
  final String statusTitle;

  /// Label kanan atas kartu status, biasanya tanggal.
  final String? statusDate;

  /// Baris kecil di bawah [statusTitle].
  final String? statusCaption;

  final Color? statusColor;
  final IconData? statusIcon;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(
        20,
        MediaQuery.of(context).padding.top + 18,
        20,
        22,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [scheme.primary, AppTheme.heroGradientEnd],
        ),
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(26),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(greeting,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .85),
                          fontSize: 13,
                        )),
                    const SizedBox(height: 2),
                    Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 21,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -.4,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .78),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 12),
              _Avatar(name: name),
            ],
          ),
          const SizedBox(height: 18),
          _StatusCard(
            title: statusTitle,
            date: statusDate,
            caption: statusCaption,
            color: statusColor ?? const Color(0xFF22C55E),
            icon: statusIcon ?? Icons.check_circle_rounded,
          ),
        ],
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final parts =
        name.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty).toList();
    final initials = parts.isEmpty
        ? '?'
        : parts.take(2).map((e) => e[0].toUpperCase()).join();

    return Container(
      width: 46,
      height: 46,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: .2),
        shape: BoxShape.circle,
      ),
      child: Text(
        initials,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
          fontSize: 15,
        ),
      ),
    );
  }
}

/// Panel semi-transparan di dalam hero — permukaan kedua tanpa memakai warna
/// baru, sehingga hierarkinya terbaca tanpa menambah bobot visual.
class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.title,
    required this.color,
    required this.icon,
    this.date,
    this.caption,
  });

  final String title;
  final String? date;
  final String? caption;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 13, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: .14),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: .16)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Status hari ini',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: .82),
                    fontSize: 11.5,
                  )),
              if (date != null)
                Text(date!,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .82),
                      fontSize: 11.5,
                    )),
            ],
          ),
          const SizedBox(height: 11),
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                child: Icon(icon, size: 21, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 15.5,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -.2,
                      ),
                    ),
                    if (caption != null) ...[
                      const SizedBox(height: 1),
                      Text(
                        caption!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .8),
                          fontSize: 11.5,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
