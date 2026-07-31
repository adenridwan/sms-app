import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../models/bootstrap_data.dart';
import '../models/queued_scan.dart';
import '../models/scan_result.dart';
import '../models/scan_time.dart';
import '../models/student_lookup.dart';

/// Akses API absensi/scanner (`/scan/*`).
class AttendanceRepository {
  AttendanceRepository(this._dio);
  final Dio _dio;

  /// GET /scan/bootstrap
  Future<BootstrapData> bootstrap() async {
    try {
      final res = await _dio.get('/scan/bootstrap');
      final data = (res.data as Map)['data'] as Map;
      return BootstrapData.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// POST /scan/lookup → identitas pemilik kode, atau null bila tidak ditemukan.
  Future<LookupResult?> lookup(String uniqueCode) async {
    try {
      final res = await _dio.post('/scan/lookup', data: {
        'unique_code': uniqueCode,
      });
      final data = (res.data as Map)['data'] as Map;
      return LookupResult.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      final ex = ApiException.fromDio(e);
      if (ex.statusCode == 404) return null; // kode tidak dikenal
      throw ex;
    }
  }

  /// POST /scan → hasil absensi.
  ///
  /// - Sukses / penolakan HTTP (mis. 422 "sudah absen") → dikembalikan sebagai
  ///   [ScanResult].
  /// - Kegagalan jaringan → melempar [ApiException] (isNetwork) agar pemanggil
  ///   dapat mengantre offline.
  Future<ScanResult> scan({
    required String uniqueCode,
    required ScanTime waktu,
    double? latitude,
    double? longitude,
  }) async {
    try {
      final res = await _dio.post('/scan', data: {
        'unique_code': uniqueCode,
        'waktu': waktu.api,
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      });
      return ScanResult.fromSuccess(Map<String, dynamic>.from(res.data as Map));
    } on DioException catch (e) {
      final ex = ApiException.fromDio(e);
      if (ex.isNetwork) throw ex;
      return ScanResult.failure(ex.message);
    }
  }

  /// POST /scan/sync-offline → kirim batch antrean.
  /// Mengembalikan map hasil: { total, success, failed, results:[...] }.
  Future<Map<String, dynamic>> syncOffline(List<QueuedScan> scans) async {
    try {
      final res = await _dio.post('/scan/sync-offline', data: {
        'scans': scans.map((s) => s.toSyncJson()).toList(),
      });
      final data = (res.data as Map)['data'] as Map;
      return Map<String, dynamic>.from(data);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
