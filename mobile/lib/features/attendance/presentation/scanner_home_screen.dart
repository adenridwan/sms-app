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

/// Metode absensi — daftar dan namanya mengikuti `attMethodOptions` pada
/// rujukan desain (artifact `0af1e5c5`).
enum AttendanceMethod { qr, refId, checklist, mine }

/// Metode yang sedang dipilih di tab Absensi.
///
/// Disimpan di provider, bukan `State` layar, supaya pilihan bertahan saat
/// pengguna berpindah tab lalu kembali — seperti rujukan yang menyimpan
/// `attScreen` di state aplikasi.
final attendanceMethodProvider =
    StateProvider<AttendanceMethod>((ref) => AttendanceMethod.qr);

/// Tab Absensi.
///
/// Mengikuti rujukan desain: judul, satu baris pil metode, lalu **isi yang
/// berganti di tempat** — bukan tombol yang membuka layar lain. Hanya kamera
/// yang tetap layar penuh, karena viewfinder butuh seluruh layar dan punya
/// siklus hidup sendiri.
class ScannerHomeScreen extends ConsumerWidget {
  const ScannerHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final method = ref.watch(attendanceMethodProvider);
    final user = ref.watch(authControllerProvider).user;

    // Rujukan membedakan keduanya: admin memindai kartu orang lain, peran lain
    // menampilkan QR miliknya sendiri.
    final isAdmin = (user?.userType ?? '') != 'teacher';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Absensi'),
        actions: const [
          Center(child: BackendStatusDot()),
          SizedBox(width: 20),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 14),
            child: PillTabs<AttendanceMethod>(
              options: AttendanceMethod.values,
              selected: method,
              labelOf: (m) => switch (m) {
                AttendanceMethod.qr => isAdmin ? 'Pindai QR' : 'Tampilkan QR',
                AttendanceMethod.refId => 'Ref ID',
                AttendanceMethod.checklist => 'Checklist',
                AttendanceMethod.mine => 'Absensi Saya',
              },
              onChanged: (m) =>
                  ref.read(attendanceMethodProvider.notifier).state = m,
            ),
          ),
          Expanded(
            child: switch (method) {
              AttendanceMethod.qr => const _ScanPane(),
              AttendanceMethod.refId => const ManualInputView(),
              AttendanceMethod.checklist => const ClassAttendanceView(),
              AttendanceMethod.mine => const MyAttendanceView(),
            },
          ),
        ],
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
