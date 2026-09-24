import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/ui_kit.dart';
import '../../auth/presentation/auth_controller.dart';
import '../models/scan_time.dart';
import 'class_attendance_screen.dart';
import 'manual_input_screen.dart';
import 'my_attendance_view.dart';
import 'scan_controller.dart';

/// Metode absensi — daftar dan namanya mengikuti `attMethodCards` pada
/// rujukan desain (artifact `0af1e5c5`).
enum AttendanceMethod { qr, refId, checklist, mine }

/// Metode yang sedang dibuka di tab Absensi; `null` berarti **menu kartu**.
///
/// Disimpan di provider, bukan `State` layar, supaya pilihan bertahan saat
/// pengguna berpindah tab lalu kembali — seperti rujukan yang menyimpan
/// `attScreen` di state aplikasi.
final attendanceMethodProvider =
    StateProvider<AttendanceMethod?>((ref) => null);

/// Tab Absensi.
///
/// Mengikuti rujukan desain terbaru: layar pertama adalah **menu kartu dua
/// kolom** berisi metode presensi, dan memilih satu kartu membuka isinya di
/// tempat dengan panah kembali ke menu. Bentuk kartu menggantikan baris pil
/// tab: empat label tidak nyaman dimampatkan dalam satu baris pil di layar
/// ponsel, dan kartu memberi ruang untuk keterangan singkat tiap metode.
class ScannerHomeScreen extends ConsumerWidget {
  const ScannerHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final method = ref.watch(attendanceMethodProvider);
    final user = ref.watch(authControllerProvider).user;

    // Rujukan membedakan keduanya: admin memindai kartu orang lain, peran lain
    // menampilkan QR miliknya sendiri.
    final isAdmin = (user?.userType ?? '') != 'teacher';

    void backToMenu() =>
        ref.read(attendanceMethodProvider.notifier).state = null;

    return PopScope(
      // Tombol kembali perangkat mengembalikan ke menu dulu, bukan keluar dari
      // tab — sejajar dengan panah kembali di AppBar.
      canPop: method == null,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) backToMenu();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: method == null
              ? null
              : IconButton(
                  icon: const Icon(Icons.arrow_back_rounded),
                  onPressed: backToMenu,
                ),
          title: Text(_titleOf(method, isAdmin)),
          actions: const [
            Center(child: BackendStatusDot()),
            SizedBox(width: 20),
          ],
        ),
        body: switch (method) {
          null => _MethodMenu(isAdmin: isAdmin),
          AttendanceMethod.qr => const _ScanPane(),
          AttendanceMethod.refId => const ManualInputView(),
          AttendanceMethod.checklist => const ClassAttendanceView(),
          AttendanceMethod.mine => const MyAttendanceView(),
        },
      ),
    );
  }

  static String _titleOf(AttendanceMethod? m, bool isAdmin) => switch (m) {
        null => 'Absensi',
        AttendanceMethod.qr => isAdmin ? 'Pindai QR' : 'Tampilkan QR',
        AttendanceMethod.refId => 'Ref ID',
        AttendanceMethod.checklist => 'Absen Manual',
        AttendanceMethod.mine => 'Absensi Saya',
      };
}

/// Menu kartu metode presensi — grid dua kolom, satu kartu per metode.
class _MethodMenu extends ConsumerWidget {
  const _MethodMenu({required this.isAdmin});

  final bool isAdmin;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scheme = Theme.of(context).colorScheme;

    final cards = <_MethodCardData>[
      _MethodCardData(
        method: AttendanceMethod.qr,
        title: isAdmin ? 'Pindai QR' : 'Tampilkan QR',
        caption: isAdmin ? 'Scan kode siswa' : 'Tampilkan kode kelas',
        icon: Icons.qr_code_scanner_rounded,
      ),
      const _MethodCardData(
        method: AttendanceMethod.refId,
        title: 'Ref ID',
        caption: 'Input kode / RFID',
        icon: Icons.badge_outlined,
      ),
      const _MethodCardData(
        method: AttendanceMethod.checklist,
        title: 'Absen Manual',
        caption: 'Tandai H / A / I',
        icon: Icons.checklist_rounded,
      ),
      const _MethodCardData(
        method: AttendanceMethod.mine,
        title: 'Absensi Saya',
        caption: 'QR & riwayat pribadi',
        icon: Icons.person_outline_rounded,
      ),
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      children: [
        Text(
          'Pilih metode presensi',
          style: TextStyle(fontSize: 12.5, color: scheme.onSurfaceVariant),
        ),
        const SizedBox(height: 14),
        GridView.count(
          crossAxisCount: 2,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: .98,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: [
            for (final c in cards)
              _MethodCard(
                data: c,
                onTap: () =>
                    ref.read(attendanceMethodProvider.notifier).state = c.method,
              ),
          ],
        ),
      ],
    );
  }
}

/// Isi satu kartu metode, dipisah dari widgetnya supaya daftar di atas terbaca
/// sebagai daftar data — bukan pohon widget.
class _MethodCardData {
  const _MethodCardData({
    required this.method,
    required this.title,
    required this.caption,
    required this.icon,
  });

  final AttendanceMethod method;
  final String title;
  final String caption;
  final IconData icon;
}

/// Kartu metode: ikon beraksen di atas, judul dan keterangan menempel di bawah.
class _MethodCard extends StatelessWidget {
  const _MethodCard({required this.data, required this.onTap});

  final _MethodCardData data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: scheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(18),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: scheme.primary.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(data.icon, size: 20, color: scheme.primary),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    data.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.2,
                      color: scheme.onSurface,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    data.caption,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 11,
                      height: 1.4,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Panel QR: sesi Masuk/Pulang, bidang gelap berbingkai QR, catatan lokasi bila
/// diwajibkan, lalu satu tombol utama — susunan dari rujukan desain.
class _ScanPane extends ConsumerWidget {
  const _ScanPane();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(scanControllerProvider);
    final scheme = Theme.of(context).colorScheme;
    final boot = state.bootstrap;

    return RefreshIndicator(
      onRefresh: () => ref.read(scanControllerProvider.notifier).loadBootstrap(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        children: [
          PillTabs<ScanTime>(
            options: const [ScanTime.masuk, ScanTime.pulang],
            selected: state.mode,
            labelOf: (m) => m == ScanTime.masuk ? 'Masuk' : 'Pulang',
            onChanged: (m) =>
                ref.read(scanControllerProvider.notifier).setMode(m),
          ),
          const SizedBox(height: 16),
          if (state.isHoliday)
            InfoStrip(
              text: boot?.holidayNote == null
                  ? 'Hari ini hari libur — absensi dinonaktifkan.'
                  : 'Libur: ${boot!.holidayNote}',
            ),
          if (state.bootstrapError != null)
            InfoStrip(text: state.bootstrapError!),
          _QrPanel(
            caption: state.loadingBootstrap
                ? 'Memuat…'
                : '${boot?.today ?? '-'}  ·  ${boot?.currentTime ?? ''}',
          ),
          if (state.requireLocation) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    color: scheme.primary,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  'Lokasi wajib · dalam radius sekolah',
                  style:
                      TextStyle(fontSize: 11, color: scheme.onSurfaceVariant),
                ),
              ],
            ),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: state.isHoliday ? null : () => context.push('/scan'),
            child: const Text('Mulai Pindai'),
          ),
          const SizedBox(height: 24),
          Panel(
            onTap: () => context.push('/queue'),
            padding: const EdgeInsets.fromLTRB(16, 15, 14, 15),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Antrean offline',
                          style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              letterSpacing: -.2)),
                      const SizedBox(height: 3),
                      Text(
                        state.queueCount > 0
                            ? '${state.queueCount} menunggu sinkron'
                            : 'Semua data sudah terkirim',
                        style: TextStyle(
                          fontSize: 11.5,
                          color: state.queueCount > 0
                              ? scheme.primary
                              : scheme.onSurfaceVariant,
                          fontWeight: state.queueCount > 0
                              ? FontWeight.w600
                              : FontWeight.w400,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.arrow_forward_rounded,
                    size: 18, color: scheme.onSurfaceVariant),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Bidang gelap dengan bingkai QR di tengah — pusat perhatian panel ini pada
/// rujukan, menggantikan kartu identitas petugas yang dipakai sebelumnya.
class _QrPanel extends StatelessWidget {
  const _QrPanel({required this.caption});

  final String caption;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final onDark = scheme.surface;

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: scheme.onSurface,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        children: [
          Container(
            width: 170,
            height: 170,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: onDark.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(22),
            ),
            child: CustomPaint(
              size: const Size(104, 104),
              painter: _QrFramePainter(scheme.primary),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Arahkan kamera ke kode QR siswa',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 12,
              height: 1.4,
              color: onDark.withValues(alpha: .62),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            caption,
            textAlign: TextAlign.center,
            style:
                TextStyle(fontSize: 11, color: onDark.withValues(alpha: .45)),
          ),
        ],
      ),
    );
  }
}

/// Tiga sudut penanda + titik tengah, meniru gambar QR pada rujukan.
///
/// Digambar, bukan aset: bentuknya sederhana dan harus ikut warna aksen tema,
/// jadi menyimpannya sebagai PNG malah menambah berkas yang perlu dirawat.
class _QrFramePainter extends CustomPainter {
  const _QrFramePainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final u = size.width / 120;
    final stroke = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 6 * u;
    final fill = Paint()..color = color;

    void corner(double x, double y) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x * u, y * u, 34 * u, 34 * u),
          Radius.circular(8 * u),
        ),
        stroke,
      );
    }

    corner(4, 4);
    corner(82, 4);
    corner(4, 82);

    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(50 * u, 50 * u, 20 * u, 20 * u),
        Radius.circular(5 * u),
      ),
      fill,
    );
  }

  @override
  bool shouldRepaint(_QrFramePainter oldDelegate) => oldDelegate.color != color;
}
