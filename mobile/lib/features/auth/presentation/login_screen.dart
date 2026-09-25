import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/theme_mode_button.dart';
import '../../../core/auth/local_session_controller.dart';
import 'auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  bool _remember = true;
  bool _obscure = true;
  bool _useOtp = false;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    final email = _emailCtrl.text.trim();
    final notifier = ref.read(authControllerProvider.notifier);

    if (_useOtp) {
      await notifier.loginWithOtp(email: email, code: _codeCtrl.text.trim());
      return;
    }

    // Perangkat yang belum tersambung ke sekolah dicoba lewat akun lokal
    // lebih dulu. Urutan ini disengaja: di perangkat seperti itu tidak ada
    // alamat server yang berarti, jadi menembak /auth/login duluan hanya
    // menghabiskan waktu tunggu sebelum gagal.
    if (ref.read(canUseLocalAccountProvider)) {
      final ok = await ref.read(localSessionProvider.notifier).localLogin(
            email: email,
            password: _passwordCtrl.text,
          );
      if (ok) return;
    }

    await notifier.login(
      email: email,
      password: _passwordCtrl.text,
      remember: _remember,
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final scheme = Theme.of(context).colorScheme;
    final canUseLocal = ref.watch(canUseLocalAccountProvider);
    final busy = auth.isBusy;

    // Tampilkan error sebagai SnackBar saat berubah.
    ref.listen<AuthState>(authControllerProvider, (prev, next) {
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(next.error!)));
      }
    });

    // Galat dari jalur akun lokal punya salurannya sendiri.
    ref.listen<LocalSession>(localSessionProvider, (prev, next) {
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(next.error!)));
        ref.read(localSessionProvider.notifier).clearError();
      }
    });

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        actions: const [
          Center(child: BackendStatusDot()),
          SizedBox(width: 12),
          ThemeModeButton(),
          SizedBox(width: 4),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Kotak aksen kecil + nama aplikasi — penanda merek pada
                    // rujukan desain, menggantikan ikon besar sebelumnya.
                    Row(
                      children: [
                        Container(width: 14, height: 14, color: scheme.primary),
                        const SizedBox(width: 10),
                        const Text(
                          AppConfig.appName,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -.2,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 28),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Masuk',
                            style: Theme.of(context).textTheme.headlineMedium),
                        // QR Scan icon untuk login via provisioning
                        Container(
                          decoration: BoxDecoration(
                            color: scheme.primaryContainer,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: IconButton(
                            // `/connect-scan`, bukan `/provision-scan`: yang
                            // pertama ikut memasang alamat server dari QR,
                            // sedangkan yang kedua mengandalkan alamat yang
                            // sudah terpasang — dan di perangkat baru alamat
                            // itu belum ada, sehingga penebusan tertuju ke
                            // server yang salah.
                            onPressed: busy ? null : () => context.push('/connect-scan'),
                            tooltip: 'Scan QR Koneksi',
                            icon: Icon(
                              Icons.qr_code_scanner_rounded,
                              color: scheme.primary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text('Gunakan akun sekolah Anda',
                        style: Theme.of(context)
                            .textTheme
                            .bodyMedium
                            ?.copyWith(color: scheme.onSurfaceVariant)),
                    const SizedBox(height: 28),
                    TextFormField(
                      controller: _emailCtrl,
                      enabled: !busy,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        prefixIcon: Icon(Icons.alternate_email_rounded),
                      ),
                      validator: (v) {
                        final s = (v ?? '').trim();
                        if (s.isEmpty) return 'Email wajib diisi';
                        if (!s.contains('@')) return 'Format email tidak valid';
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    if (_useOtp)
                      TextFormField(
                        controller: _codeCtrl,
                        enabled: !busy,
                        keyboardType: TextInputType.number,
                        textInputAction: TextInputAction.done,
                        maxLength: 6,
                        onFieldSubmitted: (_) => busy ? null : _submit(),
                        decoration: const InputDecoration(
                          labelText: 'Kode akses (6 digit)',
                          helperText: 'Minta kode ke administrator sekolah',
                          prefixIcon: Icon(Icons.pin_rounded),
                          counterText: '',
                        ),
                        validator: (v) {
                          final s = (v ?? '').trim();
                          if (s.isEmpty) return 'Kode akses wajib diisi';
                          if (s.length != 6) return 'Kode akses harus 6 digit';
                          return null;
                        },
                      )
                    else
                      TextFormField(
                        controller: _passwordCtrl,
                        enabled: !busy,
                        obscureText: _obscure,
                        textInputAction: TextInputAction.done,
                        autofillHints: const [AutofillHints.password],
                        onFieldSubmitted: (_) => busy ? null : _submit(),
                        decoration: InputDecoration(
                          labelText: 'Password',
                          prefixIcon: const Icon(Icons.lock_outline_rounded),
                          suffixIcon: IconButton(
                            onPressed: () =>
                                setState(() => _obscure = !_obscure),
                            icon: Icon(_obscure
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined),
                          ),
                        ),
                        validator: (v) {
                          if ((v ?? '').isEmpty) return 'Password wajib diisi';
                          if ((v ?? '').length < 6) {
                            return 'Password minimal 6 karakter';
                          }
                          return null;
                        },
                      ),
                    if (!_useOtp) ...[
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Checkbox(
                            value: _remember,
                            onChanged: busy
                                ? null
                                : (v) =>
                                    setState(() => _remember = v ?? true),
                          ),
                          const Text('Ingat saya'),
                        ],
                      ),
                    ],
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: busy ? null : _submit,
                      child: busy
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Masuk'),
                    ),
                    const SizedBox(height: 4),
                    TextButton(
                      onPressed: busy
                          ? null
                          : () => setState(() => _useOtp = !_useOtp),
                      child: Text(_useOtp
                          ? 'Masuk dengan password'
                          : 'Masuk dengan kode akses'),
                    ),

                    // Hanya selama perangkat belum tersambung ke sekolah.
                    // Setelah itu akun lokal tidak berlaku, jadi menawarkannya
                    // cuma mengundang orang membuat akun yang langsung ditolak.
                    if (canUseLocal) ...[
                      const SizedBox(height: 18),
                      const Divider(),
                      const SizedBox(height: 10),
                      Text(
                        'Belum terhubung ke sekolah mana pun. Anda bisa '
                        'membuat akun lokal untuk menyiapkan dan menguji '
                        'perangkat ini lebih dulu.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 11.5,
                          height: 1.45,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton.icon(
                        onPressed:
                            busy ? null : () => context.push('/signup'),
                        icon: const Icon(Icons.person_add_alt_1_outlined,
                            size: 18),
                        label: const Text('Buat Akun Lokal'),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
