import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/local_session_controller.dart';
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
/// Juga menampilkan status koneksi backend dan manajemen akun lokal.
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

      // Update last sync time in local session
      ref.read(localSessionProvider.notifier).markSynced();

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

  Future<void> _disconnectBackend() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Putus Koneksi'),
        content: const Text(
          'Yakin memutus koneksi ke sekolah? Anda tidak akan bisa '
          'sinkronisasi data sampai scan QR koneksi lagi.\n\n'
          'Data yang sudah tersimpan tidak akan hilang.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(
              backgroundColor: Colors.red,
            ),
            child: const Text('Putuskan'),
          ),
        ],
      ),
    );

    if (ok == true) {
      await ref.read(localSessionProvider.notifier).disconnectFromBackend();
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(const SnackBar(
            content: Text('Koneksi ke sekolah diputus'),
          ));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final lastSynced = ref.watch(lastSyncedProvider).valueOrNull;
    final pending = ref.watch(scanControllerProvider).queueCount +
        ref.watch(classAttendanceQueueProvider).count;
    final session = ref.watch(localSessionProvider);
    final connection = session.connection;
    final account = session.account;

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
        children: [
          // ==== AKUN LOKAL ====
          const FieldLabel('Akun lokal'),
          const SizedBox(height: 8),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (account != null) ...[
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: scheme.primaryContainer,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          _initials(account.fullName),
                          style: TextStyle(
                            color: scheme.onPrimaryContainer,
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              account.fullName,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              account.email,
                              style: TextStyle(
                                fontSize: 12,
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                ],
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => context.push('/change-password'),
                    icon: const Icon(Icons.lock_outline, size: 18),
                    label: const Text('Ubah Password'),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 22),

          // ==== KONEKSI SERVER ====
          const FieldLabel('Koneksi sekolah'),
          const SizedBox(height: 8),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 10,
                      height: 10,
                      decoration: BoxDecoration(
                        color: connection != null ? Colors.green : Colors.grey,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        connection != null
                            ? 'Terhubung ke ${connection.schoolName ?? 'server'}'
                            : 'Belum terhubung ke sekolah',
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w600,
                          letterSpacing: -.1,
                        ),
                      ),
                    ),
                  ],
                ),
                if (connection != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(
                        width: 100,
                        child: Text(
                          'API URL',
                          style: TextStyle(
                            fontSize: 11,
                            color: Colors.grey,
                          ),
                        ),
                      ),
                      Expanded(
                        child: Text(
                          connection.apiUrl,
                          style: const TextStyle(fontSize: 11),
                        ),
                      ),
                      // Tombol edit URL
                      InkWell(
                        onTap: () => context.push('/server-config'),
                        child: const Padding(
                          padding: EdgeInsets.all(4),
                          child: Icon(Icons.edit, size: 14, color: Colors.grey),
                        ),
                      ),
                    ],
                  ),
                  if (connection.backendUserName != null)
                    _InfoRow(
                      label: 'Masuk sebagai',
                      value: connection.backendUserName!,
                    ),
                  if (connection.backendUserEmail != null)
                    _InfoRow(
                      label: 'Email',
                      value: connection.backendUserEmail!,
                    ),
                  _InfoRow(
                    label: 'Terhubung sejak',
                    value: _formatDateTime(connection.connectedAt),
                  ),
                  if (connection.lastSyncAt != null)
                    _InfoRow(
                      label: 'Sinkron terakhir',
                      value: _relative(connection.lastSyncAt),
                    ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: _disconnectBackend,
                      icon: const Icon(Icons.link_off, size: 18),
                      label: const Text('Putus Koneksi'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                        side: BorderSide(color: Colors.red.withValues(alpha: 0.5)),
                      ),
                    ),
                  ),
                ] else ...[
                  const SizedBox(height: 8),
                  Text(
                    'Pindai QR dari admin sekolah untuk menghubungkan aplikasi '
                    'dan sinkronisasi data.',
                    style: TextStyle(
                      fontSize: 12,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: () => context.push('/connect-scan'),
                      icon: const Icon(Icons.qr_code_scanner, size: 18),
                      label: const Text('Scan QR Koneksi'),
                    ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => context.push('/server-config'),
                      icon: const Icon(Icons.edit, size: 18),
                      label: const Text('Input Server Manual'),
                    ),
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(height: 22),

          // ==== SINKRONISASI ====
          const FieldLabel('Sinkronisasi data'),
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
                    onPressed: connection == null
                        ? null
                        : (_syncing ? null : _sync),
                    child: _syncing
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2.4, color: Colors.white),
                          )
                        : const Text('Sinkronkan Sekarang'),
                  ),
                ),
                if (connection == null) ...[
                  const SizedBox(height: 8),
                  Text(
                    'Hubungkan ke sekolah dulu untuk bisa menyinkronkan.',
                    style: TextStyle(
                      fontSize: 11,
                      color: scheme.onSurfaceVariant,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
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

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty);
    if (parts.isEmpty) return '?';
    return parts.take(2).map((e) => e[0].toUpperCase()).join();
  }

  static String _formatDateTime(DateTime dt) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(dt.day)}/${two(dt.month)}/${dt.year} ${two(dt.hour)}:${two(dt.minute)}';
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

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: TextStyle(
                fontSize: 11,
                color: scheme.onSurfaceVariant,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 11),
            ),
          ),
        ],
      ),
    );
  }
}
