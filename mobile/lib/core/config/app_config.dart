/// Konfigurasi aplikasi tingkat build.
///
/// Base URL bisa dioverride saat build/run:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8080/api/v1
///
/// Default `10.0.2.2` adalah alias host-loopback untuk **emulator Android**
/// (menunjuk ke `localhost` mesin pengembang). Untuk perangkat fisik, isi IP
/// LAN backend lewat --dart-define.
class AppConfig {
  const AppConfig._();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8080/api/v1',
  );

  static const String appName = 'SMS Absensi';

  /// Timeout jaringan.
  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 20);
}
