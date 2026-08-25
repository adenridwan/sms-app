import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/models/user.dart';

/// Menyimpan bukti kredensial akun-akun yang pernah login online di perangkat
/// ini, supaya login tetap bisa dilakukan saat server mati.
///
/// Kenapa ini ada: aplikasi dipakai di gerbang sekolah yang sinyalnya putus-
/// putus. Sesi yang sudah berjalan memang bertahan lewat [UserCacheStore], tapi
/// begitu petugas keluar (atau aplikasi dipasang ulang) ia terkunci di layar
/// login sampai server hidup — padahal justru saat itulah absensi harus jalan.
///
/// **Menyimpan banyak akun, bukan satu.** Versi pertama hanya menyimpan akun
/// terakhir; akibatnya satu perangkat jaga yang dipakai bergantian hanya bisa
/// dimasuki orang terakhir yang kebetulan login online — sisanya terkunci.
/// Perangkat di gerbang memang dipakai bergantian, jadi satu slot salah sejak
/// awal.
///
/// **Yang disimpan bukan passwordnya**, melainkan turunan PBKDF2-HMAC-SHA256
/// dengan salt acak per akun. Verifikasi offline membandingkan turunan, jadi
/// isi penyimpanan tidak bisa dipakai untuk login ke server.
///
/// Konsekuensi yang disengaja: catatan ini **tidak dihapus saat logout**. Tanpa
/// itu, keluar lalu masuk lagi saat offline mustahil — persis keluhan yang
/// membuat fitur ini dibuat. Ambang keamanannya tetap sama dengan login biasa
/// (penyerang harus tahu passwordnya), dan perangkatnya dilindungi kunci
/// menganggur 30 detik (`AppLockGate`).
class OfflineCredentialStore {
  static const _key = 'offline_credentials';

  /// Kunci versi lama (satu akun). Dibaca sekali untuk dipindahkan.
  static const _legacyKey = 'offline_credential';

  /// Jumlah iterasi PBKDF2. Disimpan bersama datanya supaya bisa dinaikkan
  /// nanti tanpa membuat catatan lama gagal diverifikasi.
  static const _iterations = 50000;

  /// Batas akun yang diingat per perangkat. Cukup untuk satu regu jaga, dan
  /// membatasi seberapa banyak yang bocor kalau perangkat hilang. Yang paling
  /// lama tak dipakai dibuang lebih dulu.
  static const _maxAccounts = 10;

  Future<void> save({
    required String email,
    required String password,
    required User user,
  }) async {
    final key = _normalize(email);
    final salt = _randomSalt();
    final all = await _readAll();

    all[key] = {
      'email': key,
      'salt': base64Encode(salt),
      'iterations': _iterations,
      'hash': base64Encode(_pbkdf2(password, salt, _iterations)),
      'user': user.toJson(),
      'saved_at': DateTime.now().toIso8601String(),
    };

    await _writeAll(_prune(all));
  }

  /// Mengembalikan profil tersimpan bila [email] + [password] cocok, atau
  /// `null` bila akun itu tak dikenal / passwordnya salah.
  Future<User?> verify({
    required String email,
    required String password,
  }) async {
    final all = await _readAll();
    final record = all[_normalize(email)];
    if (record == null) return null;

    final salt = base64Decode(record['salt'] as String);
    final iterations = (record['iterations'] as num).toInt();
    final expected = base64Decode(record['hash'] as String);

    if (!_constantTimeEquals(expected, _pbkdf2(password, salt, iterations))) {
      return null;
    }

    // Dipakai = disegarkan, supaya pemangkasan membuang yang benar-benar
    // terlupakan, bukan yang kebetulan jarang login online.
    record['saved_at'] = DateTime.now().toIso8601String();
    await _writeAll(all);

    try {
      return User.fromJson(Map<String, dynamic>.from(record['user'] as Map));
    } catch (_) {
      return null;
    }
  }

  /// Email yang punya catatan di perangkat ini — dipakai pesan galat untuk
  /// menyebutkan akun mana saja yang bisa masuk offline.
  Future<List<String>> knownEmails() async {
    final all = await _readAll();
    return all.keys.toList()..sort();
  }

  /// Lupakan satu akun (mis. petugas pindah tugas).
  Future<void> forget(String email) async {
    final all = await _readAll();
    all.remove(_normalize(email));
    await _writeAll(all);
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
    await prefs.remove(_legacyKey);
  }

  // ---------------------------------------------------------------- internal

  static String _normalize(String email) => email.trim().toLowerCase();

  Future<Map<String, Map<String, dynamic>>> _readAll() async {
    final prefs = await SharedPreferences.getInstance();
    final out = <String, Map<String, dynamic>>{};

    final raw = prefs.getString(_key);
    if (raw != null && raw.isNotEmpty) {
      try {
        final decoded = jsonDecode(raw) as Map;
        for (final e in decoded.entries) {
          out[e.key.toString()] = Map<String, dynamic>.from(e.value as Map);
        }
      } catch (_) {
        // Catatan rusak → anggap kosong, jangan sampai login ikut gagal.
      }
    }

    // Pindahkan catatan format lama sekali, supaya perangkat yang sudah
    // terpasang tidak kehilangan kemampuan masuk offline setelah pembaruan.
    final legacy = prefs.getString(_legacyKey);
    if (legacy != null && legacy.isNotEmpty) {
      try {
        final one = Map<String, dynamic>.from(jsonDecode(legacy) as Map);
        final email = one['email']?.toString();
        if (email != null && email.isNotEmpty && !out.containsKey(email)) {
          out[email] = one..putIfAbsent('saved_at', () => '');
        }
      } catch (_) {
        // abaikan
      }
      await prefs.remove(_legacyKey);
      await _write(prefs, out);
    }

    return out;
  }

  Future<void> _writeAll(Map<String, Map<String, dynamic>> all) async {
    final prefs = await SharedPreferences.getInstance();
    await _write(prefs, all);
  }

  Future<void> _write(
    SharedPreferences prefs,
    Map<String, Map<String, dynamic>> all,
  ) =>
      prefs.setString(_key, jsonEncode(all));

  /// Sisakan [_maxAccounts] yang paling belakangan dipakai.
  Map<String, Map<String, dynamic>> _prune(
    Map<String, Map<String, dynamic>> all,
  ) {
    if (all.length <= _maxAccounts) return all;

    final entries = all.entries.toList()
      ..sort((a, b) => (b.value['saved_at'] ?? '')
          .toString()
          .compareTo((a.value['saved_at'] ?? '').toString()));

    return {for (final e in entries.take(_maxAccounts)) e.key: e.value};
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
