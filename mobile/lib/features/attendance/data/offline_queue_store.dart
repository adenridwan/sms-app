import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/queued_scan.dart';

/// Penyimpanan persisten antrean scan offline (SharedPreferences, JSON list).
class OfflineQueueStore {
  static const _key = 'offline_scans';

  Future<List<QueuedScan>> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return [];
    final list = (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
    return list.map(QueuedScan.fromJson).toList();
  }

  Future<void> _save(List<QueuedScan> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _key,
      jsonEncode(items.map((e) => e.toJson()).toList()),
    );
  }

  Future<List<QueuedScan>> add(QueuedScan scan) async {
    final items = await load()..add(scan);
    await _save(items);
    return items;
  }

  Future<List<QueuedScan>> removeIds(Set<String> ids) async {
    final items = (await load()).where((e) => !ids.contains(e.id)).toList();
    await _save(items);
    return items;
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}
