import 'package:flutter/material.dart';

import '../../auth/models/user.dart';

/// Satu kartu di grid "Aksi" Beranda.
///
/// Isi katalog disalin **persis** dari rujukan desain
/// (artifact `0af1e5c5`, konstanta `homeActionDefs`): judul, keterangan, dan
/// urutannya sama, termasuk perbedaan set antara guru dan admin.
///
/// Bedanya dengan rujukan: di sana semua modul sudah "ada" karena itu prototipe
/// tanpa backend. Di sini modul yang belum dibangun tetap tampil — dengan
/// [route] `null` sehingga kartunya nonaktif — supaya peta menu terbaca utuh
/// tanpa berpura-pura sudah jadi.
class ActionItem {
  const ActionItem({
    required this.key,
    required this.title,
    required this.caption,
    required this.icon,
    this.route,
    this.requiresBackend = true,
  });

  /// Kunci stabil untuk menyimpan pintasan pilihan pengguna. **Jangan diubah**
  /// setelah dirilis — pintasan tersimpan di perangkat merujuk kunci ini.
  final String key;

  final String title;

  /// Baris kecil di bawah judul, mengikuti `sub` pada rujukan.
  final String caption;

  /// Hanya dipakai lembar "Semua menu"; grid Beranda sengaja tanpa ikon.
  final IconData icon;

  /// Tujuan navigasi. `null` = modul belum dibangun (kartu tampil nonaktif).
  final String? route;

  /// `true` bila menu ini tidak berguna tanpa data dari server sekolah
  /// (daftar siswa, jadwal, kelas). Dipakai Beranda untuk mengunci kartu
  /// selama perangkat belum dihubungkan, bukan untuk menyembunyikannya.
  final bool requiresBackend;

  bool get isAvailable => route != null;
}

/// Aksi untuk peran guru — 5 menu shortcut dengan ikon modern.
const List<ActionItem> _teacherActions = [
  ActionItem(
    key: 'attendance',
    title: 'Absensi',
    caption: 'Pindai QR & Ref ID',
    icon: Icons.qr_code_scanner_rounded,
    route: '/attendance',
  ),
  ActionItem(
    key: 'timetable',
    title: 'Jadwal',
    caption: 'Jadwal minggu ini',
    icon: Icons.calendar_month_rounded,
  ),
  ActionItem(
    key: 'attendance_report',
    title: 'Laporan Presensi',
    caption: 'Rekap kehadiran',
    icon: Icons.assignment_rounded,
  ),
  ActionItem(
    key: 'announcements',
    title: 'Pengumuman',
    caption: 'Info terbaru',
    icon: Icons.campaign_rounded,
  ),
  ActionItem(
    key: 'leave_request',
    title: 'Pengajuan Izin',
    caption: 'Ajukan izin/sakit',
    icon: Icons.event_busy_rounded,
  ),
];

/// Aksi untuk peran admin — 5 menu shortcut dengan ikon modern.
const List<ActionItem> _adminActions = [
  ActionItem(
    key: 'attendance',
    title: 'Absensi',
    caption: 'Pindai QR & Ref ID',
    icon: Icons.qr_code_scanner_rounded,
    route: '/attendance',
  ),
  ActionItem(
    key: 'timetable',
    title: 'Jadwal',
    caption: 'Lihat jadwal',
    icon: Icons.calendar_month_rounded,
  ),
  ActionItem(
    key: 'attendance_report',
    title: 'Laporan Presensi',
    caption: 'Rekap kehadiran',
    icon: Icons.assignment_rounded,
  ),
  ActionItem(
    key: 'announcements',
    title: 'Pengumuman',
    caption: 'Buat & publikasi',
    icon: Icons.campaign_rounded,
  ),
  ActionItem(
    key: 'leave_request',
    title: 'Pengajuan Izin',
    caption: 'Kelola pengajuan',
    icon: Icons.event_busy_rounded,
  ),
];

/// Seluruh aksi yang dikenal — dipakai lembar "Semua menu" agar pengguna tetap
/// bisa melihat peta modul lengkap.
const List<ActionItem> kActionCatalog = [
  ..._adminActions,
  ActionItem(
    key: 'finance',
    title: 'Keuangan',
    caption: 'Verifikasi pembayaran',
    icon: Icons.payments_rounded,
  ),
  ActionItem(
    key: 'grades',
    title: 'Nilai',
    caption: 'Input & finalisasi',
    icon: Icons.grade_rounded,
  ),
];

/// Aksi Beranda untuk [user].
///
/// Peran diambil dari akun yang login (`user_type` di token), bukan dari
/// pilihan di aplikasi — persis seperti rujukan yang menyusun grid dari `role`.
/// Staf dan super admin memakai set admin karena lingkup kerjanya sama.
/// Aksi saat peran belum diketahui — akun lokal yang belum tersambung ke
/// server sekolah.
///
/// Sebelumnya kasus ini mengembalikan daftar kosong, sehingga Beranda tampak
/// melompong begitu login. Menunya sekarang tetap ditampilkan supaya peta
/// fitur terbaca, tapi keterangannya netral: peran belum diketahui, jadi
/// jangan menjanjikan "kelola" atau "buat" yang mungkin bukan hak akunnya.
/// Penguncian dikerjakan Beranda lewat [requiresBackend].
const List<ActionItem> _unlinkedActions = [
  ActionItem(
    key: 'attendance',
    title: 'Absensi',
    caption: 'Pindai QR & Ref ID',
    icon: Icons.qr_code_scanner_rounded,
    route: '/attendance',
  ),
  ActionItem(
    key: 'timetable',
    title: 'Jadwal',
    caption: 'Jadwal mengajar',
    icon: Icons.calendar_month_rounded,
  ),
  ActionItem(
    key: 'attendance_report',
    title: 'Laporan Presensi',
    caption: 'Rekap kehadiran',
    icon: Icons.assignment_rounded,
  ),
  ActionItem(
    key: 'announcements',
    title: 'Pengumuman',
    caption: 'Info sekolah',
    icon: Icons.campaign_rounded,
  ),
  ActionItem(
    key: 'leave_request',
    title: 'Pengajuan Izin',
    caption: 'Izin & sakit',
    icon: Icons.event_busy_rounded,
  ),
];

List<ActionItem> visibleActionsFor(User? user) => switch (user?.userType) {
      null => _unlinkedActions,
      'teacher' => _teacherActions,
      _ => _adminActions,
    };

/// Aksi berdasarkan peran yang tersimpan dari `redeem-provision`.
///
/// Dipakai saat sesi backend belum dimuat tapi perangkat sudah terhubung —
/// kondisi normal pada arsitektur local-first, karena login memakai akun
/// lokal dan `user` dari token baru terisi belakangan.
///
/// Peran tersimpan ini menentukan TAMPILAN saja. Server tetap memeriksa izin
/// di tiap request; jangan jadikan nilai ini dasar mengizinkan suatu aksi.
List<ActionItem> visibleActionsForRoles(List<String> roles) {
  if (roles.isEmpty) return _unlinkedActions;
  // Guru dan wali kelas memakai set guru; peran staf lain memakai set admin
  // karena lingkup kerjanya sama di aplikasi ini.
  if (roles.contains('guru') || roles.contains('wali_kelas')) {
    return _teacherActions;
  }
  return _adminActions;
}
