import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/models/user.dart';

/// Cache lokal profil [User] (roles + permissions), dipakai untuk memulihkan
/// sesi saat app dibuka tanpa koneksi ke backend (lihat AuthController._bootstrap).
///
/// Bukan pengganti [TokenStorage]: token tetap satu-satunya yang membuktikan
/// sesi valid ke server; ini hanya "potret terakhir" data user agar UI tak
/// kosong selagi offline.
class UserCacheStore {
  static const _key = 'cached_user';

  Future<User?> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return null;
    try {
      return User.fromJson(Map<String, dynamic>.from(jsonDecode(raw) as Map));
    } catch (_) {
      // Cache korup/format lama → abaikan, jangan sampai app gagal start.
      return null;
    }
  }

  Future<void> save(User user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(user.toJson()));
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}
