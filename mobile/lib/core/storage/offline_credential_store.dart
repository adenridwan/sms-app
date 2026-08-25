import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/models/user.dart';

/// Menyimpan bukti kredensial **satu akun terakhir** yang pernah login online
/// di perangkat ini, supaya login tetap bisa dilakukan saat server mati.
///
/// Kenapa ini ada: aplikasi dipakai di gerbang sekolah yang sinyalnya putus-
/// putus. Sesi yang sudah berjalan memang bertahan lewat [UserCacheStore], tapi
/// begitu petugas keluar (atau aplikasi dipasang ulang) ia terkunci di layar
/// login sampai server hidup — padahal justru saat itulah absensi harus jalan.
///
/// **Yang disimpan bukan passwordnya**, melainkan turunan PBKDF2-HMAC-SHA256
/// dengan salt acak per perangkat. Verifikasi offline membandingkan turunan,
/// jadi isi penyimpanan tidak bisa dipakai untuk login ke server.
///
/// Konsekuensi yang disengaja: catatan ini **tidak dihapus saat logout**. Tanpa
/// itu, keluar lalu masuk lagi saat offline mustahil — persis keluhan yang
/// membuat fitur ini dibuat. Ambang keamanannya tetap sama dengan login biasa:
/// penyerang tetap harus tahu passwordnya.
class OfflineCredentialStore {
  static const _key = 'offline_credential';

  /// Jumlah iterasi PBKDF2. Disimpan bersama datanya supaya bisa dinaikkan
  /// nanti tanpa membuat catatan lama gagal diverifikasi.
  static const _iterations = 50000;

  Future<void> save({
    required String email,
    required String password,
    required User user,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final salt = _randomSalt();
    final hash = _pbkdf2(password, salt, _iterations);

    await prefs.setString(
      _key,
      jsonEncode({
        'email': email.trim().toLowerCase(),
        'salt': base64Encode(salt),
        'iterations': _iterations,
        'hash': base64Encode(hash),
        'user': user.toJson(),
      }),
    );
  }

  /// Mengembalikan profil tersimpan bila [email] + [password] cocok, atau
  /// `null` bila tidak ada catatan / tidak cocok.
  Future<User?> verify({
    required String email,
    required String password,
  }) async {
    final record = await _read();
    if (record == null) return null;
    if (record['email'] != email.trim().toLowerCase()) return null;

    final salt = base64Decode(record['salt'] as String);
    final iterations = (record['iterations'] as num).toInt();
    final expected = base64Decode(record['hash'] as String);
    final actual = _pbkdf2(password, salt, iterations);

    if (!_constantTimeEquals(expected, actual)) return null;

    try {
      return User.fromJson(Map<String, dynamic>.from(record['user'] as Map));
    } catch (_) {
      return null;
    }
  }

  /// Email akun yang tersimpan — dipakai layar login untuk mengisi awal field
  /// dan menjelaskan akun mana yang bisa masuk offline.
  Future<String?> knownEmail() async => (await _read())?['email'] as String?;

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }

  Future<Map<String, dynamic>?> _read() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return null;
    try {
      return Map<String, dynamic>.from(jsonDecode(raw) as Map);
    } catch (_) {
      // Catatan rusak/format lama → anggap tidak ada.
      return null;
    }
  }

  static Uint8List _randomSalt([int length = 16]) {
    final rng = Random.secure();
    return Uint8List.fromList(
      List<int>.generate(length, (_) => rng.nextInt(256)),
    );
  }

  /// PBKDF2-HMAC-SHA256, satu blok (32 byte) — cukup untuk verifikasi.
  static Uint8List _pbkdf2(String password, List<int> salt, int iterations) {
    final hmac = Hmac(sha256, utf8.encode(password));

    // Blok pertama: salt diikuti indeks blok big-endian (1).
    var block = hmac.convert([...salt, 0, 0, 0, 1]).bytes;
    final result = List<int>.from(block);

    for (var i = 1; i < iterations; i++) {
      block = hmac.convert(block).bytes;
      for (var j = 0; j < result.length; j++) {
        result[j] ^= block[j];
      }
    }
    return Uint8List.fromList(result);
  }

  /// Perbandingan berwaktu tetap — mencegah bocornya informasi lewat lama
  /// eksekusi. Berlebihan untuk perangkat pribadi, tapi murah.
  static bool _constantTimeEquals(List<int> a, List<int> b) {
    if (a.length != b.length) return false;
    var diff = 0;
    for (var i = 0; i < a.length; i++) {
      diff |= a[i] ^ b[i];
    }
    return diff == 0;
  }
}
