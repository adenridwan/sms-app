import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/local_session_controller.dart';
import '../../../core/config/app_config.dart';
import '../../../core/theme/theme_mode_button.dart';

/// Login screen for local-first authentication.
///
/// Users login with local credentials stored on device.
/// Backend connection is separate (via QR scan after login).
class LocalLoginScreen extends ConsumerStatefulWidget {
  const LocalLoginScreen({super.key});

  @override
  ConsumerState<LocalLoginScreen> createState() => _LocalLoginScreenState();
}

class _LocalLoginScreenState extends ConsumerState<LocalLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    final success = await ref.read(localSessionProvider.notifier).login(
          email: _emailCtrl.text.trim(),
          password: _passwordCtrl.text,
        );

    if (success && mounted) {
      context.go('/');
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(localSessionProvider);
    final scheme = Theme.of(context).colorScheme;
    final busy = session.status == LocalSessionStatus.authenticating;

    // Show error
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
                    // Branding
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

                    Text(
                      'Masuk',
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Gunakan akun lokal Anda',
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium
                          ?.copyWith(color: scheme.onSurfaceVariant),
                    ),
                    const SizedBox(height: 28),

                    // Email
                    TextFormField(
                      controller: _emailCtrl,
                      enabled: !busy,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        prefixIcon: Icon(Icons.alternate_email),
                      ),
                      validator: (v) {
                        final s = (v ?? '').trim();
                        if (s.isEmpty) return 'Email wajib diisi';
                        if (!s.contains('@')) return 'Format email tidak valid';
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),

                    // Password
                    TextFormField(
                      controller: _passwordCtrl,
                      enabled: !busy,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => busy ? null : _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _obscure = !_obscure),
                          icon: Icon(_obscure
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined),
                        ),
                      ),
                      validator: (v) {
                        if ((v ?? '').isEmpty) return 'Password wajib diisi';
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),

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
                    const SizedBox(height: 12),

                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          'Belum punya akun? ',
                          style: TextStyle(color: scheme.onSurfaceVariant),
                        ),
                        TextButton(
                          onPressed: busy ? null : () => context.push('/signup'),
                          child: const Text('Buat Akun'),
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),
                    const Divider(),
                    const SizedBox(height: 16),

                    // QR Scan option
                    Text(
                      'Sudah ada akun dari sekolah?',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 12,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: busy ? null : () => context.push('/connect-scan'),
                      icon: const Icon(Icons.qr_code_scanner),
                      label: const Text('Scan QR Koneksi'),
                    ),
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
