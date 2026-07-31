/// Jenis absensi: masuk (check-in) atau pulang (check-out).
enum ScanTime {
  masuk,
  pulang;

  /// Nilai yang dikirim ke API (`waktu`).
  String get api => name; // 'masuk' | 'pulang'

  String get label => switch (this) {
        ScanTime.masuk => 'Masuk',
        ScanTime.pulang => 'Pulang',
      };
}
