import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/providers.dart';
import '../auth_controller.dart';

/// `true` bila akun yang sedang masuk **belum** punya catatan kredensial
/// offline di perangkat ini.
///
/// Diturunkan dari isi [OfflineCredentialStore], bukan dari penanda "sudah
/// pernah ditawarkan": yang penting bukan apakah tawarannya pernah muncul,
/// melainkan apakah perangkat ini benar-benar bisa dipakai masuk saat server
/// mati. Masuk lewat QR tidak pernah mengetik password, jadi jawabannya `true`
/// sampai password itu disimpan sekali.
final needsOfflinePasswordProvider = FutureProvider<bool>((ref) async {
  final auth = ref.watch(authControllerProvider);
  final email = auth.user?.email;
  if (!auth.isAuthenticated || email == null || email.isEmpty) return false;

  final known = await ref.watch(authRepositoryProvider).offlineEmails();
  return !known.contains(email.trim().toLowerCase());
});

/// Tawarkan menyimpan password sekolah supaya perangkat ini bisa dipakai masuk
/// saat offline.
///
/// Muncul setelah masuk lewat QR, karena jalur itu **tidak pernah** meminta
/// password — tanpa langkah ini tidak ada yang bisa diverifikasi ketika server
/// tak terjangkau, dan petugas di gerbang tanpa sinyal akan terkunci di layar
/// login. Sengaja bisa dilewati: yang buru-buru mengabsen tidak boleh ditahan,
/// dan password yang sama bisa diketik pada login berikutnya.
///
/// Yang disimpan adalah password **server**, bukan password baru: ia
/// diverifikasi ke `/auth/login` lebih dulu, lalu di-hash PBKDF2 secara lokal.
Future<void> showOfflinePasswordSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => const _OfflinePasswordSheet(),
  );
}

class _OfflinePasswordSheet extends ConsumerStatefulWidget {
  const _OfflinePasswordSheet();

  @override
  ConsumerState<_OfflinePasswordSheet> createState() =>
      _OfflinePasswordSheetState();
}

class _OfflinePasswordSheetState extends ConsumerState<_OfflinePasswordSheet> {
  final _ctrl = TextEditingController();
  bool _obscure = true;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_ctrl.text.isEmpty) {
      setState(() => _error = 'Password wajib diisi.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    final error = await ref
        .read(authControllerProvider.notifier)
        .rememberPasswordForOffline(_ctrl.text);

    if (!mounted) return;

    if (error != null) {
      setState(() {
        _busy = false;
        _error = error;
      });
      return;
    }

    ref.invalidate(needsOfflinePasswordProvider);

    Navigator.of(context).pop();
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(const SnackBar(
        content: Text('Tersimpan. Perangkat ini bisa dipakai masuk '
            'walau server tidak terjangkau.'),
      ));
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final email = ref.watch(authControllerProvider).user?.email ?? '';

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        20 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Masuk saat offline',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 6),
          Text(
            'Anda sudah masuk. Password belum pernah diketik di perangkat ini '
            'karena masuknya lewat QR — simpan sekarang bila ingin tetap bisa '
            'masuk saat server tidak terjangkau.',
            style: TextStyle(
              fontSize: 12.5,
              height: 1.45,
              color: scheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 16),
          if (email.isNotEmpty) ...[
            Text(
              email,
              style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
          ],
          TextField(
            controller: _ctrl,
            enabled: !_busy,
            obscureText: _obscure,
            autofocus: true,
            onSubmitted: (_) => _busy ? null : _save(),
            decoration: InputDecoration(
              labelText: 'Password sekolah',
              errorText: _error,
              prefixIcon: const Icon(Icons.lock_outline),
              suffixIcon: IconButton(
                onPressed: () => setState(() => _obscure = !_obscure),
                icon: Icon(_obscure
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined),
              ),
            ),
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _busy ? null : _save,
            child: _busy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2.2, color: Colors.white),
                  )
                : const Text('Simpan'),
          ),
          TextButton(
            onPressed: _busy ? null : () => Navigator.of(context).pop(),
            child: const Text('Nanti saja'),
          ),
        ],
      ),
    );
  }
}
