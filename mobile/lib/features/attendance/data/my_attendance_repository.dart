import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/database/sources/sources.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/providers.dart';
import '../../home/data/dashboard_repository.dart';
import '../models/my_attendance.dart';

/// Akses "Absensi Saya" dengan offline-first pattern.
class MyAttendanceRepository {
  MyAttendanceRepository({
    required this.dio,
    required this.local,
    required this.ref,
  });

  final Dio dio;
  final AttendanceLocalSource local;
  final Ref ref;

  bool get _isOnline => ref.read(backendStatusProvider) == BackendStatus.online;

  /// Get today's attendance with offline-first pattern.
  Future<MyAttendanceToday> today(String userId) async {
    final todayStr = _todayString();

    // Try cache first.
    final cached = await local.getMyAttendanceToday(userId, todayStr);

    if (cached != null) {
      // Have cache - refresh in background if stale.
      if (_isOnline) {
        final isStale = await local.isStale(userId, todayStr);
        if (isStale) {
          _refreshToday(userId, todayStr);
        }
      }
      return cached;
    }

    // No cache.
    if (!_isOnline) {
      // Return empty state for offline display.
      return const MyAttendanceToday(status: 'offline');
    }

    return await _fetchAndCacheToday(userId, todayStr);
  }

  /// Force refresh today's attendance.
  Future<MyAttendanceToday> refreshToday(String userId) async {
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak dapat memuat ulang saat offline.',
        isNetwork: true,
      );
    }

    return await _fetchAndCacheToday(userId, _todayString());
  }

  /// Get attendance history.
  Future<List<MyAttendanceDay>> history(String userId, {DateTime? month}) async {
    // Try cache first.
    final cached = await local.getAttendanceHistory(userId);

    if (cached.isNotEmpty) {
      if (_isOnline) {
        // Refresh in background.
        _refreshHistory(userId, month);
      }
      return cached;
    }

    if (!_isOnline) {
      return const [];
    }

    return await _fetchAndCacheHistory(userId, month);
  }

  Future<MyAttendanceToday> _fetchAndCacheToday(
    String userId,
    String dateStr,
  ) async {
    try {
      final res = await dio.get('/attendance/me/today');
      final data = (res.data as Map)['data'];
      final attendance =
          MyAttendanceToday.fromJson(Map<String, dynamic>.from(data as Map));

      await local.saveMyAttendanceToday(userId, dateStr, attendance);
      return attendance;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<MyAttendanceDay>> _fetchAndCacheHistory(
    String userId,
    DateTime? month,
  ) async {
    final m = month ?? DateTime.now();
    try {
      final res = await dio.get('/attendance/me/history', queryParameters: {
        'month':
            '${m.year.toString().padLeft(4, '0')}-${m.month.toString().padLeft(2, '0')}',
      });
      final data = (res.data as Map)['data'];
      final list = data is Map ? data['history'] : data;
      if (list is! List) return const [];

      final history = list
          .whereType<Map>()
          .map((e) => MyAttendanceDay.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      await local.saveAttendanceHistory(userId, history);
      return history;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  void _refreshToday(String userId, String dateStr) {
    _fetchAndCacheToday(userId, dateStr).ignore();
  }

  void _refreshHistory(String userId, DateTime? month) {
    _fetchAndCacheHistory(userId, month).ignore();
  }

  static String _todayString() {
    final now = DateTime.now();
    return '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
  }
}

final myAttendanceRepositoryProvider = Provider<MyAttendanceRepository>(
  (ref) => MyAttendanceRepository(
    dio: ref.watch(dioProvider),
    local: ref.watch(attendanceLocalSourceProvider),
    ref: ref,
  ),
);

/// Kartu "kode presensi Anda" + status hari ini.
final myAttendanceTodayProvider =
    FutureProvider.autoDispose.family<MyAttendanceToday, String?>(
  (ref, userId) {
    if (userId == null) {
      return Future.value(const MyAttendanceToday(status: 'belum_scan'));
    }
    return ref.watch(myAttendanceRepositoryProvider).today(userId);
  },
);

final myAttendanceHistoryProvider =
    FutureProvider.autoDispose.family<List<MyAttendanceDay>, String?>(
  (ref, userId) {
    if (userId == null) return Future.value(const []);
    return ref.watch(myAttendanceRepositoryProvider).history(userId);
  },
);

/// Yang ditonton layar.
final myAttendanceProvider =
    Provider.autoDispose<AsyncValue<MyAttendanceToday>>(
  (ref) =>
      ref.watch(myAttendanceTodayProvider(ref.watch(currentUserIdProvider))),
);

final myHistoryProvider =
    Provider.autoDispose<AsyncValue<List<MyAttendanceDay>>>(
  (ref) =>
      ref.watch(myAttendanceHistoryProvider(ref.watch(currentUserIdProvider))),
);

/// Refresh my attendance.
void invalidateMyAttendance(WidgetRef ref) {
  final id = ref.read(currentUserIdProvider);
  ref.invalidate(myAttendanceTodayProvider(id));
  ref.invalidate(myAttendanceHistoryProvider(id));
}
