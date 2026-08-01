import 'package:dio/dio.dart';

/// Error API yang sudah dinormalkan menjadi satu bentuk.
///
/// Backend punya dua bentuk error:
///  - Envelope aplikasi: `{ "success": false, "message": "...", "errors": {...} }`
///  - Validasi FormRequest (Laravel): `{ "message": "...", "errors": {field:[...]} }`
/// Keduanya dipetakan ke kelas ini.
class ApiException implements Exception {
  ApiException({
    required this.message,
    this.statusCode,
    this.errors,
    this.isNetwork = false,
  });

  final String message;
  final int? statusCode;
  final Map<String, List<String>>? errors;
  final bool isNetwork;

  bool get isUnauthorized => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isValidation => statusCode == 422;
  bool get isRateLimited => statusCode == 429;

  /// Pesan error pertama untuk sebuah field (dipakai form).
  String? fieldError(String field) => errors?[field]?.first;

  @override
  String toString() => 'ApiException($statusCode): $message';

  /// Bangun dari DioException, memilih pesan yang paling berguna.
  factory ApiException.fromDio(DioException e) {
    // Kegagalan koneksi / timeout → tak ada respons.
    if (e.response == null) {
      final network = e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout ||
          e.type == DioExceptionType.sendTimeout ||
          e.type == DioExceptionType.connectionError;
      return ApiException(
        message: network
            ? 'Tidak dapat terhubung ke server. Periksa koneksi Anda.'
            : (e.message ?? 'Terjadi kesalahan tak terduga.'),
        isNetwork: network,
      );
    }

    final status = e.response!.statusCode;
    final data = e.response!.data;

    String message = 'Terjadi kesalahan.';
    Map<String, List<String>>? errors;

    if (data is Map) {
      if (data['message'] is String && (data['message'] as String).isNotEmpty) {
        message = data['message'] as String;
      }
      final rawErrors = data['errors'];
      if (rawErrors is Map) {
        errors = rawErrors.map((key, value) {
          final list = value is List
              ? value.map((e) => e.toString()).toList()
              : <String>[value.toString()];
          return MapEntry(key.toString(), list);
        });
      }
    }

    // Fallback pesan berdasarkan status bila server tak memberi message.
    if (message == 'Terjadi kesalahan.') {
      switch (status) {
        case 401:
          message = 'Sesi Anda telah berakhir. Silakan masuk kembali.';
          break;
        case 403:
          message = 'Anda tidak memiliki akses untuk tindakan ini.';
          break;
        case 429:
          message = 'Terlalu banyak permintaan. Coba lagi beberapa saat.';
          break;
        case 500:
        case 503:
          message = 'Server sedang bermasalah. Coba lagi nanti.';
          break;
      }
    }

    return ApiException(message: message, statusCode: status, errors: errors);
  }
}
