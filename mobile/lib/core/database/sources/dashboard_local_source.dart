import 'dart:convert';

import 'package:drift/drift.dart';

import '../../../features/home/models/dashboard_stats.dart';
import '../database.dart';

/// Local data source for dashboard data.
class DashboardLocalSource {
  DashboardLocalSource(this._db);

  final AppDatabase _db;

  /// Staleness threshold for dashboard cache.
  static const staleDuration = Duration(hours: 1);

  /// Get cached dashboard stats.
  Future<DashboardStats?> getDashboardStats(String userId) async {
    final row = await _db.getDashboardCache(userId);
    if (row == null) return null;
    try {
      final json = jsonDecode(row.jsonData) as Map<String, dynamic>;
      return DashboardStats.fromJson(json);
    } catch (_) {
      return null;
    }
  }

  /// Check if dashboard cache is stale.
  Future<bool> isStale(String userId) async {
    final row = await _db.getDashboardCache(userId);
    if (row == null) return true;
    return DateTime.now().difference(row.cachedAt) > staleDuration;
  }

  /// Get cache age for UI display.
  Future<Duration?> getCacheAge(String userId) async {
    final row = await _db.getDashboardCache(userId);
    if (row == null) return null;
    return DateTime.now().difference(row.cachedAt);
  }

  /// Save dashboard stats to cache.
  Future<void> saveDashboardStats(
    String userId,
    DashboardStats stats,
    Map<String, dynamic> rawJson,
  ) async {
    await _db.upsertDashboardCache(DashboardCacheCompanion(
      userId: Value(userId),
      package: Value(stats.package),
      jsonData: Value(jsonEncode(rawJson)),
      cachedAt: Value(DateTime.now()),
    ));
  }

  /// Clear dashboard cache for user.
  Future<void> clearCache(String userId) async {
    await _db.clearDashboardCache(userId);
  }
}
