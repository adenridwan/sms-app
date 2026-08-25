import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../config/app_config.dart';
import '../providers.dart';
import '../theme/ui_kit.dart';
import 'app_lock.dart';

/// Membungkus seluruh aplikasi: menghitung waktu menganggur, dan menutupi layar
/// dengan permintaan password begitu batasnya lewat.
///
/// Dipasang di atas router (bukan per layar) supaya tak ada satu pun rute yang
/// bisa lolos dari kunci — termasuk kamera pindai dan layar antrean yang dibuka
/// di atas shell.
class AppLockGate extends ConsumerStatefulWidget {
  const AppLockGate({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<AppLockGate> createState() => _AppLockGateState();
}

class _AppLockGateState extends ConsumerState<AppLockGate>
    with WidgetsBindingObserver {
  DateTime? _pausedAt;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Sesi bisa sudah aktif sebelum gate ini dibangun (mis. dipulihkan dari
    // token tersimpan), jadi hitungan dimulai sekali di sini.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (ref.read(authControllerProvider).isAuthenticated) {
        ref.read(appLockProvider.notifier).poke();
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final lock = ref.read(appLockProvider.notifier);

    if (state == AppLifecycleState.resumed) {
      // Waktu di latar belakang ikut dihitung: HP yang tergeletak di saku
      // selama lima menit sama rawannya dengan yang tergeletak di meja.
      final since = _pausedAt;
      _pausedAt = null;
      if (since != null &&
          DateTime.now().difference(since) >= AppConfig.idleLockTimeout &&
          ref.read(authControllerProvider).isAuthenticated) {
        lock.lock();
      }
      return;
    }

    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      _pausedAt ??= DateTime.now();
    }
  }

  @override
  Widget build(BuildContext context) {
    final authed = ref.watch(
      authControllerProvider.select((s) => s.isAuthenticated),
    );
    final locked = ref.watch(appLockProvider);

    // Hitungan hanya berjalan selama ada sesi. Di layar login tak ada yang
    // perlu dilindungi, dan mengunci layar login hanya membuat orang terjebak.
    ref.listen<bool>(
      authControllerProvider.select((s) => s.isAuthenticated),
      (_, next) => next
          ? ref.read(appLockProvider.notifier).poke()
          : ref.read(appLockProvider.notifier).stop(),
    );

    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) {
        if (authed) ref.read(appLockProvider.notifier).poke();
      },
      child: Stack(
        children: [
          widget.child,
          if (authed && locked) const _LockScreen(),
        ],
      ),
    );
  }
}

/// Layar kunci. Bukan layar login: sesi tetap ada, yang diminta hanya bukti
/// bahwa yang memegang perangkat masih orang yang sama.
class _LockScreen extends ConsumerStatefulWidget {
  const _LockScreen();

  @override
  ConsumerState<_LockScreen> createState() => _LockScreenState();
}

class _LockScreenState extends ConsumerState<_LockScreen> {
  final _ctrl = TextEditingController();
  bool _checking = false;
  String? _error;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _unlock() async {
    final user = ref.read(authControllerProvider).user;
    if (user == null || _ctrl.text.isEmpty) return;

    setState(() {
      _checking = true;
      _error = null;
    });

    // Diverifikasi terhadap catatan PBKDF2 lokal, bukan ke server: kunci ini
    // justru paling sering dibuka di tempat yang tak ada sinyalnya.
    final ok = await ref.read(offlineCredentialStoreProvider).verify(
          email: user.email,
          password: _ctrl.text,
        );

    if (!mounted) return;
    if (ok != null) {
      _ctrl.clear();
      ref.read(appLockProvider.notifier).unlock();
      return;
    }

    // Tanpa catatan kredensial, tak ada yang bisa dicocokkan — bilang apa
    // adanya, jangan menuduh passwordnya salah.
    final known = await ref.read(offlineCredentialStoreProvider).knownEmails();
    if (!mounted) return;

    setState(() {
      _checking = false;
      _error = known.contains(user.email.trim().toLowerCase())
          ? 'Password salah.'
          : 'Perangkat ini belum menyimpan bukti kredensial untuk akun Anda. '
              'Keluar lalu masuk sekali saat online untuk mengaktifkan kunci.';
    });
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final user = ref.watch(authControllerProvider).user;

    return Material(
      color: scheme.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(width: 14, height: 14, color: scheme.primary),
                  const SizedBox(width: 10),
                  const Text(
                    'TERKUNCI',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.2,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                user?.fullName ?? 'Sesi Anda',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 6),
              Text(
                'Aplikasi mengunci diri setelah menganggur. Masukkan password '
                'untuk melanjutkan — antrean absensi Anda tetap aman.',
                style: TextStyle(
                  fontSize: 12.5,
                  height: 1.45,
                  color: scheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 20),
              TextField(
                controller: _ctrl,
                obscureText: true,
                autofocus: true,
                onSubmitted: (_) => _unlock(),
                decoration: const InputDecoration(labelText: 'Password'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                InfoStrip(text: _error!, margin: EdgeInsets.zero),
              ],
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _checking ? null : _unlock,
                child: _checking
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2.4, color: Colors.white),
                      )
                    : const Text('Buka'),
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: () =>
                    ref.read(authControllerProvider.notifier).logout(),
                child: const Text('Keluar dari akun ini'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
