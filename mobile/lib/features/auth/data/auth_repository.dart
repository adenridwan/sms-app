import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/token_storage.dart';
import '../../../core/storage/user_cache_store.dart';
import '../models/user.dart';

/// Akses data autentikasi ke API v1 (`/auth/*`).
///
/// Semua kegagalan Dio dinormalkan menjadi [ApiException].
class AuthRepository {
  AuthRepository({
    required Dio dio,
    required TokenStorage tokenStorage,
    required UserCacheStore userCache,
  })  : _dio = dio,
        _tokenStorage = tokenStorage,
        _userCache = userCache;

  final Dio _dio;
  final TokenStorage _tokenStorage;
  final UserCacheStore _userCache;

  /// POST /auth/login → simpan token + cache user, kembalikan user.
  Future<User> login({
    required String email,
    required String password,
    bool remember = true,
  }) async {
    try {
      final res = await _dio.post('/auth/login', data: {
        'email': email,
        'password': password,
        'remember': remember,
      });
      final data = (res.data as Map)['data'] as Map;
      final token = data['token'] as String;
      await _tokenStorage.save(token);
      final user =
          User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
      await _userCache.save(user);
      return user;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// POST /auth/login-otp → masuk dengan kode akses sekali-pakai yang
  /// digenerate administrator (dipakai saat lupa password / perangkat baru).
  Future<User> loginWithOtp({
    required String email,
    required String code,
  }) async {
    try {
      final res = await _dio.post('/auth/login-otp', data: {
        'email': email,
        'code': code,
      });
      final data = (res.data as Map)['data'] as Map;
      final token = data['token'] as String;
      await _tokenStorage.save(token);
      final user =
          User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
      await _userCache.save(user);
      return user;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// GET /auth/me → user lengkap dengan roles + permissions. Cache lokal
  /// diperbarui tiap kali panggilan ini sukses (dipakai bila offline nanti).
  Future<User> me() async {
    try {
      final res = await _dio.get('/auth/me');
      final data = (res.data as Map)['data'] as Map;
      final userJson = Map<String, dynamic>.from(data['user'] as Map);
      final permissions =
          (data['permissions'] as List?)?.map((e) => e.toString()).toList();
      final roles =
          (data['roles'] as List?)?.map((e) => e.toString()).toList();
      final user =
          User.fromJson(userJson, permissions: permissions, roles: roles);
      await _userCache.save(user);
      return user;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// User dari cache lokal (potret terakhir), tanpa panggilan jaringan.
  Future<User?> cachedUser() => _userCache.load();

  /// POST /auth/logout → cabut token di server, lalu hapus lokal.
  /// Kegagalan jaringan tetap membersihkan token lokal (token kadaluarsa
  /// sendiri dalam 7 hari).
  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } on DioException {
      // abaikan; tetap hapus token lokal
    } finally {
      await _tokenStorage.clear();
      await _userCache.clear();
    }
  }

  Future<String?> currentToken() => _tokenStorage.read();

  Future<void> clearToken() async {
    await _tokenStorage.clear();
    await _userCache.clear();
  }
}
