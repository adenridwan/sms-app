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

/// Ringkasan Beranda untuk **akun yang sedang login**.
///
/// Dikunci pada id user lewat `family`. `FutureProvider` biasa menyimpan nilai
/// lama saat dibangun ulang (`copyWithPrevious`), sehingga beberapa detik
/// setelah ganti akun Beranda sempat memakai ringkasan milik akun sebelumnya —
/// admin melihat kartu "Kelas Anda" beserta peringatan khusus guru. Satu
/// instance provider per user tidak punya nilai sebelumnya untuk diwarisi.
final dashboardStatsFamily =
    FutureProvider.autoDispose.family<DashboardStats, String?>(
  (ref, userId) => ref.watch(dashboardRepositoryProvider).fetch(),
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
