import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/local_session_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/security/biometric_service.dart';
import '../../../core/security/biometric_settings.dart';
import '../../../core/storage/sync_status_store.dart';
import '../../../core/theme/ui_kit.dart';
import '../../attendance/presentation/class_attendance_queue_controller.dart';
import '../../attendance/presentation/scan_controller.dart';
import '../../auth/presentation/auth_controller.dart';

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
    final user = ref.watch(authControllerProvider).user;

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
        children: [
          // ==== AKUN SEKOLAH ====
          const FieldLabel('Akun sekolah'),
          const SizedBox(height: 8),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (user != null) ...[
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
                          _initials(user.fullName),
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
                              user.fullName,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              user.email,
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

          // ==== KEAMANAN ====
          const FieldLabel('Keamanan'),
          const SizedBox(height: 8),
          const _BiometricPanel(),

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

/// Setelan biometrik.
///
/// Sakelar utama hanya bisa dinyalakan setelah sensornya **benar-benar
/// menjawab** sekali: menyalakannya berdasarkan janji saja akan mengunci
/// pengguna di layar kunci yang tak bisa dibuka biometrik, padahal ia mengira
/// sudah aktif.
class _BiometricPanel extends ConsumerStatefulWidget {
  const _BiometricPanel();

  @override
  ConsumerState<_BiometricPanel> createState() => _BiometricPanelState();
}

class _BiometricPanelState extends ConsumerState<_BiometricPanel> {
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    // Sidik jari bisa saja baru didaftarkan lewat Setelan sistem sejak layar
    // ini terakhir dibuka.
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => ref.invalidate(biometricCapabilityProvider),
    );
  }

  Future<void> _toggleUnlock(bool value) async {
    final notifier = ref.read(biometricSettingsProvider.notifier);

    if (!value) {
      await notifier.setUnlockEnabled(false);
      return;
    }

    setState(() => _busy = true);
    final outcome = await ref.read(biometricServiceProvider).authenticate(
          reason: 'Pastikan sidik jari atau wajah Anda dikenali',
        );
    if (!mounted) return;
    setState(() => _busy = false);

    if (outcome == BiometricOutcome.success) {
      await notifier.setUnlockEnabled(true);
      return;
    }

    ref.invalidate(biometricCapabilityProvider);
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Text(
          outcome == BiometricOutcome.unavailable
              ? 'Biometrik belum bisa dipakai. Daftarkan sidik jari atau '
                  'wajah lebih dulu di Setelan perangkat.'
              : 'Tidak dikenali — biometrik belum diaktifkan.',
        ),
      ));
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final settings = ref.watch(biometricSettingsProvider);
    final capability = ref.watch(biometricCapabilityProvider);

    return Panel(
      child: capability.when(
        loading: () => const Padding(
          padding: EdgeInsets.symmetric(vertical: 10),
          child: Center(
            child: SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2.2),
            ),
          ),
        ),
        error: (_, __) => Text(
          'Status biometrik tidak bisa dibaca di perangkat ini.',
          style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant),
        ),
        data: (cap) {
          if (!cap.available) {
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.fingerprint_rounded,
                    size: 20, color: scheme.onSurfaceVariant),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Biometrik tidak tersedia',
                          style: TextStyle(
                              fontSize: 13.5, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 3),
                      Text(
                        'Perangkat ini belum punya sidik jari atau wajah yang '
                        'terdaftar. Daftarkan lebih dulu lewat Setelan '
                        'perangkat, lalu buka layar ini lagi.',
                        style: TextStyle(
                          fontSize: 11.5,
                          height: 1.45,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            );
          }

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _BiometricSwitch(
                icon: Icons.fingerprint_rounded,
                title: 'Buka kunci dengan ${cap.label}',
                subtitle: 'Aplikasi mengunci diri setelah menganggur. '
                    'Buka tanpa mengetik password.',
                value: settings.unlockEnabled,
                busy: _busy,
                onChanged: _toggleUnlock,
              ),
              Divider(height: 22, color: scheme.outlineVariant),
              _BiometricSwitch(
                icon: Icons.lock_clock_rounded,
                title: 'Minta biometrik saat membuka aplikasi',
                subtitle: settings.unlockEnabled
                    ? 'Kunci dipasang tiap aplikasi dibuka atau kembali dari '
                        'latar, tanpa menunggu waktu menganggur.'
                    : 'Nyalakan sakelar di atas lebih dulu.',
                value: settings.requireOnLaunch,
                enabled: settings.unlockEnabled && !_busy,
                onChanged: (v) => ref
                    .read(biometricSettingsProvider.notifier)
                    .setRequireOnLaunch(v),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _BiometricSwitch extends StatelessWidget {
  const _BiometricSwitch({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
    this.enabled = true,
    this.busy = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool enabled;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final on = enabled && !busy;

    return Opacity(
      opacity: on ? 1 : .55,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: scheme.onSurfaceVariant),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: const TextStyle(
                        fontSize: 13.5, fontWeight: FontWeight.w600)),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 11.5,
                    height: 1.45,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          busy
              ? const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  child: SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2),
                  ),
                )
              : Switch(value: value, onChanged: on ? onChanged : null),
        ],
      ),
    );
  }
}
