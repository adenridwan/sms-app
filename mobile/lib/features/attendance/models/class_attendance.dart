/// Status kehadiran yang boleh disimpan lewat `POST /attendance/students/bulk`.
///
/// [slug] memakai **slug Indonesia** — itulah yang divalidasi endpoint
/// (`AttendanceStatus::storableSlugs()`), bukan nilai backing enum yang
/// berbahasa Inggris (`present`, `sick`, …). Server yang menerjemahkannya.
///
/// `belum_scan` sengaja tidak ada di sini: status itu virtual — dihitung,
/// tak pernah disimpan.
enum AttendanceMark {
  hadir('hadir', 'Hadir', 'H'),
  sakit('sakit', 'Sakit', 'S'),
  izin('izin', 'Izin', 'I'),
  alfa('alfa', 'Alfa', 'A');

  const AttendanceMark(this.slug, this.label, this.short);

  final String slug;
  final String label;

  /// Huruf tunggal untuk tombol pilihan cepat di daftar siswa.
  final String short;
}

/// Satu siswa dalam daftar absen kelas.
class ClassStudent {
  const ClassStudent({
    required this.id,
    required this.name,
    this.nis,
  });

  final String id;
  final String name;
  final String? nis;

  /// Inisial untuk avatar (maks 2 huruf).
  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty);
    if (parts.isEmpty) return '?';
    return parts.take(2).map((e) => e[0].toUpperCase()).join();
  }

  factory ClassStudent.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    final name = user is Map ? (user['full_name'] ?? '').toString() : '';
    return ClassStudent(
      id: json['id']?.toString() ?? '',
      name: name.isEmpty ? '(tanpa nama)' : name,
      nis: json['nis']?.toString(),
    );
  }
}

/// Pilihan kelas pada pemilih di layar absen.
class ClassOption {
  const ClassOption({required this.id, required this.name});

  final String id;
  final String name;

  factory ClassOption.fromJson(Map<String, dynamic> json) => ClassOption(
        id: json['id']?.toString() ?? '',
        name: (json['name'] ?? '').toString(),
      );
}
