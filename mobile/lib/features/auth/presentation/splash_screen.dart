import 'package:flutter/material.dart';

import '../../../core/config/app_config.dart';

/// Layar transisi saat menentukan sesi (status auth == unknown).
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: scheme.primary,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Icon(Icons.qr_code_scanner_rounded,
                  color: scheme.onPrimary, size: 32),
            ),
            const SizedBox(height: 20),
            Text(AppConfig.appName,
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.4),
            ),
            const SizedBox(height: 10),
            Text('Memeriksa sesi…',
                style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}
