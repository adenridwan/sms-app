import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/token_storage.dart';
import '../models/user.dart';

/// Akses data autentikasi ke API v1 (`/auth/*`).
///
/// Semua kegagalan Dio dinormalkan menjadi [ApiException].
class AuthRepository {
  AuthRepository({required Dio dio, required TokenStorage tokenStorage})
      : _dio = dio,
        _tokenStorage = tokenStorage;

  final Dio _dio;
  final TokenStorage _tokenStorage;

  /// POST /auth/login → simpan token, kembalikan user.
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
      return User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// GET /auth/me → user lengkap dengan roles + permissions.
  Future<User> me() async {
    try {
      final res = await _dio.get('/auth/me');
      final data = (res.data as Map)['data'] as Map;
      final userJson = Map<String, dynamic>.from(data['user'] as Map);
      final permissions =
          (data['permissions'] as List?)?.map((e) => e.toString()).toList();
      final roles =
          (data['roles'] as List?)?.map((e) => e.toString()).toList();
      return User.fromJson(userJson, permissions: permissions, roles: roles);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

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
    }
  }

  Future<String?> currentToken() => _tokenStorage.read();

  Future<void> clearToken() => _tokenStorage.clear();
}
