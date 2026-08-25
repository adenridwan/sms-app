import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../home/data/dashboard_repository.dart';
import '../models/finance.dart';

/// Akses modul Keuangan (`/finance/*`).
class FinanceRepository {
  FinanceRepository(this._dio);

  final Dio _dio;

  Future<FeeSummary> summary() async {
    try {
      final res = await _dio.get('/finance/fees/summary');
      final data = (res.data as Map)['data'];
      return FeeSummary.fromJson(Map<String, dynamic>.from(data as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Pembayaran yang menunggu diverifikasi.
  ///
  /// Memakai `needs_verification=1` — filternya di server (`scopeNeedsVerification`)
  /// mencakup status `pending` **dan** `processing` yang belum diverifikasi,
  /// bukan hanya salah satunya.
  Future<List<PaymentEntry>> needsVerification({int perPage = 25}) async {
    try {
      final res = await _dio.get('/finance/payments', queryParameters: {
        'needs_verification': 1,
        'per_page': perPage,
      });
      final data = (res.data as Map)['data'];
      final items = data is Map ? data['data'] : data;
      if (items is! List) return const [];
      return items
          .whereType<Map>()
          .map((e) => PaymentEntry.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Setujui atau tolak satu pembayaran.
  ///
  /// Endpoint mewajibkan `approved` — verifikasi punya dua arah, bukan sekadar
  /// "tandai selesai". Menolak menandai pembayaran gagal beserta alasannya.
  Future<void> verify(
    String paymentId, {
    required bool approved,
    String? notes,
  }) async {
    try {
      await _dio.post('/finance/payments/$paymentId/verify', data: {
        'approved': approved,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final financeRepositoryProvider = Provider<FinanceRepository>(
  (ref) => FinanceRepository(ref.watch(dioProvider)),
);

// Dikunci pada id akun, sama seperti ringkasan Beranda: angka keuangan milik
// akun sebelumnya tak boleh sempat tampil setelah ganti akun.
final _summaryFamily = FutureProvider.autoDispose.family<FeeSummary, String?>(
  (ref, userId) => ref.watch(financeRepositoryProvider).summary(),
);

final _queueFamily =
    FutureProvider.autoDispose.family<List<PaymentEntry>, String?>(
  (ref, userId) => ref.watch(financeRepositoryProvider).needsVerification(),
);

final feeSummaryProvider = Provider.autoDispose<AsyncValue<FeeSummary>>(
  (ref) => ref.watch(_summaryFamily(ref.watch(currentUserIdProvider))),
);

final verificationQueueProvider =
    Provider.autoDispose<AsyncValue<List<PaymentEntry>>>(
  (ref) => ref.watch(_queueFamily(ref.watch(currentUserIdProvider))),
);

/// Muat ulang keduanya (dipakai pull-to-refresh dan sesudah verifikasi).
void invalidateFinance(WidgetRef ref) {
  final id = ref.read(currentUserIdProvider);
  ref.invalidate(_summaryFamily(id));
  ref.invalidate(_queueFamily(id));
}
