import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/theme_controller.dart';
import '../../../core/theme/ui_kit.dart';
import '../../attendance/presentation/scanner_home_screen.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../notifications/presentation/notification_controller.dart';

/// Tab Profil: identitas, peran, status koneksi, tema, versi, keluar.
///
/// Peran ditampilkan sebagai fakta akun (dari token login), bukan pilihan —
/// tidak ada pengalih peran di sini, karena izin ditentukan server.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final backend = ref.watch(backendStatusProvider);
    final mode = ref.watch(themeModeProvider);
    final unread = ref.watch(notificationControllerProvider).unreadCount;
    final scheme = Theme.of(context).colorScheme;
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil'),
        actions: const [
          Center(child: BackendStatusDot()),
          SizedBox(width: 20),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
        children: [
          // Kartu identitas gelap — sejajar dengan kartu ringkasan di Beranda.
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: scheme.onSurface,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Row(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: scheme.primary,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(
                    _initials(user?.fullName ?? '?'),
                    style: TextStyle(
                      color: scheme.onPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.fullName ?? 'Petugas',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -.4,
                          color: scheme.surface,
                        ),
                      ),
                      if (user?.email != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          user!.email,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 12,
                            color: scheme.surface.withValues(alpha: .68),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),

          if (user != null && user.roles.isNotEmpty) ...[
            const SizedBox(height: 20),
            const FieldLabel('Peran akun'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final role in user.roles)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: scheme.primaryContainer,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      role,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: scheme.onPrimaryContainer,
                      ),
                    ),
                  ),
              ],
            ),
          ],

          const SizedBox(height: 22),
          const FieldLabel('Pengaturan'),
          const SizedBox(height: 8),
          // Urutan baris mengikuti rujukan desain (`tabIsProfile`).
          Panel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _Row(
                  label: 'Absensi Saya',
                  value: 'Kode presensi & riwayat Anda',
                  onTap: () {
                    ref.read(attendanceMethodProvider.notifier).state =
                        AttendanceMethod.mine;
                    context.go('/attendance');
                  },
                  trailing: _chevron(scheme),
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                _Row(
                  label: 'Pengaturan & Sinkronisasi',
                  value: 'Kirim antrean ke server',
                  onTap: () => context.push('/settings'),
                  trailing: _chevron(scheme),
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                // Notifikasi tidak punya tab sendiri (rujukan memakai Keuangan
                // di posisi itu), jadi pintunya di sini.
                _Row(
                  label: 'Notifikasi',
                  value: unread > 0
                      ? '$unread belum dibaca'
                      : 'Tidak ada yang baru',
                  onTap: () => context.push('/notifications'),
                  trailing: _chevron(scheme),
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                _Row(
                  label: 'Tema',
                  value: mode.label,
                  trailing: PopupMenuButton<ThemeMode>(
                    icon: Icon(Icons.expand_more_rounded,
                        size: 20, color: scheme.onSurfaceVariant),
                    onSelected: (m) =>
                        ref.read(themeModeProvider.notifier).setMode(m),
                    itemBuilder: (context) => [
                      for (final m in ThemeMode.values)
                        PopupMenuItem<ThemeMode>(
                          value: m,
                          child: Row(
                            children: [
                              Icon(m.icon, size: 18),
                              const SizedBox(width: 12),
                              Text(m.label),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                // Rujukan menamainya "Masuk & Keamanan — Kode akses, sesi".
                // Menerbitkan kode akses dan mencabut sesi adalah wewenang
                // administratif; tempatnya di web, jadi di sini hanya status.
                _Row(
                  label: 'Masuk & Keamanan',
                  value: _backendLabel(backend, auth.isSessionVerified),
                  trailing: const BackendStatusDot(size: 10),
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                const _Row(label: 'Versi Aplikasi', value: AppConfig.appName),
              ],
            ),
          ),

          const SizedBox(height: 22),
          OutlinedButton(
            onPressed: () => _confirmLogout(context, ref),
            style: OutlinedButton.styleFrom(
              foregroundColor: scheme.primary,
              side: BorderSide(color: scheme.primary.withValues(alpha: .4)),
            ),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
  }

  static Widget _chevron(ColorScheme scheme) => Icon(
        Icons.arrow_forward_rounded,
        size: 18,
        color: scheme.onSurfaceVariant,
      );

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

/// Baris pengaturan: label kiri, nilai kanan — tanpa ikon pembuka, sesuai
/// rujukan yang mengandalkan tipografi untuk hierarki.
class _Row extends StatelessWidget {
  const _Row({
    required this.label,
    required this.value,
    this.trailing,
    this.onTap,
  });

  final String label;
  final String value;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final pad = trailing == null ? 14.0 : 8.0;

    final row = Padding(
      padding: EdgeInsets.fromLTRB(16, pad, 12, pad),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                        letterSpacing: -.1)),
                const SizedBox(height: 2),
                Text(value,
                    style: TextStyle(
                        fontSize: 11.5, color: scheme.onSurfaceVariant)),
              ],
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: 8),
            trailing!,
          ],
        ],
      ),
    );

    if (onTap == null) return row;
    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: onTap, child: row),
    );
  }
}
