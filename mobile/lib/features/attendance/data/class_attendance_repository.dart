import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../models/class_attendance.dart';

/// Absen kelas (ceklis manual oleh guru).
///
/// **Cakupan siswa ditentukan server, bukan klien**: `?mine=1` dan penjagaan
/// pada endpoint daftar siswa membuat guru hanya bisa melihat kelas yang ia
/// ampu, sementara peran ber-akses penuh (admin, kepala sekolah, TU, …)
/// melihat semuanya. Klien tidak menyaring apa pun sendiri.
class ClassAttendanceRepository {
  ClassAttendanceRepository(this._dio);

  final Dio _dio;

  /// Kelas yang boleh diabsen oleh pemanggil.
  Future<List<ClassOption>> classrooms() async {
    try {
      final res = await _dio.get('/academic/classrooms', queryParameters: {
        'mine': 1,
        'per_page': 100,
        'is_active': true,
      });
      final payload = (res.data as Map)['data'];
      final items = payload is Map ? payload['data'] : payload;
      if (items is! List) return const [];
      return items
          .whereType<Map>()
          .map((e) => ClassOption.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<ClassStudent>> students(String classroomId) async {
    try {
      final res = await _dio.get('/academic/classrooms/$classroomId/students');
      final items = (res.data as Map)['data'];
      if (items is! List) return const [];
      return items
          .whereType<Map>()
          .map((e) => ClassStudent.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Simpan absensi satu kelas. Aman dipanggil ulang — server memperbarui
  /// baris yang sudah ada untuk tanggal tersebut.
  Future<void> submit({
    required String classroomId,
    required DateTime date,
    required Map<String, AttendanceMark> marks,
  }) async {
    try {
      await _dio.post('/attendance/students/bulk', data: {
        'classroom_id': classroomId,
        'date': _formatDate(date),
        'attendances': [
          for (final e in marks.entries)
            {'student_id': e.key, 'status': e.value.slug},
        ],
      });
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  static String _formatDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-'
      '${d.month.toString().padLeft(2, '0')}-'
      '${d.day.toString().padLeft(2, '0')}';
}

final classAttendanceRepositoryProvider = Provider<ClassAttendanceRepository>(
  (ref) => ClassAttendanceRepository(ref.watch(dioProvider)),
);
