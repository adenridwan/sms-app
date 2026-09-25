import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sms_mobile/core/security/biometric_settings.dart';

/// Menunggu controller selesai membaca SharedPreferences.
Future<BiometricSettings> _loaded(ProviderContainer c) async {
  if (c.read(biometricSettingsProvider).loaded) {
    return c.read(biometricSettingsProvider);
  }
  final done = Completer<BiometricSettings>();
  final sub = c.listen<BiometricSettings>(biometricSettingsProvider, (_, next) {
    if (next.loaded && !done.isCompleted) done.complete(next);
  });
  final result = await done.future;
  sub.close();
  return result;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ProviderContainer container;

  Future<void> boot(Map<String, Object> initial) async {
    SharedPreferences.setMockInitialValues(initial);
    container = ProviderContainer();
    addTearDown(container.dispose);
    await _loaded(container);
  }

  test('bawaannya mati, dan ditandai sudah terbaca', () async {
    await boot({});

    final s = container.read(biometricSettingsProvider);
    expect(s.unlockEnabled, isFalse);
    expect(s.requireOnLaunch, isFalse);
    expect(s.loaded, isTrue);
  });

  test('memulihkan pilihan yang tersimpan', () async {
    await boot({
      'biometric_unlock_enabled': true,
      'biometric_require_on_launch': true,
    });

    final s = container.read(biometricSettingsProvider);
    expect(s.unlockEnabled, isTrue);
    expect(s.requireOnLaunch, isTrue);
  });

  test('menyimpan perubahan ke disk', () async {
    await boot({});
    await container
        .read(biometricSettingsProvider.notifier)
        .setUnlockEnabled(true);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getBool('biometric_unlock_enabled'), isTrue);
  });

  test('mematikan biometrik ikut mematikan kunci-saat-dibuka', () async {
    // Kalau tidak, sakelar itu tinggal berarti "ketik password tiap membuka
    // aplikasi" — hukuman yang tidak pernah diminta siapa pun.
    await boot({
      'biometric_unlock_enabled': true,
      'biometric_require_on_launch': true,
    });

    await container
        .read(biometricSettingsProvider.notifier)
        .setUnlockEnabled(false);

    expect(container.read(biometricSettingsProvider).requireOnLaunch, isFalse);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getBool('biometric_require_on_launch'), isFalse);
  });

  test('kunci-saat-dibuka tidak bisa menyala tanpa biometrik', () async {
    await boot({});

    await container
        .read(biometricSettingsProvider.notifier)
        .setRequireOnLaunch(true);

    expect(container.read(biometricSettingsProvider).requireOnLaunch, isFalse);
  });
}
