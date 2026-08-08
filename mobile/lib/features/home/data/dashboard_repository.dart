import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../models/dashboard_stats.dart';

/// Akses `GET /dashboard` (ringkasan Beranda per paket peran).
class DashboardRepository {
  DashboardRepository(this._dio);

  final Dio _dio;

  Future<DashboardStats> fetch() async {
    try {
      final res = await _dio.get('/dashboard');
      final data = (res.data as Map)['data'];
      if (data is! Map) return const DashboardStats(package: 'none');
      return DashboardStats.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final dashboardRepositoryProvider = Provider<DashboardRepository>(
  (ref) => DashboardRepository(ref.watch(dioProvider)),
);

/// Ringkasan Beranda. `AsyncValue` cukup di sini — tak ada mutasi state,
/// hanya muat ulang (pull-to-refresh lewat `ref.invalidate`).
///
/// Sengaja bergantung pada **id user yang sedang login**: tanpa itu, data
/// tetap tersimpan setelah ganti akun, sehingga guru yang baru masuk masih
/// melihat ringkasan milik admin sebelumnya.
final dashboardStatsProvider = FutureProvider<DashboardStats>((ref) {
  ref.watch(authControllerProvider.select((s) => s.user?.id));
  return ref.watch(dashboardRepositoryProvider).fetch();
});
