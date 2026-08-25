import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/sync_status_store.dart';
import '../../../core/theme/ui_kit.dart';
import '../../attendance/presentation/class_attendance_queue_controller.dart';
import '../../attendance/presentation/scan_controller.dart';

/// Waktu sinkron terakhir. Dibaca ulang tiap layar dibuka dan sesudah sinkron.
final lastSyncedProvider = FutureProvider.autoDispose<DateTime?>(
  (ref) => SyncStatusStore().lastSyncedAt(),
);

/// Layar **Pengaturan & Sinkronisasi**.
///
/// Mengikuti rujukan desain (`openSettingsHandler`): bagian **Sinkronisasi
/// Server** dengan "Terakhir tersinkron" dan tombol "Sinkronkan dengan Server
/// API".
///
/// Bagian **Master Data Keuangan** pada rujukan (periode laporan, tambah/hapus
/// pos biaya) sengaja **tidak** dibuat: proyek ini menaruh seluruh master data
/// di web dan menyisakan transaksi saja untuk mobile
/// (`docs/04-NAVIGATION-MENU.md`). Menambahkan CRUD pos biaya di sini akan
/// membalik keputusan itu diam-diam. Yang ditampilkan hanya penunjuk ke web.
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  bool _syncing = false;

  Future<void> _sync() async {
    setState(() => _syncing = true);
    final messenger = ScaffoldMessenger.of(context);
    final parts = <String>[];

    try {
      final scan = await ref.read(scanControllerProvider.notifier).syncOffline();
      if (scan != null) parts.add('${scan.success} scan');

      final kelas =
          await ref.read(classAttendanceQueueProvider.notifier).sync();
      if (kelas.total > 0) parts.add('${kelas.success} absen kelas');

      ref.invalidate(lastSyncedProvider);
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text(parts.isEmpty
              ? 'Tidak ada antrean — semua data sudah terkirim.'
              : 'Terkirim: ${parts.join(', ')}.'),
        ));
    } on ApiException catch (e) {
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _syncing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final lastSynced = ref.watch(lastSyncedProvider).valueOrNull;
    final pending = ref.watch(scanControllerProvider).queueCount +
        ref.watch(classAttendanceQueueProvider).count;

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
        children: [
          const FieldLabel('Sinkronisasi server'),
          const SizedBox(height: 8),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Terakhir tersinkron',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w600,
                    letterSpacing: -.1,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _relative(lastSynced),
                  style: TextStyle(
                    fontSize: 11.5,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  pending == 0
                      ? 'Tidak ada data menunggu.'
                      : '$pending data menunggu dikirim.',
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: pending == 0 ? FontWeight.w400 : FontWeight.w600,
                    color:
                        pending == 0 ? scheme.onSurfaceVariant : scheme.primary,
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _syncing ? null : _sync,
                    child: _syncing
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2.4, color: Colors.white),
                          )
                        : const Text('Sinkronkan dengan Server API'),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 22),
          const FieldLabel('Master data'),
          const SizedBox(height: 8),
          const InfoStrip(
            margin: EdgeInsets.zero,
            tone: StripTone.neutral,
            text: 'Pos biaya, periode laporan, data siswa, dan jadwal diatur '
                'lewat web. Aplikasi ini sengaja hanya memegang transaksi, '
                'supaya perubahan master tidak terjadi dari perangkat yang '
                'berpindah-pindah tangan.',
          ),
        ],
      ),
    );
  }

  /// "3 menit lalu" lebih menjawab "sudah terkirim belum?" daripada jam pasti.
  static String _relative(DateTime? at) {
    if (at == null) return 'Belum pernah tersinkron di perangkat ini';

    final diff = DateTime.now().difference(at);
    if (diff.inSeconds < 60) return 'Baru saja';
    if (diff.inMinutes < 60) return '${diff.inMinutes} menit lalu';
    if (diff.inHours < 24) return '${diff.inHours} jam lalu';
    if (diff.inDays == 1) return 'Kemarin';
    if (diff.inDays < 7) return '${diff.inDays} hari lalu';

    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(at.day)}/${two(at.month)}/${at.year}';
  }
}
