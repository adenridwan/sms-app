import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/class_attendance_repository.dart';
import '../models/class_attendance.dart';

class ClassAttendanceState {
  const ClassAttendanceState({
    this.classes = const [],
    this.selectedClassId,
    this.students = const [],
    this.marks = const {},
    this.date,
    this.loadingClasses = true,
    this.loadingStudents = false,
    this.saving = false,
    this.error,
  });

  final List<ClassOption> classes;
  final String? selectedClassId;
  final List<ClassStudent> students;

  /// student_id → status. Semua siswa terisi sejak awal (default hadir).
  final Map<String, AttendanceMark> marks;

  final DateTime? date;
  final bool loadingClasses;
  final bool loadingStudents;
  final bool saving;
  final String? error;

  bool get canSave =>
      selectedClassId != null && students.isNotEmpty && !saving;

  /// Jumlah per status untuk ringkasan di atas tombol simpan.
  Map<AttendanceMark, int> get tally {
    final out = <AttendanceMark, int>{};
    for (final m in marks.values) {
      out[m] = (out[m] ?? 0) + 1;
    }
    return out;
  }

  ClassAttendanceState copyWith({
    List<ClassOption>? classes,
    String? selectedClassId,
    List<ClassStudent>? students,
    Map<String, AttendanceMark>? marks,
    DateTime? date,
    bool? loadingClasses,
    bool? loadingStudents,
    bool? saving,
    String? error,
  }) {
    return ClassAttendanceState(
      classes: classes ?? this.classes,
      selectedClassId: selectedClassId ?? this.selectedClassId,
      students: students ?? this.students,
      marks: marks ?? this.marks,
      date: date ?? this.date,
      loadingClasses: loadingClasses ?? this.loadingClasses,
      loadingStudents: loadingStudents ?? this.loadingStudents,
      saving: saving ?? this.saving,
      error: error,
    );
  }
}

class ClassAttendanceController extends StateNotifier<ClassAttendanceState> {
  ClassAttendanceController(this._repo, this._initialClassroomId)
      : super(ClassAttendanceState(date: DateTime.now())) {
    _loadClasses();
  }

  final ClassAttendanceRepository _repo;
  final String? _initialClassroomId;

  Future<void> _loadClasses() async {
    state = state.copyWith(loadingClasses: true);
    try {
      final classes = await _repo.classrooms();
      state = state.copyWith(classes: classes, loadingClasses: false);

      // Kelas dari tautan Beranda (mis. kelas perwalian) dipilih lebih dulu;
      // kalau tidak ada, ambil kelas pertama agar layar langsung berguna.
      final preferred = classes.any((c) => c.id == _initialClassroomId)
          ? _initialClassroomId
          : (classes.isNotEmpty ? classes.first.id : null);

      if (preferred != null) await selectClass(preferred);
    } on ApiException catch (e) {
      state = state.copyWith(loadingClasses: false, error: e.message);
    }
  }

  Future<void> selectClass(String classroomId) async {
    state = state.copyWith(
      selectedClassId: classroomId,
      loadingStudents: true,
      students: const [],
      marks: const {},
    );
    try {
      final students = await _repo.students(classroomId);
      state = state.copyWith(
        students: students,
        // Semua default "Hadir": guru hanya menandai pengecualian, sebagaimana
        // roll call sungguhan bekerja.
        marks: {for (final s in students) s.id: AttendanceMark.hadir},
        loadingStudents: false,
      );
    } on ApiException catch (e) {
      state = state.copyWith(loadingStudents: false, error: e.message);
    }
  }

  void setMark(String studentId, AttendanceMark mark) {
    state = state.copyWith(marks: {...state.marks, studentId: mark});
  }

  void markAll(AttendanceMark mark) {
    state = state.copyWith(
      marks: {for (final s in state.students) s.id: mark},
    );
  }

  void setDate(DateTime date) => state = state.copyWith(date: date);

  /// Mengembalikan null bila sukses, atau pesan error bila gagal.
  Future<String?> save() async {
    final classId = state.selectedClassId;
    if (classId == null || state.marks.isEmpty) return 'Belum ada data absensi.';

    state = state.copyWith(saving: true);
    try {
      await _repo.submit(
        classroomId: classId,
        date: state.date ?? DateTime.now(),
        marks: state.marks,
      );
      state = state.copyWith(saving: false);
      return null;
    } on ApiException catch (e) {
      state = state.copyWith(saving: false, error: e.message);
      return e.message;
    }
  }
}

/// Dibuat per-kelas-awal agar membuka layar dari kartu kelas tertentu di
/// Beranda langsung memilih kelas itu.
final classAttendanceControllerProvider = StateNotifierProvider.family<
    ClassAttendanceController, ClassAttendanceState, String?>(
  (ref, initialClassroomId) => ClassAttendanceController(
    ref.watch(classAttendanceRepositoryProvider),
    initialClassroomId,
  ),
);
