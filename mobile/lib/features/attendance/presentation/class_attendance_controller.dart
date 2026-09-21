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

  Future<void> selectClass(String classroomId) {
    state = state.copyWith(selectedClassId: classroomId);
    return _loadSheet();
  }

  /// Muat daftar siswa **beserta status yang sudah tercatat** untuk kelas dan
  /// tanggal yang sedang dipilih.
  ///
  /// Dipanggil tiap kali salah satunya berubah. Sebelumnya daftar hanya dimuat
  /// saat kelas berganti dan selalu di-set "semua hadir", sehingga mengganti
  /// tanggal tak berpengaruh apa pun — dan menyimpan akan menimpa absensi
  /// tanggal itu dengan tanda yang tak pernah dilihat gurunya.
  Future<void> _loadSheet() async {
    final classroomId = state.selectedClassId;
    if (classroomId == null) return;

    state = state.copyWith(
      loadingStudents: true,
      students: const [],
      marks: const {},
    );
    try {
      final sheet = await _repo.daily(
        classroomId: classroomId,
        date: state.date ?? DateTime.now(),
      );
      state = state.copyWith(
        students: sheet.students,
        marks: sheet.marks,
        loadingStudents: false,
      );
    } on ApiException catch (e) {
      // Offline: daftar siswa masih bisa diambil dari endpoint kelas, tapi
      // status tersimpan tak bisa dibaca — mulai dari "semua hadir" seperti
      // roll call biasa, supaya guru tetap bisa bekerja.
      if (e.isNetwork) {
        try {
          final students = await _repo.students(classroomId);
          state = state.copyWith(
            students: students,
            marks: {for (final s in students) s.id: AttendanceMark.hadir},
            loadingStudents: false,
          );
          return;
        } on ApiException {
          // jatuh ke pesan error di bawah
        }
      }
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

  Future<void> setDate(DateTime date) {
    state = state.copyWith(date: date);
    return _loadSheet();
  }

  /// Simpan absensi kelas.
  ///
  /// Mengembalikan [SaveOutcome]: terkirim ke server, atau **masuk antrean**
  /// bila jaringan mati. Mengantre itu penting — tanpa itu, guru yang sudah
  /// menandai puluhan siswa kehilangan seluruh pekerjaannya begitu sinyal
  /// hilang.
  Future<SaveOutcome> save() async {
    final classId = state.selectedClassId;
    if (classId == null || state.marks.isEmpty) {
      return const SaveOutcome.failure('Belum ada data absensi.');
    }

    final date = state.date ?? DateTime.now();
    state = state.copyWith(saving: true);

    final className = state.classes
        .where((c) => c.id == classId)
        .map((c) => c.name)
        .firstOrNull ??
        '';

    try {
      await _repo.submit(
        classroomId: classId,
        classroomName: className,
        date: date,
        marks: state.marks,
      );
      state = state.copyWith(saving: false);
      return const SaveOutcome.sent();
    } on ApiException catch (e) {
      if (e.isNetwork) {
        // Already queued by repository.
        state = state.copyWith(saving: false);
        return const SaveOutcome.queued();
      }
      state = state.copyWith(saving: false, error: e.message);
      return SaveOutcome.failure(e.message);
    }
  }
}

/// Hasil penyimpanan absen kelas.
class SaveOutcome {
  const SaveOutcome._(this.kind, [this.message]);

  const SaveOutcome.sent() : this._(SaveOutcomeKind.sent);
  const SaveOutcome.queued() : this._(SaveOutcomeKind.queued);
  const SaveOutcome.failure(String message)
      : this._(SaveOutcomeKind.failure, message);

  final SaveOutcomeKind kind;
  final String? message;

  bool get isFailure => kind == SaveOutcomeKind.failure;

  String get label => switch (kind) {
        SaveOutcomeKind.sent => 'Absensi tersimpan.',
        SaveOutcomeKind.queued =>
          'Tidak ada koneksi — absensi disimpan di antrean '
              'dan akan dikirim otomatis begitu tersambung lagi.',
        SaveOutcomeKind.failure => message ?? 'Gagal menyimpan absensi.',
      };
}

enum SaveOutcomeKind { sent, queued, failure }

/// Dibuat per-kelas-awal agar membuka layar dari kartu kelas tertentu di
/// Beranda langsung memilih kelas itu.
final classAttendanceControllerProvider = StateNotifierProvider.family<
    ClassAttendanceController, ClassAttendanceState, String?>(
  (ref, initialClassroomId) => ClassAttendanceController(
    ref.watch(classAttendanceRepositoryProvider),
    initialClassroomId,
  ),
);
