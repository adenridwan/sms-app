import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth_controller.dart';

/// Tanya dulu, baru keluar.
///
/// Dipakai bersama oleh Profil dan ikon keluar di Beranda. Satu tempat, karena
/// dua alur keluar yang berbeda-beda isinya adalah cara paling mudah membuat
/// salah satunya diam-diam lupa menyisakan antrean absensi.
///
/// Konfirmasinya **tidak** dihilangkan meski rujukan desain langsung keluar
/// saat ikonnya ditekan: di ponsel ikon sekecil itu di pojok kanan atas mudah
/// tersenggol, dan petugas yang tak sengaja keluar saat sinyal mati tidak bisa
/// masuk lagi sampai server terjangkau.
Future<void> confirmLogout(BuildContext context, WidgetRef ref) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Keluar'),
      content: const Text(
        'Yakin keluar dari aplikasi? Antrean scan offline tidak akan terhapus, '
        'dan koneksi ke server tetap tersimpan.',
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

  if (ok != true) return;

  // Catatan koneksi sekolah sengaja dibiarkan: keluar bukan berarti perangkat
  // ini berpindah sekolah, dan memutusnya akan memaksa minta QR baru ke admin
  // hanya untuk masuk kembali.
  await ref.read(authControllerProvider.notifier).logout();
}
