import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
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
              ? _empty(context)
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (classQueue.items.isNotEmpty) ...[
                      _SectionLabel(
                          'Absen kelas (${classQueue.items.length})'),
                      for (final item in classQueue.items)
                        _ClassTile(item: item),
                      const SizedBox(height: 18),
                    ],
                    if (_scans.isNotEmpty) ...[
                      _SectionLabel('Scan (${_scans.length})'),
                      for (final s in _scans) _ScanTile(scan: s),
                    ],
                  ],
                ),
      bottomNavigationBar: total == 0
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton.icon(
                  onPressed: _syncing ? null : _sync,
                  icon: _syncing
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2.4, color: Colors.white))
                      : const Icon(Icons.cloud_upload_rounded),
                  label:
                      Text(_syncing ? 'Menyinkron…' : 'Sinkron $total data'),
                ),
              ),
            ),
    );
  }

  Widget _empty(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.cloud_done_rounded,
              size: 48, color: scheme.onSurfaceVariant),
          const SizedBox(height: 12),
          Text('Tidak ada antrean',
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text('Semua absensi sudah tersinkron.',
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: scheme.onSurfaceVariant)),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(text,
          style: const TextStyle(
              fontSize: 13, fontWeight: FontWeight.w700, letterSpacing: -.2)),
    );
  }
}

class _ClassTile extends StatelessWidget {
  const _ClassTile({required this.item});
  final QueuedClassAttendance item;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final tally = item.tally;
    final parts = [
      for (final m in tally.keys)
        if ((tally[m] ?? 0) > 0) '${tally[m]} ${m.label.toLowerCase()}',
    ];

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: scheme.primaryContainer,
          child: Icon(Icons.fact_check_rounded,
              color: scheme.onPrimaryContainer, size: 20),
        ),
        title: Text(item.classroomName.isEmpty
            ? '${item.studentCount} siswa'
            : '${item.classroomName} · ${item.studentCount} siswa'),
        subtitle: Text(
          '${_fmtDate(item.date)}${parts.isEmpty ? '' : ' · ${parts.join(', ')}'}',
        ),
      ),
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
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: scheme.secondaryContainer,
          child: Icon(
            scan.waktu.name == 'pulang'
                ? Icons.logout_rounded
                : Icons.login_rounded,
            color: scheme.onSecondaryContainer,
            size: 20,
          ),
        ),
        title: Text(scan.uniqueCode),
        subtitle: Text('${scan.waktu.label} · ${_fmt(scan.scannedAt)}'),
      ),
    );
  }

  static String _fmt(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(d.hour)}:${two(d.minute)} · ${two(d.day)}/${two(d.month)}';
  }
}
