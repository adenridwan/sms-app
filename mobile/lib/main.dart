import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/config/app_config.dart';
import 'core/services/heartbeat_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Load server URL dari storage (dari QR provisioning sebelumnya, jika ada).
  await AppConfig.loadServerUrl();

  // Inisialisasi device info untuk heartbeat (versi app, model perangkat, dll).
  await HeartbeatService.initDeviceInfo();

  runApp(
    const ProviderScope(child: SmsApp()),
  );
}
