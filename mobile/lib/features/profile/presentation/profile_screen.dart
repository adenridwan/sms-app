import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/theme_controller.dart';
import '../../auth/presentation/auth_controller.dart';

/// Tab Profil: identitas, peran, status koneksi, tema, versi, keluar.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final backend = ref.watch(backendStatusProvider);
    final mode = ref.watch(themeModeProvider);
    final scheme = Theme.of(context).colorScheme;
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil'),
        actions: const [
          Center(child: BackendStatusDot()),
          SizedBox(width: 16),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: scheme.primary,
                child: Text(
                  _initials(user?.fullName ?? '?'),
                  style: TextStyle(
                    color: scheme.onPrimary,
                    fontWeight: FontWeight.w700,
                    fontSize: 18,
                  ),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(user?.fullName ?? 'Petugas',
                        style: Theme.of(context).textTheme.titleMedium),
                    if (user?.email != null)
                      Text(user!.email,
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(color: scheme.onSurfaceVariant)),
                  ],
                ),
              ),
            ],
          ),

          if (user != null && user.roles.isNotEmpty) ...[
            const SizedBox(height: 16),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final role in user.roles)
                  Chip(
                    label: Text(role, style: const TextStyle(fontSize: 11)),
                    visualDensity: VisualDensity.compact,
                  ),
              ],
            ),
          ],

          const SizedBox(height: 24),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.dns_rounded),
                  title: const Text('Status server'),
                  subtitle: Text(_backendLabel(backend, auth.isSessionVerified)),
                  trailing: const BackendStatusDot(size: 12),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: Icon(mode.icon),
                  title: const Text('Tema'),
                  subtitle: Text(mode.label),
                  trailing: PopupMenuButton<ThemeMode>(
                    icon: const Icon(Icons.chevron_right_rounded),
                    onSelected: (m) =>
                        ref.read(themeModeProvider.notifier).setMode(m),
                    itemBuilder: (context) => [
                      for (final m in ThemeMode.values)
                        PopupMenuItem<ThemeMode>(
                          value: m,
                          child: Row(
                            children: [
                              Icon(m.icon, size: 20),
                              const SizedBox(width: 12),
                              Text(m.label),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                const Divider(height: 1),
                const ListTile(
                  leading: Icon(Icons.info_outline_rounded),
                  title: Text('Aplikasi'),
                  subtitle: Text(AppConfig.appName),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: () => _confirmLogout(context, ref),
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Keluar'),
            style: OutlinedButton.styleFrom(foregroundColor: scheme.error),
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

  static String _backendLabel(BackendStatus status, bool verified) {
    if (status == BackendStatus.offline) return 'Tidak terhubung';
    if (status == BackendStatus.unknown) return 'Belum diperiksa';
    return verified ? 'Terhubung' : 'Terhubung — menyinkronkan sesi';
  }

  Future<void> _confirmLogout(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Keluar'),
        content: const Text(
          'Yakin keluar dari aplikasi? Antrean scan offline tidak akan terhapus.',
        ),
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
