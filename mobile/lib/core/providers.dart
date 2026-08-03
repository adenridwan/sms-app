import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/auth/data/auth_repository.dart';
import 'network/api_client.dart';
import 'network/api_exception.dart';
import 'network/backend_status.dart';
import 'storage/token_storage.dart';
import 'storage/user_cache_store.dart';

/// Penyimpanan token (secure storage).
final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

/// Cache lokal profil user (dipakai untuk sesi offline-tolerant).
final userCacheStoreProvider =
    Provider<UserCacheStore>((ref) => UserCacheStore());

/// Instance Dio terkonfigurasi (base URL, header, Bearer interceptor, +
/// pelaporan status backend ke [backendStatusProvider] tiap request).
final dioProvider = Provider<Dio>((ref) {
  final storage = ref.watch(tokenStorageProvider);
  final dio = buildDio(storage);
  final status = ref.read(backendStatusProvider.notifier);

  dio.interceptors.add(
    InterceptorsWrapper(
      onResponse: (response, handler) {
        status.markOnline();
        handler.next(response);
      },
      onError: (error, handler) {
        if (ApiException.isNetworkError(error)) {
          status.markOffline();
        } else {
          // Server menjawab (walau dengan status error) → backend terjangkau.
          status.markOnline();
        }

        // 401 dari endpoint terproteksi = token dicabut/kedaluwarsa. Bersihkan
        // sesi di satu tempat, supaya user tak terjebak di layar yang seolah
        // masih login (mis. admin mencabut sesi lewat menu Keamanan Login).
        //
        // Endpoint login dikecualikan: di sana 401 berarti "kredensial salah",
        // bukan sesi kedaluwarsa — alur login sendiri yang menampilkannya.
        final path = error.requestOptions.path;
        final isLoginAttempt =
            path.contains('/auth/login') || path.contains('/auth/login-otp');

        if (error.response?.statusCode == 401 && !isLoginAttempt) {
          ref.read(unauthorizedSignalProvider.notifier).fire();
        }

        handler.next(error);
      },
    ),
  );

  return dio;
});

/// Repository autentikasi.
final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    dio: ref.watch(dioProvider),
    tokenStorage: ref.watch(tokenStorageProvider),
    userCache: ref.watch(userCacheStoreProvider),
  );
});
