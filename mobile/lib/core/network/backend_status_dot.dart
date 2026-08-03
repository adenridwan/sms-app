import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';
import 'backend_status.dart';

/// Bulat kecil: status jangkauan backend + kesegaran sesi saat ini.
///
/// - **Hijau**: terhubung, sesi terverifikasi.
/// - **Kuning**: terhubung, tapi sesi masih dari cache lokal (belum sempat
///   re-verifikasi ke `/auth/me` — lihat `AuthState.isSessionVerified`).
/// - **Merah**: tidak terhubung ke server.
/// - **Abu-abu**: belum ada request sejak app dibuka.
class BackendStatusDot extends ConsumerWidget {
  const BackendStatusDot({super.key, this.size = 10});

  final double size;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(backendStatusProvider);
    final auth = ref.watch(authControllerProvider);
    final scheme = Theme.of(context).colorScheme;

    final unverifiedSession = auth.isAuthenticated && !auth.isSessionVerified;

    final Color color;
    final String label;
    if (status == BackendStatus.offline) {
      color = scheme.error;
      label = 'Tidak terhubung ke server';
    } else if (status == BackendStatus.online && unverifiedSession) {
      color = const Color(0xFFF59E0B);
      label = 'Terhubung, menyinkronkan ulang sesi…';
    } else if (status == BackendStatus.online) {
      color = const Color(0xFF22C55E);
      label = 'Terhubung ke server';
    } else {
      color = scheme.onSurfaceVariant.withValues(alpha: 0.4);
      label = unverifiedSession
          ? 'Belum terhubung — memakai sesi tersimpan'
          : 'Memeriksa koneksi server…';
    }

    return Tooltip(
      message: label,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
    );
  }
}
