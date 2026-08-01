import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/theme_mode_button.dart';
import '../../auth/presentation/auth_controller.dart';
import '../models/scan_time.dart';
import 'scan_controller.dart';

/// Beranda petugas: info hari ini, pilih Masuk/Pulang, mulai scan / input manual.
class ScannerHomeScreen extends ConsumerWidget {
  const ScannerHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(scanControllerProvider);
    final scheme = Theme.of(context).colorScheme;
    final user = ref.watch(authControllerProvider).user;
    final boot = state.bootstrap;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Absensi'),
        actions: [
          const ThemeModeButton(),
          IconButton(
            tooltip: 'Keluar',
            icon: const Icon(Icons.logout_rounded),
            onPressed: () => _confirmLogout(context, ref),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(scanControllerProvider.notifier).loadBootstrap(),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            // Info hari ini
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(Icons.event_available_rounded, color: scheme.primary),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.fullName ?? 'Petugas',
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                          const SizedBox(height: 2),
                          if (state.loadingBootstrap)
                            Text('Memuat…',
                                style: Theme.of(context).textTheme.bodySmall)
                          else if (state.bootstrapError != null)
                            Text(state.bootstrapError!,
                                style: TextStyle(color: scheme.error, fontSize: 12))
                          else
                            Text(
                              '${boot?.today ?? '-'} · ${boot?.currentTime ?? ''}',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(color: scheme.onSurfaceVariant),
                            ),
                        ],
                      ),
                    ),
                    if (state.requireLocation)
                      Icon(Icons.location_on_rounded,
                          size: 18, color: scheme.onSurfaceVariant),
                  ],
                ),
              ),
            ),

            if (state.isHoliday) ...[
              const SizedBox(height: 12),
              _Banner(
                color: scheme.error,
                icon: Icons.beach_access_rounded,
                text: boot?.holidayNote == null
                    ? 'Hari ini hari libur — absensi dinonaktifkan.'
                    : 'Libur: ${boot!.holidayNote}',
              ),
            ],

            const SizedBox(height: 20),
            Text('Pilih waktu', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            _ModeSelector(
              mode: state.mode,
              onChanged: (m) =>
                  ref.read(scanControllerProvider.notifier).setMode(m),
            ),

            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: state.isHoliday ? null : () => context.push('/scan'),
              icon: const Icon(Icons.qr_code_scanner_rounded),
              label: const Text('Mulai Scan QR'),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: state.isHoliday ? null : () => context.push('/manual'),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              icon: const Icon(Icons.keyboard_alt_outlined),
              label: const Text('Input Ref ID'),
            ),

            const SizedBox(height: 24),
            // Antrean offline
            Card(
              child: ListTile(
                leading: Icon(
                  state.queueCount > 0
                      ? Icons.cloud_upload_rounded
                      : Icons.cloud_done_rounded,
                  color: state.queueCount > 0 ? scheme.primary : scheme.onSurfaceVariant,
                ),
                title: const Text('Antrean Offline'),
                subtitle: Text(
                  state.queueCount > 0
                      ? '${state.queueCount} menunggu sinkron'
                      : 'Tidak ada antrean',
                ),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => context.push('/queue'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmLogout(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Keluar?'),
        content: const Text('Anda akan keluar dari aplikasi.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await ref.read(authControllerProvider.notifier).logout();
    }
  }
}

class _ModeSelector extends StatelessWidget {
  const _ModeSelector({required this.mode, required this.onChanged});
  final ScanTime mode;
  final ValueChanged<ScanTime> onChanged;

  @override
  Widget build(BuildContext context) {
    return SegmentedButton<ScanTime>(
      segments: const [
        ButtonSegment(
          value: ScanTime.masuk,
          label: Text('Masuk'),
          icon: Icon(Icons.login_rounded),
        ),
        ButtonSegment(
          value: ScanTime.pulang,
          label: Text('Pulang'),
          icon: Icon(Icons.logout_rounded),
        ),
      ],
      selected: {mode},
      onSelectionChanged: (s) => onChanged(s.first),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.color, required this.icon, required this.text});
  final Color color;
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(text, style: TextStyle(color: color, fontSize: 13)),
          ),
        ],
      ),
    );
  }
}
