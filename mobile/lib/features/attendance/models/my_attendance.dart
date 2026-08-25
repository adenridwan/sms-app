import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Kehadiran akun sendiri hari ini, beserta identitas untuk dipindai.
class MyAttendanceToday {
  const MyAttendanceToday({
    required this.status,
    this.dateLabel,
    this.checkInTime,
    this.checkOutTime,
    this.lateMinutes = 0,
    this.identityNumber,
    this.uniqueCode,
    this.rfidCode,
  });

  /// Slug status dari server (`present`, `late`, `belum_scan`, …).
  final String status;

  final String? dateLabel;
  final String? checkInTime;
  final String? checkOutTime;
  final int lateMinutes;

  /// NIP / nomor pegawai.
  final String? identityNumber;

  /// Isi QR — inilah yang dicocokkan scanner (`ScannerController`).
  final String? uniqueCode;

  /// Kode kartu, juga bisa diketik di mode Ref ID.
  final String? rfidCode;

  /// Ada kode yang bisa ditampilkan/dipindai.
  bool get hasCode => (uniqueCode ?? '').isNotEmpty;

  factory MyAttendanceToday.fromJson(Map<String, dynamic> json) {
    final qr = json['qr'];
    return MyAttendanceToday(
      status: (json['status'] ?? 'belum_scan').toString(),
      dateLabel: json['date_label']?.toString(),
      checkInTime: json['check_in_time']?.toString(),
      checkOutTime: json['check_out_time']?.toString(),
      lateMinutes:
          json['late_minutes'] is num ? (json['late_minutes'] as num).toInt() : 0,
      identityNumber: json['identity_number']?.toString(),
      uniqueCode: qr is Map ? qr['unique_code']?.toString() : null,
      rfidCode: json['rfid_code']?.toString(),
    );
  }
}

/// Satu hari pada riwayat kehadiran.
class MyAttendanceDay {
  const MyAttendanceDay({
    required this.date,
    required this.status,
    this.label,
  });

  final String date;
  final String status;

  /// Label siap tampil dari server bila ada.
  final String? label;

  factory MyAttendanceDay.fromJson(Map<String, dynamic> json) =>
      MyAttendanceDay(
        date: (json['date'] ?? '').toString(),
        status: (json['status'] ?? '').toString(),
        label: json['date_label']?.toString() ?? json['day_name']?.toString(),
      );

  /// Tanggal ringkas `25/08` untuk baris riwayat.
  String get shortDate {
    final parts = date.split('-');
    if (parts.length != 3) return date;
    return '${parts[2]}/${parts[1]}';
  }
}

/// Nama dan warna status — dipakai pil pada riwayat maupun kartu hari ini.
///
/// Server memakai campuran slug Inggris (`present`, `late`) dan Indonesia
/// (`belum_scan`); keduanya dipetakan di satu tempat supaya tampilan tak ikut
/// terpecah mengikuti ketidakkonsistenan itu.
({String label, Color color}) attendanceStatusStyle(
  String status,
  ColorScheme scheme,
) =>
    switch (status) {
      'present' || 'hadir' => (label: 'Hadir', color: const Color(0xFF16A34A)),
      'late' || 'telat' => (label: 'Telat', color: AppTheme.accentSoft),
      'sick' || 'sakit' => (label: 'Sakit', color: const Color(0xFF0284C7)),
      'permitted' || 'izin' => (label: 'Izin', color: const Color(0xFF7C3AED)),
      'absent' || 'alfa' => (label: 'Alfa', color: AppTheme.accent),
      'libur' => (label: 'Libur', color: scheme.onSurfaceVariant),
      _ => (label: 'Belum scan', color: scheme.onSurfaceVariant),
    };
