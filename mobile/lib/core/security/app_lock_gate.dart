import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../config/app_config.dart';
import '../providers.dart';
import '../theme/ui_kit.dart';
import 'app_lock.dart';
import 'biometric_service.dart';
import 'biometric_settings.dart';

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
      _resolveLaunch(ref.read(authControllerProvider).status);
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Nasib sesi saat aplikasi dibuka sudah diputuskan.
  bool _launchResolved = false;

  /// Putuskan perlakuan untuk sesi yang **dipulihkan** saat aplikasi dibuka.
  ///
  /// Harus dipisah dari login biasa. Sesi yang dipulihkan dari token tersimpan
  /// belum membuktikan apa pun, jadi ia yang dikenai "minta biometrik saat
  /// dibuka"; orang yang baru saja mengetik password atau memindai QR sudah
  /// membuktikan dirinya sedetik lalu, dan mengunci dia seketika hanya akan
  /// terasa seperti kerusakan.
  ///
  /// Status bisa masih `unknown` di frame pertama karena `_bootstrap()`
  /// menunggu `/auth/me`. Karena itu keputusannya ditunda sampai status
  /// benar-benar terisi — tanpa ini, setelan tersebut terlewat persis pada
  /// pembukaan aplikasi yang menjadi alasan keberadaannya.
  void _resolveLaunch(AuthStatus status) {
    if (_launchResolved || status == AuthStatus.unknown) return;

    if (status != AuthStatus.authenticated) {
      // Selesai tanpa sesi: login setelah ini dihitung login baru.
      _launchResolved = true;
      return;
    }

    // Setelan biometrik juga dibaca dari disk dan bisa belum siap.
    final settings = ref.read(biometricSettingsProvider);
    if (!settings.loaded) {
      late final ProviderSubscription<BiometricSettings> sub;
      sub = ref.listenManual(biometricSettingsProvider, (_, next) {
        if (!next.loaded) return;
        sub.close();
        if (mounted) _applyLaunchPolicy(next);
      });
      return;
    }

    _applyLaunchPolicy(settings);
  }

  /// Kunci langsung bila diminta, selain itu mulai hitungan menganggur.
  void _applyLaunchPolicy(BiometricSettings settings) {
    _launchResolved = true;

    if (settings.requireOnLaunch) {
      ref.read(appLockProvider.notifier).lock();
    } else {
      ref.read(appLockProvider.notifier).poke();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final lock = ref.read(appLockProvider.notifier);

    if (state == AppLifecycleState.resumed) {
      // Waktu di latar belakang ikut dihitung: HP yang tergeletak di saku
      // selama lima menit sama rawannya dengan yang tergeletak di meja.
      final since = _pausedAt;
      _pausedAt = null;
      if (since == null || !ref.read(authControllerProvider).isAuthenticated) {
        return;
      }

      // Latar belakang yang barusan terjadi adalah dialog biometriknya
      // sendiri, bukan pengguna meninggalkan aplikasi.
      if (lock.justUnlocked) return;

      // Dengan "minta biometrik saat dibuka", kembali dari latar selalu
      // mengunci — tak peduli sebentarnya. Tanpa itu, hanya lewat batas
      // menganggur yang mengunci.
      final always = ref.read(biometricSettingsProvider).requireOnLaunch;
      if (always ||
          DateTime.now().difference(since) >= AppConfig.idleLockTimeout) {
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
    ref.listen<AuthStatus>(
      authControllerProvider.select((s) => s.status),
      (_, next) {
        if (next != AuthStatus.authenticated) {
          if (next != AuthStatus.unknown) _launchResolved = true;
          ref.read(appLockProvider.notifier).stop();
          return;
        }

        // Sesi yang muncul sebelum pembukaan diputuskan berarti sesi
        // pulihan — itulah yang boleh dikunci sejak awal.
        if (!_launchResolved) {
          _resolveLaunch(next);
        } else {
          ref.read(appLockProvider.notifier).poke();
        }
      },
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

  /// Biometrik sudah ditawarkan sekali untuk penguncian ini.
  ///
  /// Dialog sistem dimunculkan otomatis saat layar kunci tampil — itu yang
  /// diharapkan orang dari kunci biometrik. Tapi hanya **sekali**: kalau yang
  /// gagal langsung ditawari lagi, pengguna terjebak dalam dialog yang tak
  /// bisa ditutup untuk beralih ke password.
  bool _biometricOffered = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _tryBiometric(auto: true));
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  /// Buka kunci dengan sidik jari / wajah.
  ///
  /// Tidak ada password yang dicocokkan di sini, dan memang tidak perlu:
  /// sesinya belum berakhir — yang diminta cuma bukti bahwa pemegang perangkat
  /// masih orang yang sama.
  Future<void> _tryBiometric({bool auto = false}) async {
    final settings = ref.read(biometricSettingsProvider);
    if (!settings.unlockEnabled) return;
    if (auto && _biometricOffered) return;
    _biometricOffered = true;

    final outcome = await ref.read(biometricServiceProvider).authenticate(
          reason: 'Buka kunci aplikasi absensi',
        );

    if (!mounted) return;

    switch (outcome) {
      case BiometricOutcome.success:
        _ctrl.clear();
        ref.read(appLockProvider.notifier).unlock();
      case BiometricOutcome.failed:
        // Diam saja saat tawaran otomatis: layar passwordnya sudah terlihat,
        // dan memerahkannya tanpa pengguna melakukan apa pun cuma bikin cemas.
        if (!auto) {
          setState(() => _error = 'Tidak dikenali. Coba lagi atau '
              'masukkan password.');
        }
      case BiometricOutcome.unavailable:
        setState(() => _error = 'Biometrik sedang tidak bisa dipakai. '
            'Masukkan password.');
    }
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
    final biometricOn = ref.watch(biometricSettingsProvider).unlockEnabled;
    final capability = ref.watch(biometricCapabilityProvider).valueOrNull;

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
              if (biometricOn) ...[
                OutlinedButton.icon(
                  onPressed: _checking ? null : () => _tryBiometric(),
                  icon: const Icon(Icons.fingerprint_rounded, size: 22),
                  label: Text('Buka dengan ${capability?.label ?? 'Biometrik'}'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(child: Divider(color: scheme.outlineVariant)),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      child: Text(
                        'atau password',
                        style: TextStyle(
                          fontSize: 11,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ),
                    Expanded(child: Divider(color: scheme.outlineVariant)),
                  ],
                ),
                const SizedBox(height: 14),
              ],
              TextField(
                controller: _ctrl,
                obscureText: true,
                // Papan ketik tidak dimunculkan saat biometrik menyala —
                // dialog sistem sedang tampil di atasnya, dan naiknya papan
                // ketik di belakang dialog membuat layar terlihat kacau.
                autofocus: !biometricOn,
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
