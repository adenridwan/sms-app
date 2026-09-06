import 'dart:async';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:dio/dio.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../features/attendance/data/class_attendance_queue_store.dart';
import '../../features/attendance/data/offline_queue_store.dart';
import '../storage/token_storage.dart';

/// Service untuk mengirim heartbeat berkala ke server.
///
/// Heartbeat berisi informasi:
/// - Versi aplikasi & OS
/// - Level baterai & status charging (via Android/iOS API)
/// - Tipe koneksi (WiFi/mobile) & nama WiFi
/// - Jumlah scan yang belum tersinkron
///
/// Interval default: 30 detik. Heartbeat gagal (offline) diabaikan — tidak
/// ditampilkan ke user, tidak mempengaruhi fungsionalitas lain.
class HeartbeatService {
  HeartbeatService({
    required Dio dio,
    required TokenStorage tokenStorage,
    required OfflineQueueStore offlineQueueStore,
    required ClassAttendanceQueueStore classQueueStore,
  })  : _dio = dio,
        _tokenStorage = tokenStorage,
        _offlineQueueStore = offlineQueueStore,
        _classQueueStore = classQueueStore;

  final Dio _dio;
  final TokenStorage _tokenStorage;
  final OfflineQueueStore _offlineQueueStore;
  final ClassAttendanceQueueStore _classQueueStore;

  Timer? _timer;
  final NetworkInfo _networkInfo = NetworkInfo();

  /// Device info — di-cache sekali saat start, tidak berubah selama app jalan.
  static String? _appVersion;
  static String? _osVersion;
  static String? _deviceModel;

  /// Interval heartbeat dalam detik.
  static const int _intervalSeconds = 30;

  /// Inisialisasi device info. Panggil sekali di main() sebelum runApp.
  static Future<void> initDeviceInfo() async {
    try {
      final packageInfo = await PackageInfo.fromPlatform();
      _appVersion = packageInfo.version;

      final deviceInfo = DeviceInfoPlugin();
      if (Platform.isAndroid) {
        final android = await deviceInfo.androidInfo;
        _osVersion = 'Android ${android.version.release}';
        _deviceModel = '${android.brand} ${android.model}';
      } else if (Platform.isIOS) {
        final ios = await deviceInfo.iosInfo;
        _osVersion = 'iOS ${ios.systemVersion}';
        _deviceModel = ios.model;
      }
    } catch (_) {
      // Fallback — device info tidak kritikal, app tetap jalan.
    }
  }

  /// Mulai mengirim heartbeat berkala.
  /// Panggil setelah login berhasil.
  void start() {
    // Kirim heartbeat pertama segera
    _sendHeartbeat();

    // Lalu kirim berkala
    _timer?.cancel();
    _timer = Timer.periodic(
      const Duration(seconds: _intervalSeconds),
      (_) => _sendHeartbeat(),
    );
  }

  /// Hentikan heartbeat.
  /// Panggil saat logout.
  void stop() {
    _timer?.cancel();
    _timer = null;
  }

  /// Kirim heartbeat ke server.
  Future<void> _sendHeartbeat() async {
    // Skip jika tidak ada token (belum login / login offline)
    final token = await _tokenStorage.read();
    if (token == null || token.isEmpty) return;

    try {
      final data = await _collectDeviceInfo();

      await _dio.post(
        '/devices/heartbeat',
        data: data,
      );
    } catch (e) {
      // Heartbeat gagal tidak perlu ditampilkan ke user.
      // ignore: avoid_print
      // print('[Heartbeat] Failed: $e');
    }
  }

  /// Kumpulkan informasi device.
  Future<Map<String, dynamic>> _collectDeviceInfo() async {
    // Koneksi
    String? networkType;
    String? networkName;
    int? latencyMs;

    try {
      final connectivity = await Connectivity().checkConnectivity();
      if (connectivity.contains(ConnectivityResult.wifi)) {
        networkType = 'wifi';
        networkName = await _networkInfo.getWifiName();
        // Hapus tanda kutip jika ada (Android)
        networkName = networkName?.replaceAll('"', '');
      } else if (connectivity.contains(ConnectivityResult.mobile)) {
        networkType = 'mobile';
      } else if (connectivity.contains(ConnectivityResult.ethernet)) {
        networkType = 'ethernet';
      } else {
        networkType = 'none';
      }

      // Ukur latency dengan ping ke server
      final stopwatch = Stopwatch()..start();
      try {
        await _dio.head('/ping').timeout(const Duration(seconds: 5));
        stopwatch.stop();
        latencyMs = stopwatch.elapsedMilliseconds;
      } catch (_) {
        // Ping gagal — latency tidak tersedia
      }
    } catch (_) {}

    // Pending sync count (gabungan scan tunggal + absen kelas)
    int pendingSyncCount = 0;
    try {
      final scans = await _offlineQueueStore.load();
      final classAttendances = await _classQueueStore.load();
      pendingSyncCount = scans.length + classAttendances.length;
    } catch (_) {}

    return {
      'app_version': _appVersion,
      'os_version': _osVersion,
      'device_model': _deviceModel,
      // Battery level dikirim null — Flutter tidak punya API baterai bawaan,
      // dan battery_plus terlalu berat untuk dependency baru. Server tetap
      // bisa menampilkan status online tanpa info baterai.
      'battery_level': null,
      'battery_charging': false,
      'network_type': networkType,
      'network_name': networkName,
      'latency_ms': latencyMs,
      'pending_sync_count': pendingSyncCount,
    };
  }
}
