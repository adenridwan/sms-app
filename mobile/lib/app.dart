import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/config/app_config.dart';
import 'core/network/backend_probe.dart';
import 'core/routing/app_router.dart';
import 'core/routing/deep_link_handler.dart';
import 'core/security/app_lock_gate.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_controller.dart';

class SmsApp extends ConsumerWidget {
  const SmsApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Menyalakan pemeriksa jangkauan server. Harus ditonton dari widget yang
    // hidup selama aplikasi hidup, supaya timer-nya tidak ikut mati.
    ref.watch(backendProbeProvider);

    // Mendengarkan deep link (smsapp://provision?token=xxx).
    ref.watch(deepLinkHandlerProvider);

    final router = ref.watch(routerProvider);
    final themeMode = ref.watch(themeModeProvider);
    return MaterialApp.router(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: themeMode,
      routerConfig: router,
      // Kunci menganggur dipasang lewat `builder`, di atas seluruh rute —
      // termasuk layar penuh seperti kamera pindai yang berada di luar shell.
      builder: (context, child) =>
          AppLockGate(child: child ?? const SizedBox.shrink()),
    );
  }
}
