import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/database/sources/sources.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../models/dashboard_stats.dart';

/// Akses `GET /dashboard` dengan offline-first pattern.
///
/// Data dimuat dari cache lokal terlebih dahulu untuk respons instan,
/// kemudian di-refresh dari server jika online dan cache sudah stale.
class DashboardRepository {
  DashboardRepository({
    required this.dio,
    required this.local,
    required this.ref,
  });

  final Dio dio;
  final DashboardLocalSource local;
  final Ref ref;

  bool get _isOnline => ref.read(backendStatusProvider) == BackendStatus.online;

  /// Fetch dashboard stats with offline-first pattern.
  ///
  /// 1. Return cached data immediately if available
  /// 2. If online and cache is stale, refresh in background
  /// 3. If no cache and online, fetch from server
  /// 4. If no cache and offline, throw error
  Future<DashboardStats> fetch(String userId) async {
    // Try to get cached data first.
    final cached = await local.getDashboardStats(userId);

    if (cached != null) {
      // Have cache - check if we should refresh in background.
      if (_isOnline) {
        final isStale = await local.isStale(userId);
        if (isStale) {
          // Fire and forget - don't wait for refresh.
          _refreshFromServer(userId);
        }
      }
      return cached;
    }

    // No cache - must fetch from server.
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak ada data tersimpan dan perangkat offline.',
        isNetwork: true,
      );
    }

    return await _fetchAndCache(userId);
  }

  /// Force refresh from server (for pull-to-refresh).
  Future<DashboardStats> refresh(String userId) async {
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak dapat memuat ulang saat offline.',
        isNetwork: true,
      );
    }

    return await _fetchAndCache(userId);
  }

  /// Get cache age for UI display.
  Future<Duration?> getCacheAge(String userId) async {
    return await local.getCacheAge(userId);
  }

  Future<DashboardStats> _fetchAndCache(String userId) async {
    try {
      final res = await dio.get('/dashboard');
      final data = (res.data as Map)['data'];
      if (data is! Map) return const DashboardStats(package: 'none');

      final json = Map<String, dynamic>.from(data);
      final stats = DashboardStats.fromJson(json);

      // Cache the raw JSON for later reconstruction.
      await local.saveDashboardStats(userId, stats, json);

      return stats;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  void _refreshFromServer(String userId) {
    // Ignore errors in background refresh.
    _fetchAndCache(userId).ignore();
  }
}

final dashboardRepositoryProvider = Provider<DashboardRepository>(
  (ref) => DashboardRepository(
    dio: ref.watch(dioProvider),
    local: ref.watch(dashboardLocalSourceProvider),
    ref: ref,
  ),
);

/// Ringkasan Beranda untuk **akun yang sedang login**.
///
/// Dikunci pada id user lewat `family`. `FutureProvider` biasa menyimpan nilai
/// lama saat dibangun ulang (`copyWithPrevious`), sehingga beberapa detik
/// setelah ganti akun Beranda sempat memakai ringkasan milik akun sebelumnya —
/// admin melihat kartu "Kelas Anda" beserta peringatan khusus guru. Satu
/// instance provider per user tidak punya nilai sebelumnya untuk diwarisi.
final dashboardStatsFamily =
    FutureProvider.autoDispose.family<DashboardStats, String?>(
  (ref, userId) {
    if (userId == null) {
      return Future.value(const DashboardStats(package: 'none'));
    }
    return ref.watch(dashboardRepositoryProvider).fetch(userId);
  },
);

/// Id akun yang sedang login — kunci untuk [dashboardStatsFamily].
final currentUserIdProvider = Provider<String?>(
  (ref) => ref.watch(authControllerProvider.select((s) => s.user?.id)),
);

/// Yang ditonton layar. Tak ada mutasi state di sini, hanya muat ulang
/// (pull-to-refresh lewat [invalidateDashboardStats]).
final dashboardStatsProvider = Provider.autoDispose<AsyncValue<DashboardStats>>(
  (ref) => ref.watch(dashboardStatsFamily(ref.watch(currentUserIdProvider))),
);

/// Muat ulang ringkasan milik akun yang sedang login.
void invalidateDashboardStats(WidgetRef ref) =>
    ref.invalidate(dashboardStatsFamily(ref.read(currentUserIdProvider)));
