import 'dart:async';

import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/offline_credential_store.dart';
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
    required OfflineCredentialStore offlineCredentials,
  })  : _dio = dio,
        _tokenStorage = tokenStorage,
        _userCache = userCache,
        _offline = offlineCredentials;

  final Dio _dio;
  final TokenStorage _tokenStorage;
  final UserCacheStore _userCache;
  final OfflineCredentialStore _offline;

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

  /// Simpan bukti kredensial supaya akun ini bisa masuk lagi saat server mati.
  /// Dipanggil hanya setelah login online benar-benar sukses.
  Future<void> rememberForOffline({
    required String email,
    required String password,
    required User user,
  }) =>
      _offline.save(email: email, password: password, user: user);

  /// Verifikasi kredensial terhadap catatan lokal; `null` bila tak cocok.
  Future<User?> verifyOffline({
    required String email,
    required String password,
  }) =>
      _offline.verify(email: email, password: password);

  /// Email yang punya catatan offline di perangkat ini.
  Future<List<String>> offlineEmails() => _offline.knownEmails();

  /// POST /auth/logout → cabut token di server, lalu hapus lokal.
  /// Kegagalan jaringan tetap membersihkan token lokal (token kadaluarsa
  /// sendiri dalam 7 hari).
  /// Keluar dari sesi.
  ///
  /// Sesi lokal dihapus **lebih dulu**, pemberitahuan ke server menyusul tanpa
  /// ditunggu. Urutan sebelumnya (server dulu, baru hapus) membuat tombol
  /// Keluar tampak mati sampai 15 detik — selama itu Dio menunggu
  /// `connectTimeout` — dan sama sekali tidak berfungsi saat offline. Keluar
  /// dari aplikasi tidak boleh bergantung pada jaringan.
  ///
  /// Token dibaca dulu sebelum dihapus, supaya permintaan pencabutan masih
  /// membawa `Authorization` (interceptor tak lagi menemukannya di storage).
  Future<void> logout() async {
    final token = await _tokenStorage.read();

    await _tokenStorage.clear();
    await _userCache.clear();

    if (token == null || token.isEmpty) return;
    unawaited(_revokeOnServer(token));
  }

  /// Mencabut token di server; kegagalan sengaja diabaikan.
  ///
  /// Kalau server tak terjangkau, token yang tertinggal akan kedaluwarsa
  /// sendiri — itu bukan alasan untuk menahan pengguna di dalam aplikasi.
  Future<void> _revokeOnServer(String token) async {
    try {
      await _dio.post(
        '/auth/logout',
        options: Options(headers: {'Authorization': 'Bearer $token'}),
      );
    } on DioException {
      // diabaikan dengan sengaja
    }
  }

  Future<String?> currentToken() => _tokenStorage.read();

  Future<void> clearToken() async {
    await _tokenStorage.clear();
    await _userCache.clear();
  }
}
