import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';

/// Membangun instance [Dio] yang sudah dikonfigurasi:
///  - base URL & timeout dari [AppConfig]
///  - header `Accept: application/json`
///  - menyisipkan `Authorization: Bearer <token>` dari [TokenStorage] tiap request
Dio buildDio(TokenStorage tokenStorage) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.baseUrl,
      connectTimeout: AppConfig.connectTimeout,
      receiveTimeout: AppConfig.receiveTimeout,
      headers: {'Accept': 'application/json'},
      // Default: 2xx sukses, selain itu melempar DioException (dengan response
      // terlampir) yang ditangkap repository → ApiException.fromDio.
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await tokenStorage.read();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
    ),
  );

  return dio;
}
