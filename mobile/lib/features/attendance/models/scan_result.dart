/// Hasil submit absensi (dari `POST /scan`) yang sudah dinormalkan untuk UI.
class ScanResult {
  const ScanResult({
    required this.success,
    required this.message,
    this.type,
    this.action,
    this.name,
    this.identifier,
    this.classroom,
    this.time,
    this.late = false,
    this.lateMinutes = 0,
    this.needsVerification = false,
    this.queued = false,
  });

  final bool success;
  final String message;
  final String? type; // student | teacher
  final String? action; // check_in | check_out
  final String? name;
  final String? identifier; // NIS / NIP
  final String? classroom;
  final String? time;
  final bool late;
  final int lateMinutes;
  final bool needsVerification;

  /// True bila hasil ini dari penyimpanan offline (belum terkirim ke server).
  final bool queued;

  bool get isCheckOut => action == 'check_out';

  /// Bangun dari envelope sukses `{ success, message, data:{...} }`.
  factory ScanResult.fromSuccess(Map<String, dynamic> body) {
    final data = (body['data'] as Map?) ?? const {};
    final entity = (data['student'] ?? data['teacher']) as Map?;
    return ScanResult(
      success: true,
      message: (body['message'] ?? 'Berhasil').toString(),
      type: data['type']?.toString(),
      action: data['action']?.toString(),
      name: entity?['name']?.toString(),
      identifier: (entity?['nis'] ?? entity?['nip'])?.toString(),
      classroom: entity?['classroom']?.toString(),
      time: data['time']?.toString(),
      late: data['late'] == true,
      lateMinutes: (data['late_minutes'] as num?)?.toInt() ?? 0,
      needsVerification: data['needs_verification'] == true,
    );
  }

  factory ScanResult.failure(String message) =>
      ScanResult(success: false, message: message);

  factory ScanResult.queuedOffline() => const ScanResult(
        success: true,
        message: 'Tersimpan offline — akan disinkron saat online.',
        queued: true,
      );

  /// Label status untuk badge.
  String get statusLabel {
    if (queued) return 'Tersimpan';
    if (!success) return 'Gagal';
    if (needsVerification) return 'Perlu verifikasi';
    if (late) return 'Telat $lateMinutes mnt';
    return isCheckOut ? 'Pulang' : 'Hadir';
  }
}
