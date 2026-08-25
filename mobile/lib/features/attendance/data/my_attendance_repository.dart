import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../home/data/dashboard_repository.dart';
import '../models/my_attendance.dart';

/// Akses "Absensi Saya" — selalu tentang akun yang sedang login.
///
/// Endpointnya (`/attendance/me/*`) discope ke `auth()->user()` di server, jadi
/// tak ada id yang perlu dikirim dan tak ada cara melihat data orang lain.
class MyAttendanceRepository {
  MyAttendanceRepository(this._dio);

  final Dio _dio;

  Future<MyAttendanceToday> today() async {
    try {
      final res = await _dio.get('/attendance/me/today');
      final data = (res.data as Map)['data'];
      return MyAttendanceToday.fromJson(Map<String, dynamic>.from(data as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Riwayat satu bulan (`YYYY-MM`). Bulan berjalan bila tidak disebut.
  Future<List<MyAttendanceDay>> history({DateTime? month}) async {
    final m = month ?? DateTime.now();
    try {
      final res = await _dio.get('/attendance/me/history', queryParameters: {
        'month': '${m.year.toString().padLeft(4, '0')}-'
            '${m.month.toString().padLeft(2, '0')}',
      });
      final data = (res.data as Map)['data'];
      final list = data is Map ? data['history'] : data;
      if (list is! List) return const [];
      return list
          .whereType<Map>()
          .map((e) => MyAttendanceDay.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final myAttendanceRepositoryProvider = Provider<MyAttendanceRepository>(
  (ref) => MyAttendanceRepository(ref.watch(dioProvider)),
);

/// Kartu "kode presensi Anda" + status hari ini.
///
/// Dikunci pada id akun seperti ringkasan Beranda: tanpa itu, kode presensi
/// milik akun sebelumnya sempat tampil beberapa detik setelah ganti akun —
/// dan kode presensi orang lain adalah hal terakhir yang boleh salah tampil.
final myAttendanceTodayProvider =
    FutureProvider.autoDispose.family<MyAttendanceToday, String?>(
  (ref, userId) => ref.watch(myAttendanceRepositoryProvider).today(),
);

final myAttendanceHistoryProvider =
    FutureProvider.autoDispose.family<List<MyAttendanceDay>, String?>(
  (ref, userId) => ref.watch(myAttendanceRepositoryProvider).history(),
);

/// Yang ditonton layar.
final myAttendanceProvider =
    Provider.autoDispose<AsyncValue<MyAttendanceToday>>(
  (ref) => ref.watch(myAttendanceTodayProvider(ref.watch(currentUserIdProvider))),
);

final myHistoryProvider =
    Provider.autoDispose<AsyncValue<List<MyAttendanceDay>>>(
  (ref) =>
      ref.watch(myAttendanceHistoryProvider(ref.watch(currentUserIdProvider))),
);
