import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../models/app_notification.dart';

/// Akses `GET/POST /notifications` (lihat docs/03-API-CONTRACT.md §4.13).
class NotificationRepository {
  NotificationRepository(this._dio);

  final Dio _dio;

  Future<List<AppNotification>> list() async {
    try {
      final res = await _dio.get('/notifications');
      final payload = (res.data as Map)['data'];
      // Endpoint bisa mengembalikan list langsung atau paginator {data: [...]}.
      final items = payload is Map ? payload['data'] : payload;
      if (items is! List) return const [];
      return items
          .whereType<Map>()
          .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> markRead(String id) async {
    try {
      await _dio.post('/notifications/$id/read');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> markAllRead() async {
    try {
      await _dio.post('/notifications/read-all');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
