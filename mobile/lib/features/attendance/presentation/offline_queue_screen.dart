import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/ui_kit.dart';
import '../data/attendance_providers.dart';
import '../models/queued_class_attendance.dart';
import '../models/queued_scan.dart';
import 'class_attendance_queue_controller.dart';
import 'scan_controller.dart';

/// Antrean offline: scan tunggal **dan** sesi absen kelas, plus tombol sinkron.
///
/// Keduanya ditampilkan di satu layar supaya petugas punya satu tempat untuk
/// memastikan tak ada pekerjaan yang tertinggal — bukan dua daftar terpisah.
class OfflineQueueScreen extends ConsumerStatefulWidget {
  const OfflineQueueScreen({super.key});

  @override
  ConsumerState<OfflineQueueScreen> createState() => _OfflineQueueScreenState();
}

class _OfflineQueueScreenState extends ConsumerState<OfflineQueueScreen> {
  List<QueuedScan> _scans = const [];
  bool _loading = true;
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final scans = await ref.read(offlineQueueStoreProvider).load();
    await ref.read(classAttendanceQueueProvider.notifier).refresh();
    if (mounted) {
      setState(() {
        _scans = scans;
        _loading = false;
      });
    }
  }

  Future<void> _sync() async {
    setState(() => _syncing = true);
    final parts = <String>[];
    try {
      final scanSummary =
          await ref.read(scanControllerProvider.notifier).syncOffline();
      if (scanSummary != null) {
        parts.add('${scanSummary.success} scan');
      }

      final classSummary =
          await ref.read(classAttendanceQueueProvider.notifier).sync();
      if (classSummary.total > 0) {
        parts.add('${classSummary.success} absen kelas');
      }

      if (!mounted) return;
      _toast(parts.isEmpty
          ? 'Tidak ada antrean untuk disinkron.'
          : 'Sinkron selesai: ${parts.join(', ')} terkirim.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) _toast(e.message);
    } finally {
      if (mounted) setState(() => _syncing = false);
    }
  }

  void _toast(String msg) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    final classQueue = ref.watch(classAttendanceQueueProvider);
    final total = _scans.length + classQueue.count;

    return Scaffold(
      appBar: AppBar(title: const Text('Antrean Offline')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : total == 0
              ? const Center(
                  child: EmptyNote(
                    title: 'Tidak ada antrean',
                    body: 'Semua absensi sudah terkirim ke sekolah.',
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
                  children: [
                    if (classQueue.items.isNotEmpty) ...[
                      FieldLabel('Absen kelas · ${classQueue.items.length}'),
                      const SizedBox(height: 8),
                      for (final item in classQueue.items)
                        _ClassTile(item: item),
                      const SizedBox(height: 20),
                    ],
                    if (_scans.isNotEmpty) ...[
                      FieldLabel('Scan · ${_scans.length}'),
                      const SizedBox(height: 8),
                      for (final s in _scans) _ScanTile(scan: s),
                    ],
                  ],
                ),
      bottomNavigationBar: total == 0
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                child: FilledButton(
                  onPressed: _syncing ? null : _sync,
                  child: _syncing
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2.4, color: Colors.white))
                      : Text('Sinkron $total data'),
                ),
              ),
            ),
    );
  }
}

/// Baris antrean: judul tebal + keterangan, tanpa avatar berikon.
///
/// Ikon bundar sebelumnya membuat daftar terasa berat padahal isinya seragam —
/// rujukan memakai panel putih polos dan mengandalkan teks.
class _QueueTile extends StatelessWidget {
  const _QueueTile({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      child: Panel(
        padding: const EdgeInsets.fromLTRB(16, 13, 16, 13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title,
                style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -.2)),
            const SizedBox(height: 3),
            Text(subtitle,
                style:
                    TextStyle(fontSize: 11.5, color: scheme.onSurfaceVariant)),
          ],
        ),
      ),
    );
  }
}

class _ClassTile extends StatelessWidget {
  const _ClassTile({required this.item});
  final QueuedClassAttendance item;

  @override
  Widget build(BuildContext context) {
    final tally = item.tally;
    final parts = [
      for (final m in tally.keys)
        if ((tally[m] ?? 0) > 0) '${tally[m]} ${m.label.toLowerCase()}',
    ];

    return _QueueTile(
      title: item.classroomName.isEmpty
          ? '${item.studentCount} siswa'
          : '${item.classroomName} · ${item.studentCount} siswa',
      subtitle:
          '${_fmtDate(item.date)}${parts.isEmpty ? '' : ' · ${parts.join(', ')}'}',
    );
  }

  static String _fmtDate(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(d.day)}/${two(d.month)}/${d.year}';
  }
}

class _ScanTile extends StatelessWidget {
  const _ScanTile({required this.scan});
  final QueuedScan scan;

  @override
  Widget build(BuildContext context) => _QueueTile(
        title: scan.uniqueCode,
        subtitle: '${scan.waktu.label} · ${_fmt(scan.scannedAt)}',
      );

  static String _fmt(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(d.hour)}:${two(d.minute)} · ${two(d.day)}/${two(d.month)}';
  }
}
