import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../attendance/presentation/scan_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/dashboard_repository.dart';
import '../models/action_item.dart';
import '../models/dashboard_stats.dart';
import 'widgets/action_grid.dart';
import 'widgets/attendance_bar.dart';
import 'widgets/home_hero.dart';

/// Tab Beranda — tata letak "Opsi A": satu blok header memikul identitas,
/// sisanya rata tanpa bingkai kartu (lihat mockup redesign 2026-08-02).
///
/// Isi menyesuaikan paket peran dari `GET /dashboard`: admin melihat angka
/// se-sekolah dan bertugas memindai di gerbang; guru melihat kelas yang ia
/// ampu dan bertugas mengabsen di ruangan.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final scan = ref.watch(scanControllerProvider);
    final stats = ref.watch(dashboardStatsProvider);
    final scheme = Theme.of(context).colorScheme;
    final user = auth.user;

    final data = stats.valueOrNull;
    final isTeacher = data?.isTeacher ?? false;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(dashboardStatsProvider);
          await ref.read(scanControllerProvider.notifier).loadBootstrap();
        },
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            HomeHero(
              name: user?.fullName ?? 'Petugas',
              persona: _personaLine(user?.userType, data),
              today: scan.bootstrap?.today,
              figures: _figuresFor(data),
            ),

            Padding(
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!auth.isSessionVerified)
                    _Strip(
                      icon: Icons.cloud_off_rounded,
                      color: scheme.error,
                      text:
                          'Memakai sesi tersimpan — belum terhubung ke server. '
                          'Scan tetap tersimpan dan disinkronkan nanti.',
                    ),

                  // Guru yang belum ditempatkan di kelas mana pun: angka nol
                  // akan membingungkan, jadi jelaskan penyebabnya.
                  if (isTeacher && data?.linked == false)
                    _Strip(
                      icon: Icons.link_off_rounded,
                      color: scheme.tertiary,
                      text: 'Akun Anda belum terhubung ke kelas mana pun. '
                          'Hubungi administrator sekolah.',
                    ),

                  if (data != null && data.attendanceSummary.isNotEmpty) ...[
                    AttendanceBar(
                      title: isTeacher
                          ? 'Absensi kelas saya'
                          : 'Absensi hari ini',
                      percentage: data.attendancePercentage,
                      summary: data.attendanceSummary,
                    ),
                    const SizedBox(height: 20),
                  ],

                  // Tugas utama berbeda per peran: guru mengabsen di kelas,
                  // petugas lain memindai di gerbang.
                  if (isTeacher)
                    _PrimaryButton(
                      icon: Icons.fact_check_rounded,
                      label: 'Absen Kelas Hari Ini',
                      onTap: data?.linked == false
                          ? null
                          : () => context.push('/class-attendance'),
                    )
                  else if (user?.hasPermission('attendance.record') ?? false)
                    _PrimaryButton(
                      icon: Icons.qr_code_scanner_rounded,
                      label: 'Mulai Scan',
                      onTap: () => context.push('/scan'),
                    ),

                  if (scan.queueCount > 0) ...[
                    const SizedBox(height: 10),
                    InkWell(
                      onTap: () => context.push('/queue'),
                      borderRadius: BorderRadius.circular(9),
                      child: _Strip(
                        icon: Icons.cloud_upload_rounded,
                        color: scheme.primary,
                        text: '${scan.queueCount} scan menunggu sinkron',
                        margin: EdgeInsets.zero,
                      ),
                    ),
                  ],

                  const SizedBox(height: 24),
                  ActionGrid(actions: visibleActionsFor(user)),

                  if (isTeacher && (data?.myClasses.isNotEmpty ?? false)) ...[
                    const SizedBox(height: 26),
                    Text('KELAS SAYA',
                        style: TextStyle(
                          fontSize: 10,
                          letterSpacing: 1.3,
                          fontWeight: FontWeight.w700,
                          color: scheme.onSurfaceVariant,
                        )),
                    const SizedBox(height: 6),
                    for (final c in data!.myClasses)
                      _ClassRow(
                        item: c,
                        isHomeroom: c.id == data.homeroomClassroomId,
                        onTap: () =>
                            context.push('/class-attendance?classroom=${c.id}'),
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Baris kedua header: peran, dan untuk guru ditambah kelas perwaliannya.
  static String _personaLine(String? userType, DashboardStats? data) {
    final persona = switch (userType) {
      'super_admin' => 'Super Admin',
      'admin' => 'Administrator',
      'teacher' => 'Guru',
      'staff' => 'Staf',
      _ => userType ?? '',
    };

    if (data?.isTeacher ?? false) {
      final homeroom = data!.myClasses
          .where((c) => c.id == data.homeroomClassroomId)
          .map((c) => c.name);
      if (homeroom.isNotEmpty) return '$persona · Wali Kelas ${homeroom.first}';
    }
    return persona;
  }

  /// Angka di header — guru melihat lingkup kelasnya, bukan total sekolah.
  static List<HeroFigure> _figuresFor(DashboardStats? d) {
    if (d == null) return const [];

    if (d.isTeacher) {
      return [
        if (d.totalClassrooms != null)
          HeroFigure(value: '${d.totalClassrooms}', label: 'Kelas'),
        if (d.totalStudents != null)
          HeroFigure(value: '${d.totalStudents}', label: 'Siswa'),
      ];
    }

    return [
      if (d.totalStudents != null)
        HeroFigure(value: '${d.totalStudents}', label: 'Siswa'),
      if (d.totalTeachers != null)
        HeroFigure(value: '${d.totalTeachers}', label: 'Guru'),
      if (d.totalClassrooms != null)
        HeroFigure(value: '${d.totalClassrooms}', label: 'Kelas'),
    ];
  }
}

/// Pemberitahuan tipis dengan rel warna di kiri — pengganti kartu, agar
/// tidak menambah satu permukaan lagi hanya untuk satu kalimat.
class _Strip extends StatelessWidget {
  const _Strip({
    required this.icon,
    required this.color,
    required this.text,
    this.margin = const EdgeInsets.only(bottom: 16),
  });

  final IconData icon;
  final Color color;
  final String text;
  final EdgeInsets margin;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: margin,
      padding: const EdgeInsets.fromLTRB(11, 10, 12, 10),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        border: Border(left: BorderSide(color: color, width: 3)),
        borderRadius: const BorderRadius.horizontal(right: Radius.circular(9)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(text,
                style: TextStyle(
                    fontSize: 11.5, color: scheme.onSurfaceVariant, height: 1.35)),
          ),
        ],
      ),
    );
  }
}

class _PrimaryButton extends StatelessWidget {
  const _PrimaryButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: FilledButton.icon(
        onPressed: onTap,
        icon: Icon(icon, size: 19),
        label: Text(label),
        style: FilledButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 15),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }
}

class _ClassRow extends StatelessWidget {
  const _ClassRow({
    required this.item,
    required this.isHomeroom,
    required this.onTap,
  });

  final DashboardClass item;
  final bool isHomeroom;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 11),
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: scheme.outlineVariant)),
        ),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(11),
              ),
              child: Text(
                item.name.length > 4 ? item.name.substring(0, 4) : item.name,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: scheme.onPrimaryContainer,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(item.name,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                fontSize: 13, fontWeight: FontWeight.w600)),
                      ),
                      if (isHomeroom) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: scheme.primaryContainer,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text('WALI',
                              style: TextStyle(
                                fontSize: 8.5,
                                fontWeight: FontWeight.w700,
                                letterSpacing: .5,
                                color: scheme.onPrimaryContainer,
                              )),
                        ),
                      ],
                    ],
                  ),
                  if (item.studentsCount != null)
                    Text('${item.studentsCount} siswa',
                        style: TextStyle(
                            fontSize: 11, color: scheme.onSurfaceVariant)),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded,
                size: 20, color: scheme.onSurfaceVariant),
          ],
        ),
      ),
    );
  }
}
