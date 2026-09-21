import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Penjelasan saat pengguna menekan menu yang belum bisa dipakai.
///
/// Dipakai menggantikan kartu yang diam saja: menekan ikon lalu tidak terjadi
/// apa-apa membuat orang mengira aplikasinya rusak. Lembar ini menyebut
/// alasannya dan — untuk kasus "belum terhubung" — menyediakan jalan keluarnya
/// langsung, bukan menyuruh pengguna mencari sendiri.
enum BlockedReason {
  /// Perangkat belum dihubungkan ke server sekolah.
  notConnected,

  /// Modul memang belum dibangun.
  notBuilt,
}

Future<void> showBlockedMenuSheet(
  BuildContext context, {
  required String title,
  required BlockedReason reason,
}) {
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (ctx) => _BlockedSheet(title: title, reason: reason),
  );
}

class _BlockedSheet extends StatelessWidget {
  const _BlockedSheet({required this.title, required this.reason});

  final String title;
  final BlockedReason reason;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final connect = reason == BlockedReason.notConnected;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        24,
        4,
        24,
        24 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: scheme.primaryContainer,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              connect ? Icons.cloud_off_rounded : Icons.construction_rounded,
              color: scheme.primary,
              size: 24,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            connect ? 'Hubungkan ke server dulu' : '$title belum tersedia',
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              letterSpacing: -.3,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            connect
                ? 'Menu $title membutuhkan data dari sekolah — daftar siswa, '
                    'kelas, dan jadwal. Perangkat ini belum terhubung, jadi '
                    'belum ada yang bisa ditampilkan.\n\n'
                    'Minta QR Koneksi ke administrator sekolah, lalu pindai di '
                    'sini. Setelah itu menu ini terbuka sesuai peran akun Anda.'
                : 'Menu ini sudah direncanakan tapi belum dibangun. Ia '
                    'ditampilkan supaya peta fitur terbaca utuh, bukan karena '
                    'sudah bisa dipakai.',
            style: TextStyle(
              fontSize: 13,
              height: 1.5,
              color: scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 22),
          if (connect)
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () {
                  Navigator.pop(context);
                  context.push('/connect-scan');
                },
                icon: const Icon(Icons.qr_code_scanner_rounded, size: 18),
                label: const Text('Scan QR Koneksi'),
              ),
            ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Tutup'),
            ),
          ),
        ],
      ),
    );
  }
}
