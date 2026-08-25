import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/theme/ui_kit.dart';
import '../data/my_attendance_repository.dart';
import '../models/my_attendance.dart';

/// Panel "Absensi Saya" — kode presensi milik akun yang login, lalu riwayat.
///
/// Susunannya dari rujukan desain (`attIsOwn`): satu kartu gelap berisi label
/// "KODE PRESENSI ANDA", QR di bidang terang, dan kodenya sebagai teks; di
/// bawahnya daftar riwayat dengan pil status di kanan.
///
/// Bedanya dengan rujukan: QR di sana hanya gambar hiasan. Di sini QR-nya
/// sungguhan — berisi `unique_code` yang memang dicocokkan scanner — karena
/// kode presensi yang tak bisa dipindai tak ada gunanya.
class MyAttendanceView extends ConsumerWidget {
  const MyAttendanceView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final today = ref.watch(myAttendanceProvider);
    final history = ref.watch(myHistoryProvider);
    final scheme = Theme.of(context).colorScheme;

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(myAttendanceTodayProvider);
        ref.invalidate(myAttendanceHistoryProvider);
      },
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        children: [
          switch (today) {
            AsyncData(:final value) => _CodeCard(data: value),
            AsyncError(:final error) => InfoStrip(
                text: 'Tidak bisa memuat kode presensi: $error',
                margin: EdgeInsets.zero,
              ),
            _ => const Padding(
                padding: EdgeInsets.symmetric(vertical: 40),
                child: Center(child: CircularProgressIndicator()),
              ),
          },
          const SizedBox(height: 22),
          const FieldLabel('Riwayat'),
          const SizedBox(height: 8),
          switch (history) {
            AsyncData(:final value) when value.isEmpty => Text(
                'Belum ada riwayat bulan ini.',
                style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant),
              ),
            AsyncData(:final value) => Column(
                children: [for (final d in value) _HistoryRow(day: d)],
              ),
            AsyncError() => Text(
                'Riwayat tidak bisa dimuat saat offline.',
                style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant),
              ),
            _ => const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(child: CircularProgressIndicator()),
              ),
          },
        ],
      ),
    );
  }
}

class _CodeCard extends StatelessWidget {
  const _CodeCard({required this.data});

  final MyAttendanceToday data;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final onDark = scheme.surface;

    if (!data.hasCode) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: scheme.onSurface,
          borderRadius: BorderRadius.circular(22),
        ),
        child: Column(
          children: [
            Text(
              'KODE PRESENSI ANDA',
              style: TextStyle(
                fontSize: 11,
                letterSpacing: .9,
                fontWeight: FontWeight.w600,
                color: onDark.withValues(alpha: .62),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Akun ini belum punya kode presensi. Kode dibuat administrator '
              'lewat data kepegawaian di web.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 12,
                height: 1.45,
                color: onDark.withValues(alpha: .72),
              ),
            ),
          ],
        ),
      );
    }

    final style = attendanceStatusStyle(data.status, scheme);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: scheme.onSurface,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        children: [
          Text(
            'KODE PRESENSI ANDA',
            style: TextStyle(
              fontSize: 11,
              letterSpacing: .9,
              fontWeight: FontWeight.w600,
              color: onDark.withValues(alpha: .62),
            ),
          ),
          const SizedBox(height: 14),
          Container(
            width: 168,
            height: 168,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              // Bidang terang di balik QR: pemindai butuh kontras tinggi, dan
              // QR gelap-di-atas-gelap praktis tak terbaca.
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
            ),
            child: QrImageView(
              data: data.uniqueCode!,
              version: QrVersions.auto,
              size: 140,
              backgroundColor: Colors.white,
              eyeStyle: const QrEyeStyle(
                eyeShape: QrEyeShape.square,
                color: Colors.black,
              ),
              dataModuleStyle: const QrDataModuleStyle(
                dataModuleShape: QrDataModuleShape.square,
                color: Colors.black,
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            data.uniqueCode!,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              letterSpacing: .6,
              color: onDark,
            ),
          ),
          if ((data.rfidCode ?? '').isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              'Ref ID · ${data.rfidCode}',
              style: TextStyle(
                fontSize: 11.5,
                color: onDark.withValues(alpha: .62),
              ),
            ),
          ],
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                decoration: BoxDecoration(
                  color: style.color.withValues(alpha: .18),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  style.label,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: style.color,
                  ),
                ),
              ),
              if (data.checkInTime != null) ...[
                const SizedBox(width: 10),
                Text(
                  'Masuk ${data.checkInTime}',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: onDark.withValues(alpha: .62),
                  ),
                ),
              ],
              if (data.checkOutTime != null) ...[
                const SizedBox(width: 10),
                Text(
                  'Pulang ${data.checkOutTime}',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: onDark.withValues(alpha: .62),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({required this.day});

  final MyAttendanceDay day;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final style = attendanceStatusStyle(day.status, scheme);

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            day.label ?? day.shortDate,
            style: const TextStyle(fontSize: 13),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
            decoration: BoxDecoration(
              color: style.color.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              style.label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: style.color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
