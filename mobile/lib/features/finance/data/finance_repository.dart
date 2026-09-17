import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/database/sources/sources.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/providers.dart';
import '../../home/data/dashboard_repository.dart';
import '../models/finance.dart';

/// Akses modul Keuangan (`/finance/*`) dengan offline-first pattern.
class FinanceRepository {
  FinanceRepository({
    required this.dio,
    required this.local,
    required this.ref,
  });

  final Dio dio;
  final FinanceLocalSource local;
  final Ref ref;

  bool get _isOnline => ref.read(backendStatusProvider) == BackendStatus.online;

  /// Get fee summary with offline-first pattern.
  Future<FeeSummary> summary(String userId) async {
    // Try cache first.
    final cached = await local.getFeeSummary(userId);

    if (cached != null) {
      // Have cache - refresh in background if stale.
      if (_isOnline) {
        final isStale = await local.isFeeSummaryStale(userId);
        if (isStale) {
          _refreshSummary(userId);
        }
      }
      return cached;
    }

    // No cache.
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak ada data tersimpan dan perangkat offline.',
        isNetwork: true,
      );
    }

    return await _fetchAndCacheSummary(userId);
  }

  /// Pembayaran yang menunggu diverifikasi.
  Future<List<PaymentEntry>> needsVerification(String userId,
      {int perPage = 25}) async {
    // Try cache first.
    final cached = await local.getPaymentEntries();

    if (cached.isNotEmpty) {
      if (_isOnline) {
        // Always refresh payment queue - it changes frequently.
        _refreshPaymentQueue(userId, perPage);
      }
      return cached;
    }

    if (!_isOnline) {
      return const [];
    }

    return await _fetchAndCachePaymentQueue(userId, perPage);
  }

  /// Force refresh from server.
  Future<void> refresh(String userId) async {
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak dapat memuat ulang saat offline.',
        isNetwork: true,
      );
    }

    await _fetchAndCacheSummary(userId);
    await _fetchAndCachePaymentQueue(userId, 25);
  }

  /// Setujui atau tolak satu pembayaran.
  Future<void> verify(
    String paymentId, {
    required bool approved,
    String? notes,
  }) async {
    if (!_isOnline) {
      // Queue for later sync if offline.
      await local.queuePaymentVerification(paymentId);
      throw ApiException(
        message: 'Verifikasi ditunda sampai online.',
        isNetwork: true,
      );
    }

    try {
      await dio.post('/finance/payments/$paymentId/verify', data: {
        'approved': approved,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<FeeSummary> _fetchAndCacheSummary(String userId) async {
    try {
      final res = await dio.get('/finance/fees/summary');
      final data = (res.data as Map)['data'];
      final summary = FeeSummary.fromJson(Map<String, dynamic>.from(data as Map));

      await local.saveFeeSummary(userId, summary);
      return summary;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<PaymentEntry>> _fetchAndCachePaymentQueue(
    String userId,
    int perPage,
  ) async {
    try {
      final res = await dio.get('/finance/payments', queryParameters: {
        'needs_verification': 1,
        'per_page': perPage,
      });
      final data = (res.data as Map)['data'];
      final items = data is Map ? data['data'] : data;
      if (items is! List) return const [];

      final entries = items
          .whereType<Map>()
          .map((e) => PaymentEntry.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      await local.savePaymentEntries(entries);
      return entries;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  void _refreshSummary(String userId) {
    _fetchAndCacheSummary(userId).ignore();
  }

  void _refreshPaymentQueue(String userId, int perPage) {
    _fetchAndCachePaymentQueue(userId, perPage).ignore();
  }
}

final financeRepositoryProvider = Provider<FinanceRepository>(
  (ref) => FinanceRepository(
    dio: ref.watch(dioProvider),
    local: ref.watch(financeLocalSourceProvider),
    ref: ref,
  ),
);

// Dikunci pada id akun, sama seperti ringkasan Beranda.
final _summaryFamily = FutureProvider.autoDispose.family<FeeSummary, String?>(
  (ref, userId) {
    if (userId == null) {
      return Future.value(const FeeSummary(billed: '-', paid: '-', remaining: '-'));
    }
    return ref.watch(financeRepositoryProvider).summary(userId);
  },
);

final _queueFamily =
    FutureProvider.autoDispose.family<List<PaymentEntry>, String?>(
  (ref, userId) {
    if (userId == null) return Future.value(const []);
    return ref.watch(financeRepositoryProvider).needsVerification(userId);
  },
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
