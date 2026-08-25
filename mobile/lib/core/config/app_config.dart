/// Konfigurasi aplikasi tingkat build.
///
/// **Base URL tidak pernah di-hardcode.** Nilainya ditentukan saat build lewat
/// `--dart-define`, jadi APK yang sama bisa diarahkan ke mana saja tanpa
/// mengubah kode:
///
/// ```
/// # Emulator Android (alias host-loopback ke localhost komputer)
/// flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///
/// # Perangkat fisik via USB (butuh: adb reverse tcp:8000 tcp:8000)
/// flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
///
/// # Perangkat fisik via WiFi (backend harus --host=0.0.0.0)
/// flutter run --dart-define=API_BASE_URL=http://192.168.1.19:8000/api/v1
///
/// # PRODUKSI — pakai domain + HTTPS, bukan IP
/// flutter build apk --release \
///   --dart-define=API_BASE_URL=https://api.sekolah.sch.id/api/v1
/// ```
///
/// Untuk beberapa lingkungan sekaligus (dev/staging/prod), pakai
/// `--dart-define-from-file=env/prod.json` agar tak perlu mengetik ulang.
///
/// Default di bawah sengaja diarahkan ke emulator: itu satu-satunya target
/// yang aman ditebak saat pengembangan. Build rilis **wajib** menyertakan
/// `--dart-define`, kalau tidak aplikasi akan menunjuk ke alamat emulator
/// yang tak berarti apa-apa di perangkat pengguna.
class AppConfig {
  const AppConfig._();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  /// True bila memakai default pengembangan — dipakai untuk memperingatkan
  /// diri sendiri kalau build rilis lupa menyertakan `--dart-define`.
  static bool get isUsingDevDefault =>
      const bool.fromEnvironment('dart.vm.product') &&
      baseUrl.contains('10.0.2.2');

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
