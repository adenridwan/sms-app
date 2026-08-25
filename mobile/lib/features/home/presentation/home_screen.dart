import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/ui_kit.dart';
import '../../attendance/presentation/class_attendance_queue_controller.dart';
import '../../attendance/presentation/scan_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/dashboard_repository.dart';
import '../models/action_item.dart';
import '../models/dashboard_stats.dart';
import 'widgets/action_grid.dart';
import 'widgets/home_hero.dart';
import 'widgets/section_header.dart';

/// Tab Beranda — mengikuti rujukan desain: sapaan editorial, satu kartu
/// ringkasan gelap, lalu grid aksi datar.
///
/// **Peran diambil dari akun yang login**, bukan dipilih pengguna: paket peran
/// dari `GET /dashboard` menentukan lingkup angka (kelas sendiri vs seluruh
/// sekolah) dan aksi mana yang ditonjolkan.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final scan = ref.watch(scanControllerProvider);
    final stats = ref.watch(dashboardStatsProvider);
    final user = auth.user;

    final data = stats.valueOrNull;
    final isTeacher = data?.isTeacher ?? false;
    final actions = visibleActionsFor(user);

    final pendingSync =
        scan.queueCount + ref.watch(classAttendanceQueueProvider).count;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: () async {
            invalidateDashboardStats(ref);
            await ref.read(scanControllerProvider.notifier).loadBootstrap();
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 28),
            children: [
              HomeHero(
                greeting: _greeting(user?.fullName),
                roleLabel: _roleLabel(user?.userType),
                dateLabel: _prettyDate(scan.bootstrap?.today),
                schoolName: null,
              ),

              const SizedBox(height: 18),

              if (!auth.isSessionVerified)
                const InfoStrip(
                  text: 'Memakai sesi tersimpan — belum terhubung ke server. '
                      'Absensi tetap disimpan dan disinkronkan nanti.',
                ),

              if (isTeacher && data?.linked == false)
                const InfoStrip(
                  text: 'Akun Anda belum terhubung ke kelas mana pun. '
                      'Hubungi administrator sekolah.',
                ),

              if (pendingSync > 0)
                InfoStrip(
                  onTap: () => context.push('/queue'),
                  text: '$pendingSync data menunggu sinkron — ketuk '
                      'untuk melihat antrean.',
                ),

              SummaryCard(
                title: isTeacher ? 'Hari ini · Kelas Anda' : 'Hari ini · Seluruh Sekolah',
                figures: _figures(data),
              ),

              const SizedBox(height: 20),
              ActionGrid(actions: actions),

              if (isTeacher && (data?.myClasses.isNotEmpty ?? false)) ...[
                const SizedBox(height: 26),
                const SectionHeader(title: 'Kelas Saya'),
                const SizedBox(height: 10),
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
      ),
    );
  }

  /// Tiga angka ringkasan. Sengaja **tidak** memakai kategori "Terlambat"
  /// seperti rujukan — `GET /dashboard` tidak mengembalikannya, dan mengarang
  /// angka lebih buruk daripada menampilkan kategori yang memang ada.
  static List<SummaryFigure> _figures(DashboardStats? d) {
    final s = d?.attendanceSummary ?? const <String, int>{};
    if (s.isEmpty) {
      return const [
        SummaryFigure(value: '—', label: 'Hadir'),
        SummaryFigure(value: '—', label: 'Tidak hadir'),
        SummaryFigure(value: '—', label: 'Belum tercatat'),
      ];
    }

    final hadir = s['hadir'] ?? 0;
    final tidakHadir = (s['sakit'] ?? 0) + (s['izin'] ?? 0) + (s['alfa'] ?? 0);
    final belum = s['belum_scan'] ?? 0;

    return [
      SummaryFigure(value: '$hadir', label: 'Hadir'),
      SummaryFigure(
        value: '$tidakHadir',
        label: 'Tidak hadir',
        tint: SummaryFigure.warn,
      ),
      SummaryFigure(
        value: '$belum',
        label: 'Belum tercatat',
        tint: SummaryFigure.bad,
      ),
    ];
  }

  static String _greeting(String? fullName) {
    final h = DateTime.now().hour;
    final salam = h < 11
        ? 'Selamat pagi'
        : h < 15
            ? 'Selamat siang'
            : h < 18
                ? 'Selamat sore'
                : 'Selamat malam';

    final first = (fullName ?? '').trim().split(RegExp(r'\s+')).first;
    return first.isEmpty ? '$salam,' : '$salam, $first';
  }

  static String _roleLabel(String? userType) => switch (userType) {
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'teacher' => 'Guru',
        'staff' => 'Staf',
        _ => userType ?? '',
      };

  /// `2026-08-22` → `Sabtu, 22 Agustus 2026`.
  static String? _prettyDate(String? iso) {
    if (iso == null || iso.isEmpty) return null;
    final d = DateTime.tryParse(iso);
    if (d == null) return iso;

    const days = [
      'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu',
    ];
    const months = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    return '${days[d.weekday - 1]}, ${d.day} ${months[d.month - 1]} ${d.year}';
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

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
            child: Row(
              children: [
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
                                    fontSize: 14,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -.2)),
                          ),
                          if (isHomeroom) ...[
                            const SizedBox(width: 8),
                            Text('WALI',
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: .8,
                                  color: scheme.primary,
                                )),
                          ],
                        ],
                      ),
                      if (item.studentsCount != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 3),
                          child: Text('${item.studentsCount} siswa',
                              style: TextStyle(
                                  fontSize: 11,
                                  color: scheme.onSurfaceVariant)),
                        ),
                    ],
                  ),
                ),
                Icon(Icons.arrow_forward_rounded,
                    size: 18, color: scheme.onSurfaceVariant),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
