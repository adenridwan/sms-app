import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/queued_class_attendance.dart';

/// Penyimpanan persisten antrean **absen kelas** offline.
///
/// Kunci penyimpanannya terpisah dari `offline_scans` (antrean scan tunggal)
/// karena bentuk datanya berbeda: satu entri di sini memuat seisi kelas.
class ClassAttendanceQueueStore {
  static const _key = 'offline_class_attendance';

  Future<List<QueuedClassAttendance>> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return [];
    try {
      return (jsonDecode(raw) as List)
          .whereType<Map>()
          .map((e) =>
              QueuedClassAttendance.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      // Data korup/format lama — jangan sampai app gagal start karenanya.
      return [];
    }
  }

  Future<void> _save(List<QueuedClassAttendance> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _key,
      jsonEncode(items.map((e) => e.toJson()).toList()),
    );
  }

  /// Menyimpan satu sesi. Entri untuk **kelas + tanggal yang sama** digantikan,
  /// bukan ditumpuk: mengoreksi absen yang sama dua kali saat offline harus
  /// menyisakan satu entri terbaru, bukan dua yang saling bertentangan.
  Future<List<QueuedClassAttendance>> add(QueuedClassAttendance item) async {
    final items = (await load())
        .where((e) => !(e.classroomId == item.classroomId &&
            _sameDay(e.date, item.date)))
        .toList()
      ..add(item);
    await _save(items);
    return items;
  }

  Future<List<QueuedClassAttendance>> removeIds(Set<String> ids) async {
    final items = (await load()).where((e) => !ids.contains(e.id)).toList();
    await _save(items);
    return items;
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }

  static bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}
