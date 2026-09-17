import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/config/app_config.dart';
import 'core/database/database.dart';
import 'core/database/database_providers.dart';
import 'core/database/migration_helper.dart';
import 'core/services/heartbeat_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Load server URL dari storage (dari QR provisioning sebelumnya, jika ada).
  await AppConfig.loadServerUrl();

  // Inisialisasi device info untuk heartbeat (versi app, model perangkat, dll).
  await HeartbeatService.initDeviceInfo();

  // Migrasi data dari SharedPreferences ke SQLite (sekali saat update).
  final db = AppDatabase();
  final migration = MigrationHelper(db);
  final result = await migration.migrate();
  if (result.hasMigrated) {
    debugPrint('Migration complete: ${result.totalMigrated} items migrated');
  }

  runApp(
    ProviderScope(
      overrides: [
        // Gunakan database yang sudah diinisialisasi dan di-migrasi.
        databaseProvider.overrideWithValue(db),
      ],
      child: const SmsApp(),
    ),
  );
}
