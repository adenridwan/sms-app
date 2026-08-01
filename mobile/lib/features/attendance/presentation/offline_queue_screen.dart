import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/attendance_providers.dart';
import '../models/queued_scan.dart';
import 'scan_controller.dart';

/// Daftar scan yang tertahan offline + tombol sinkron.
class OfflineQueueScreen extends ConsumerStatefulWidget {
  const OfflineQueueScreen({super.key});

  @override
  ConsumerState<OfflineQueueScreen> createState() => _OfflineQueueScreenState();
}

class _OfflineQueueScreenState extends ConsumerState<OfflineQueueScreen> {
  List<QueuedScan> _items = const [];
  bool _loading = true;
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final items = await ref.read(offlineQueueStoreProvider).load();
    if (mounted) {
      setState(() {
        _items = items;
        _loading = false;
      });
    }
  }

  Future<void> _sync() async {
    setState(() => _syncing = true);
    try {
      final summary = await ref.read(scanControllerProvider.notifier).syncOffline();
      if (!mounted) return;
      if (summary == null) {
        _toast('Tidak ada antrean untuk disinkron.');
      } else {
        _toast(
            'Sinkron selesai: ${summary.success} berhasil, ${summary.failed} gagal.');
      }
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
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Antrean Offline')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? _empty(context)
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final s = _items[i];
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: scheme.secondaryContainer,
                          child: Icon(
                            s.waktu.name == 'pulang'
                                ? Icons.logout_rounded
                                : Icons.login_rounded,
                            color: scheme.onSecondaryContainer,
                            size: 20,
                          ),
                        ),
                        title: Text(s.uniqueCode),
                        subtitle: Text(
                          '${s.waktu.label} · ${_fmt(s.scannedAt)}',
                        ),
                      ),
                    );
                  },
                ),
      bottomNavigationBar: _items.isEmpty
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
                  label: Text(_syncing
                      ? 'Menyinkron…'
                      : 'Sinkron ${_items.length} data'),
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
          Icon(Icons.cloud_done_rounded, size: 48, color: scheme.onSurfaceVariant),
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

  static String _fmt(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(d.hour)}:${two(d.minute)} · ${two(d.day)}/${two(d.month)}';
  }
}
