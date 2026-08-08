import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../attendance/presentation/scan_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/dashboard_repository.dart';
import '../models/action_item.dart';
import '../models/dashboard_stats.dart';
import 'widgets/action_grid.dart';
import 'widgets/home_hero.dart';
import 'widgets/section_header.dart';
import 'widgets/stat_chips.dart';

/// Tab Beranda.
///
/// Susunan mengikuti rujukan desain: bidang biru dengan kartu status di
/// dalamnya, kartu aksi 2 kolom (satu ditonjolkan), ringkasan angka bertinta,
/// lalu daftar. Isi menyesuaikan paket peran dari `GET /dashboard` — admin
/// bertugas memindai di gerbang, guru mengabsen di ruangan.
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
    final actions = visibleActionsFor(user);

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
              greeting: _greeting(),
              name: user?.fullName ?? 'Petugas',
              subtitle: _subtitle(user?.userType, data),
              statusDate: _prettyDate(scan.bootstrap?.today),
              statusTitle: _statusTitle(data, isTeacher),
              statusCaption: _statusCaption(data, isTeacher),
              statusColor: _statusColor(data),
              statusIcon: _statusIcon(data),
            ),

            Padding(
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!auth.isSessionVerified)
                    _Strip(
                      icon: Icons.cloud_off_rounded,
                      color: scheme.error,
                      text: 'Memakai sesi tersimpan — belum terhubung ke '
                          'server. Scan tetap disimpan dan disinkronkan nanti.',
                    ),

                  if (isTeacher && data?.linked == false)
                    const _Strip(
                      icon: Icons.link_off_rounded,
                      color: Color(0xFFD97706),
                      text: 'Akun Anda belum terhubung ke kelas mana pun. '
                          'Hubungi administrator sekolah.',
                    ),

                  if (scan.queueCount > 0)
                    InkWell(
                      onTap: () => context.push('/queue'),
                      borderRadius: BorderRadius.circular(12),
                      child: _Strip(
                        icon: Icons.cloud_upload_rounded,
                        color: scheme.primary,
                        text: '${scan.queueCount} scan menunggu sinkron',
                      ),
                    ),

                  ActionGrid(
                    actions: actions,
                    // Tugas utama berbeda per peran.
                    primaryKey: isTeacher
                        ? 'attendance.class'
                        : 'attendance.scan',
                  ),

                  // Selama absensi belum dimulai, keempat ubin pasti berisi "0"
                  // — itu hanya menambah bising, sedangkan kabar yang sama
                  // sudah disampaikan kartu status di hero. Ubin baru muncul
                  // setelah ada kehadiran yang benar-benar tercatat.
                  if (data != null && !_notStarted(data)) ...[
                    const SizedBox(height: 26),
                    SectionHeader(
                      title: isTeacher
                          ? 'Absensi Kelas Saya'
                          : 'Absensi Hari Ini',
                      trailing: data.attendancePercentage == null
                          ? null
                          : '${_pct(data.attendancePercentage!)}% hadir',
                    ),
                    const SizedBox(height: 10),
                    StatChips(summary: data.attendanceSummary),
                    if ((data.attendanceSummary['belum_scan'] ?? 0) > 0) ...[
                      const SizedBox(height: 8),
                      Text(
                        '${data.attendanceSummary['belum_scan']} siswa belum tercatat hari ini',
                        style: TextStyle(
                            fontSize: 11.5, color: scheme.onSurfaceVariant),
                      ),
                    ],
                  ],

                  if (isTeacher && (data?.myClasses.isNotEmpty ?? false)) ...[
                    const SizedBox(height: 26),
                    const SectionHeader(title: 'Kelas Saya'),
                    const SizedBox(height: 10),
                    for (final c in data!.myClasses)
                      _ClassCard(
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

  static String _greeting() {
    final h = DateTime.now().hour;
    if (h < 11) return 'Selamat pagi,';
    if (h < 15) return 'Selamat siang,';
    if (h < 18) return 'Selamat sore,';
    return 'Selamat malam,';
  }

  static String _pct(num v) => v.toStringAsFixed(v % 1 == 0 ? 0 : 1);

  /// `2026-08-03` → `Sen, 3 Agu 2026`. Dikerjakan manual agar tak perlu
  /// menambah paket intl hanya untuk satu label.
  static String? _prettyDate(String? iso) {
    if (iso == null || iso.isEmpty) return null;
    final d = DateTime.tryParse(iso);
    if (d == null) return iso;

    const days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
      'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];
    return '${days[d.weekday - 1]}, ${d.day} ${months[d.month - 1]} ${d.year}';
  }

  /// Baris identitas: peran, dan untuk guru ditambah kelas perwaliannya.
  static String _subtitle(String? userType, DashboardStats? data) {
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
      if (homeroom.isNotEmpty) return '$persona • Wali Kelas ${homeroom.first}';
      if (data.totalClassrooms != null) {
        return '$persona • ${data.totalClassrooms} kelas';
      }
    }
    return persona;
  }

  static int _total(DashboardStats? d) =>
      d?.attendanceSummary.values.fold<int>(0, (a, b) => a + b) ?? 0;

  /// True bila hari ini belum ada satu pun siswa tercatat.
  ///
  /// Termasuk kasus **semua angka nol** — itu terjadi di hari yang belum ada
  /// datanya sama sekali. Tanpa penjagaan ini, layar sempat menampilkan
  /// "0 dari 0 sudah hadir" berikut keterangan "Semua siswa hadir" yang
  /// saling bertentangan.
  static bool _notStarted(DashboardStats? d) {
    final s = d?.attendanceSummary;
    if (s == null || s.isEmpty) return true;
    final total = _total(d);
    if (total == 0) return true;
    return (s['belum_scan'] ?? 0) == total;
  }

  /// Satu kalimat terpenting hari ini, di dalam hero.
  static String _statusTitle(DashboardStats? d, bool isTeacher) {
    if (d == null) return 'Memuat data…';

    final s = d.attendanceSummary;
    if (s.isEmpty) {
      if (isTeacher && d.linked == false) return 'Belum terhubung ke kelas';
      return 'Belum ada data absensi';
    }

    if (_total(d) == 0) return 'Belum ada data absensi';
    if (_notStarted(d)) return 'Absensi belum dimulai';

    return '${s['hadir'] ?? 0} dari ${_total(d)} sudah hadir';
  }

  static String? _statusCaption(DashboardStats? d, bool isTeacher) {
    if (d == null) return null;
    final s = d.attendanceSummary;
    if (s.isEmpty) return null;

    // Belum dimulai: jangan menyimpulkan apa pun soal kehadiran — dulu di sini
    // sempat muncul "Semua siswa hadir" yang justru bertentangan dengan
    // judulnya.
    if (_notStarted(d)) {
      final total = _total(d);
      return total == 0
          ? 'Belum ada data untuk hari ini'
          : '$total siswa menunggu dicatat';
    }

    final belum = s['belum_scan'] ?? 0;
    if (belum > 0) return '$belum belum tercatat';

    final tidakHadir =
        (s['sakit'] ?? 0) + (s['izin'] ?? 0) + (s['alfa'] ?? 0);
    return tidakHadir == 0
        ? 'Semua siswa hadir'
        : '$tidakHadir tidak hadir hari ini';
  }

  static Color _statusColor(DashboardStats? d) {
    // "Belum dimulai" bukan keadaan buruk — memberinya merah membuat layar
    // terasa seperti ada masalah padahal harinya memang baru mulai.
    if (_notStarted(d)) return const Color(0xFF64748B);

    final pct = d?.attendancePercentage;
    if (pct == null) return const Color(0xFF64748B);
    if (pct >= 90) return const Color(0xFF22C55E);
    if (pct >= 60) return const Color(0xFFD97706);
    return const Color(0xFFDC2626);
  }

  static IconData _statusIcon(DashboardStats? d) {
    if (_notStarted(d)) return Icons.schedule_rounded;
    final pct = d?.attendancePercentage;
    if (pct == null) return Icons.schedule_rounded;
    return pct >= 90 ? Icons.check_circle_rounded : Icons.info_rounded;
  }
}

/// Pemberitahuan tipis dengan rel warna di kiri — pengganti kartu, agar tidak
/// menambah satu permukaan lagi hanya untuk satu kalimat.
class _Strip extends StatelessWidget {
  const _Strip({
    required this.icon,
    required this.color,
    required this.text,
  });

  final IconData icon;
  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(12, 11, 13, 11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .09),
        border: Border(left: BorderSide(color: color, width: 3)),
        borderRadius: const BorderRadius.horizontal(right: Radius.circular(12)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 17, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(text,
                style: TextStyle(
                  fontSize: 11.5,
                  height: 1.35,
                  color: scheme.onSurface.withValues(alpha: .8),
                )),
          ),
        ],
      ),
    );
  }
}

/// Baris kelas bergaya kartu — sama seperti daftar "Aktivitas Terbaru" pada
/// rujukan: ikon bulat bertinta, judul tebal, meta abu, chevron.
class _ClassCard extends StatelessWidget {
  const _ClassCard({
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
            padding: const EdgeInsets.fromLTRB(12, 11, 12, 11),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: scheme.primary.withValues(alpha: .11),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.groups_rounded,
                      size: 20, color: scheme.primary),
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
                                    fontSize: 13.5,
                                    fontWeight: FontWeight.w700,
                                    letterSpacing: -.2)),
                          ),
                          if (isHomeroom) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: scheme.primary.withValues(alpha: .12),
                                borderRadius: BorderRadius.circular(5),
                              ),
                              child: Text('WALI',
                                  style: TextStyle(
                                    fontSize: 8.5,
                                    fontWeight: FontWeight.w700,
                                    letterSpacing: .5,
                                    color: scheme.primary,
                                  )),
                            ),
                          ],
                        ],
                      ),
                      if (item.studentsCount != null)
                        Text('${item.studentsCount} siswa',
                            style: TextStyle(
                                fontSize: 11.5,
                                color: scheme.onSurfaceVariant)),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right_rounded,
                    size: 20, color: scheme.onSurfaceVariant),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
