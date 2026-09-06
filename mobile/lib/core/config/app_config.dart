import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Konfigurasi aplikasi tingkat build + runtime.
///
/// **Base URL bisa dari dua sumber:**
/// 1. Build-time via `--dart-define=API_BASE_URL=...` (default)
/// 2. Runtime via QR provisioning (disimpan ke secure storage)
///
/// ```
/// # Emulator Android (alias host-loopback ke localhost komputer)
/// flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///
/// # Perangkat fisik via WiFi (backend harus --host=0.0.0.0)
/// flutter run --dart-define=API_BASE_URL=http://192.168.1.19:8000/api/v1
///
/// # PRODUKSI — pakai domain + HTTPS, bukan IP
/// flutter build apk --release \
///   --dart-define=API_BASE_URL=https://api.sekolah.sch.id/api/v1
/// ```
class AppConfig {
  const AppConfig._();

  static const _storage = FlutterSecureStorage();
  static const _serverUrlKey = 'provisioned_server_url';

  /// URL default dari build-time.
  static const String _defaultBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  /// URL runtime dari QR provisioning (null jika belum pernah provisioning).
  static String? _runtimeBaseUrl;

  /// API Base URL yang aktif.
  /// Prioritas: runtime (dari QR) > build-time (dari --dart-define).
  static String get baseUrl => _runtimeBaseUrl ?? _defaultBaseUrl;

  /// True jika URL saat ini dari QR provisioning (bukan default).
  static bool get isProvisioned => _runtimeBaseUrl != null;

  /// True bila memakai default pengembangan — dipakai untuk memperingatkan
  /// diri sendiri kalau build rilis lupa menyertakan `--dart-define`.
  static bool get isUsingDevDefault =>
      const bool.fromEnvironment('dart.vm.product') &&
      baseUrl.contains('10.0.2.2');

  /// Load server URL dari storage (panggil di main() sebelum runApp).
  static Future<void> loadServerUrl() async {
    _runtimeBaseUrl = await _storage.read(key: _serverUrlKey);
  }

  /// Set server URL dari QR provisioning.
  /// URL akan disimpan dan dipakai untuk semua request berikutnya.
  static Future<void> setServerUrl(String url) async {
    // Normalize: hapus trailing slash
    final normalized = url.endsWith('/') ? url.substring(0, url.length - 1) : url;
    _runtimeBaseUrl = normalized;
    await _storage.write(key: _serverUrlKey, value: normalized);
  }

  /// Reset ke URL default (hapus provisioning).
  static Future<void> resetServerUrl() async {
    _runtimeBaseUrl = null;
    await _storage.delete(key: _serverUrlKey);
  }

  static const String appName = 'SMS Absensi';

  /// Berapa lama menganggur sebelum aplikasi mengunci diri.
  ///
  /// Perangkat membawa daftar siswa, kode QR, dan antrean absensi. Kunci layar
  /// OS tidak bisa dipaksa dari aplikasi, jadi batas ini yang dipegang sendiri.
  /// 30 detik memang ketat — itu keputusan sadar, bukan default framework.
  static const Duration idleLockTimeout = Duration(seconds: 30);

  /// Timeout jaringan.
  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 20);
}
