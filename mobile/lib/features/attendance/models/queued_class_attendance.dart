import 'class_attendance.dart';

/// Satu sesi absen kelas yang tertahan offline.
///
/// Berbeda dari `QueuedScan` yang mewakili **satu** orang: entri ini memuat
/// seisi kelas — karena itu antreannya disimpan terpisah, bukan dipaksa masuk
/// bentuk scan tunggal.
///
/// Pengulangan pengiriman aman: `POST /attendance/students/bulk` memperbarui
/// baris yang sudah ada untuk tanggal tersebut, jadi entri yang sempat
/// terkirim sebagian tidak akan menghasilkan duplikat.
class QueuedClassAttendance {
  const QueuedClassAttendance({
    required this.id,
    required this.classroomId,
    required this.classroomName,
    required this.date,
    required this.marks,
    required this.savedAt,
  });

  final String id;
  final String classroomId;

  /// Disimpan agar layar Antrean bisa menyebut nama kelasnya tanpa perlu
  /// memanggil server — justru saat offline nama itu tak bisa diambil.
  final String classroomName;

  final DateTime date;

  /// student_id → status.
  final Map<String, AttendanceMark> marks;

  final DateTime savedAt;

  int get studentCount => marks.length;

  /// Ringkasan per status untuk ditampilkan di antrean.
  Map<AttendanceMark, int> get tally {
    final out = <AttendanceMark, int>{};
    for (final m in marks.values) {
      out[m] = (out[m] ?? 0) + 1;
    }
    return out;
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'classroom_id': classroomId,
        'classroom_name': classroomName,
        'date': _fmtDate(date),
        'saved_at': savedAt.toIso8601String(),
        'marks': {
          for (final e in marks.entries) e.key: e.value.slug,
        },
      };

  factory QueuedClassAttendance.fromJson(Map<String, dynamic> json) {
    final rawMarks = json['marks'];
    return QueuedClassAttendance(
      id: json['id'].toString(),
      classroomId: json['classroom_id'].toString(),
      classroomName: (json['classroom_name'] ?? '').toString(),
      date: DateTime.parse(json['date'].toString()),
      savedAt: DateTime.tryParse((json['saved_at'] ?? '').toString()) ??
          DateTime.now(),
      marks: rawMarks is Map
          ? {
              for (final e in rawMarks.entries)
                e.key.toString(): _markFromSlug(e.value.toString()),
            }
          : const {},
    );
  }

  static AttendanceMark _markFromSlug(String slug) => AttendanceMark.values
      .firstWhere((m) => m.slug == slug, orElse: () => AttendanceMark.hadir);

  static String _fmtDate(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)}';
  }
}
