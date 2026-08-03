/// Ringkasan Beranda dari `GET /dashboard`.
///
/// Bentuk respons **berbeda per paket peran** (`admin`, `teacher`, `finance`,
/// `principal`, …) — lihat `DashboardStatsService::statsFor()` di backend.
/// Karena itu semua field di sini opsional; UI hanya menampilkan yang ada.
class DashboardStats {
  const DashboardStats({
    required this.package,
    this.totalStudents,
    this.totalTeachers,
    this.totalClassrooms,
    this.academicYear,
    this.attendanceDate,
    this.attendancePercentage,
    this.attendanceSummary = const {},
    this.myClasses = const [],
    this.linked = true,
    this.homeroomClassroomId,
  });

  /// Paket peran yang menentukan bentuk data (`admin`, `teacher`, …).
  final String package;

  final int? totalStudents;
  final int? totalTeachers;
  final int? totalClassrooms;
  final String? academicYear;

  /// Khusus paket guru: `false` bila akun belum terhubung ke kelas mana pun.
  /// Angka nol pada layar akan membingungkan, jadi kasus ini perlu pesan sendiri.
  final bool linked;

  /// Kelas perwalian — dipakai sebagai pilihan default form absen kelas.
  final String? homeroomClassroomId;

  bool get isTeacher => package == 'teacher';

  final String? attendanceDate;
  final num? attendancePercentage;

  /// `{hadir: n, sakit: n, izin: n, alfa: n, belum_scan: n}` — paket sekolah.
  final Map<String, int> attendanceSummary;

  /// Kelas yang diampu — paket guru.
  final List<DashboardClass> myClasses;

  bool get hasSchoolTotals => totalStudents != null || totalClassrooms != null;

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    int? asInt(dynamic v) => v is num ? v.toInt() : null;

    final attendance = json['attendance_today'];
    final summaryRaw =
        attendance is Map ? attendance['summary'] : null;

    return DashboardStats(
      package: (json['package'] ?? 'none').toString(),
      totalStudents: asInt(json['total_students']),
      totalTeachers: asInt(json['total_teachers']),
      // Paket guru memakai `total_classes`, paket sekolah `total_classrooms`.
      totalClassrooms:
          asInt(json['total_classrooms']) ?? asInt(json['total_classes']),
      academicYear: json['active_academic_year']?.toString(),
      linked: json['linked'] is bool ? json['linked'] as bool : true,
      homeroomClassroomId: json['homeroom_classroom_id']?.toString(),
      attendanceDate:
          attendance is Map ? attendance['date']?.toString() : null,
      attendancePercentage:
          attendance is Map && attendance['percentage'] is num
              ? attendance['percentage'] as num
              : null,
      attendanceSummary: summaryRaw is Map
          ? {
              for (final e in summaryRaw.entries)
                if (e.value is num) e.key.toString(): (e.value as num).toInt(),
            }
          : const {},
      myClasses: json['my_classes'] is List
          ? (json['my_classes'] as List)
              .whereType<Map>()
              .map((e) => DashboardClass.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
    );
  }
}

class DashboardClass {
  const DashboardClass({
    required this.id,
    required this.name,
    this.studentsCount,
  });

  final String id;
  final String name;
  final int? studentsCount;

  factory DashboardClass.fromJson(Map<String, dynamic> json) => DashboardClass(
        id: json['id']?.toString() ?? '',
        name: (json['name'] ?? '').toString(),
        studentsCount:
            json['students_count'] is num ? (json['students_count'] as num).toInt() : null,
      );
}
