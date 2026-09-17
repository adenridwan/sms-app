import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/database/sources/sources.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status.dart';
import '../../../core/providers.dart';
import '../models/class_attendance.dart';
import '../models/queued_class_attendance.dart';

/// Absen kelas (ceklis manual oleh guru) dengan offline-first pattern.
class ClassAttendanceRepository {
  ClassAttendanceRepository({
    required this.dio,
    required this.localClassroom,
    required this.localQueue,
    required this.ref,
  });

  final Dio dio;
  final ClassroomLocalSource localClassroom;
  final ClassAttendanceQueueLocalSource localQueue;
  final Ref ref;

  bool get _isOnline => ref.read(backendStatusProvider) == BackendStatus.online;

  /// Kelas yang boleh diabsen oleh pemanggil.
  Future<List<ClassOption>> classrooms() async {
    // Try cache first.
    final cached = await localClassroom.getClassrooms();

    if (cached.isNotEmpty) {
      if (_isOnline) {
        final isStale = await localClassroom.isStale();
        if (isStale) {
          _refreshClassrooms();
        }
      }
      return cached;
    }

    if (!_isOnline) {
      return const [];
    }

    return await _fetchAndCacheClassrooms();
  }

  /// Force refresh classrooms.
  Future<List<ClassOption>> refreshClassrooms() async {
    if (!_isOnline) {
      throw ApiException(
        message: 'Tidak dapat memuat ulang saat offline.',
        isNetwork: true,
      );
    }

    return await _fetchAndCacheClassrooms();
  }

  /// Get students for a classroom.
  Future<List<ClassStudent>> students(String classroomId) async {
    // Try cache first.
    final cached = await localClassroom.getStudents(classroomId);

    if (cached.isNotEmpty) {
      if (_isOnline) {
        final isStale = await localClassroom.areStudentsStale(classroomId);
        if (isStale) {
          _refreshStudents(classroomId);
        }
      }
      return cached;
    }

    if (!_isOnline) {
      return const [];
    }

    return await _fetchAndCacheStudents(classroomId);
  }

  /// Absensi kelas pada satu tanggal.
  Future<({List<ClassStudent> students, Map<String, AttendanceMark> marks})>
      daily({required String classroomId, required DateTime date}) async {
    // This endpoint must always be online - it fetches current attendance state.
    if (!_isOnline) {
      // Try to build from local students cache.
      final students = await localClassroom.getStudents(classroomId);
      if (students.isEmpty) {
        throw ApiException(
          message: 'Tidak dapat memuat data saat offline.',
          isNetwork: true,
        );
      }
      // Return students with default "hadir" marks.
      return (
        students: students,
        marks: {for (final s in students) s.id: AttendanceMark.hadir}
      );
    }

    try {
      final res =
          await dio.get('/attendance/students/daily', queryParameters: {
        'classroom_id': classroomId,
        'date': _ymd(date),
      });

      final data = (res.data as Map)['data'];
      final items = data is Map ? data['students'] : data;
      if (items is! List) {
        return (
          students: const <ClassStudent>[],
          marks: const <String, AttendanceMark>{}
        );
      }

      final students = <ClassStudent>[];
      final marks = <String, AttendanceMark>{};

      for (final raw in items.whereType<Map>()) {
        final row = Map<String, dynamic>.from(raw);
        final id = row['student_id']?.toString() ?? '';
        if (id.isEmpty) continue;

        students.add(ClassStudent(
          id: id,
          name: (row['name'] ?? '').toString().isEmpty
              ? '(tanpa nama)'
              : row['name'].toString(),
          nis: row['nis']?.toString(),
        ));

        marks[id] = AttendanceMark.values.firstWhere(
          (m) => m.slug == row['status']?.toString(),
          orElse: () => AttendanceMark.hadir,
        );
      }

      // Cache students for offline use.
      await localClassroom.saveStudents(classroomId, students);

      return (students: students, marks: marks);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Simpan absensi satu kelas.
  ///
  /// If offline, queues for later sync.
  Future<void> submit({
    required String classroomId,
    required String classroomName,
    required DateTime date,
    required Map<String, AttendanceMark> marks,
  }) async {
    if (!_isOnline) {
      // Queue for offline sync.
      await localQueue.addAttendance(QueuedClassAttendance(
        id: '${classroomId}_${_ymd(date)}',
        classroomId: classroomId,
        classroomName: classroomName,
        date: date,
        marks: marks,
        savedAt: DateTime.now(),
      ));
      return;
    }

    try {
      await dio.post('/attendance/students/bulk', data: {
        'classroom_id': classroomId,
        'date': _ymd(date),
        'attendances': [
          for (final e in marks.entries)
            {'student_id': e.key, 'status': e.value.slug},
        ],
      });
    } on DioException catch (e) {
      // If network error, queue for later sync.
      if (ApiException.isNetworkError(e)) {
        await localQueue.addAttendance(QueuedClassAttendance(
          id: '${classroomId}_${_ymd(date)}',
          classroomId: classroomId,
          classroomName: classroomName,
          date: date,
          marks: marks,
          savedAt: DateTime.now(),
        ));
        return;
      }
      throw ApiException.fromDio(e);
    }
  }

  /// Get pending queue count.
  Future<int> getPendingQueueCount() async {
    return await localQueue.getPendingCount();
  }

  /// Get all pending queue items.
  Future<List<QueuedClassAttendance>> getPendingQueue() async {
    return await localQueue.getAllAttendances();
  }

  Future<List<ClassOption>> _fetchAndCacheClassrooms() async {
    try {
      final res = await dio.get('/academic/classrooms', queryParameters: {
        'mine': 1,
        'per_page': 100,
        'is_active': true,
      });
      final payload = (res.data as Map)['data'];
      final items = payload is Map ? payload['data'] : payload;
      if (items is! List) return const [];

      final classrooms = items
          .whereType<Map>()
          .map((e) => ClassOption.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      await localClassroom.saveClassrooms(classrooms);
      return classrooms;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<ClassStudent>> _fetchAndCacheStudents(String classroomId) async {
    try {
      final res = await dio.get('/academic/classrooms/$classroomId/students');
      final items = (res.data as Map)['data'];
      if (items is! List) return const [];

      final students = items
          .whereType<Map>()
          .map((e) => ClassStudent.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      await localClassroom.saveStudents(classroomId, students);
      return students;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  void _refreshClassrooms() {
    _fetchAndCacheClassrooms().ignore();
  }

  void _refreshStudents(String classroomId) {
    _fetchAndCacheStudents(classroomId).ignore();
  }

  static String _ymd(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-'
      '${d.month.toString().padLeft(2, '0')}-'
      '${d.day.toString().padLeft(2, '0')}';
}

final classAttendanceRepositoryProvider = Provider<ClassAttendanceRepository>(
  (ref) => ClassAttendanceRepository(
    dio: ref.watch(dioProvider),
    localClassroom: ref.watch(classroomLocalSourceProvider),
    localQueue: ref.watch(classAttendanceQueueLocalSourceProvider),
    ref: ref,
  ),
);
