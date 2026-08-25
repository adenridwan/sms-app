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

  bool get isAvailable => route != null;
}

/// Aksi untuk peran guru — rujukan `homeActionDefs.teacher`.
const List<ActionItem> _teacherActions = [
  ActionItem(
    key: 'attendance',
    title: 'Absensi',
    caption: 'Pindai, Ref ID & checklist',
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
    key: 'announcements',
    title: 'Pengumuman',
    caption: 'Pengumuman terbaru',
    icon: Icons.campaign_rounded,
  ),
  ActionItem(
    key: 'grades',
    title: 'Nilai',
    caption: 'Input & finalisasi',
    icon: Icons.grade_rounded,
  ),
];

/// Aksi untuk peran admin — rujukan `homeActionDefs.admin`.
const List<ActionItem> _adminActions = [
  ActionItem(
    key: 'attendance',
    title: 'Absensi',
    caption: 'Pindai, Ref ID & checklist',
    icon: Icons.qr_code_scanner_rounded,
    route: '/attendance',
  ),
  ActionItem(
    key: 'finance',
    title: 'Keuangan',
    caption: 'Verifikasi pembayaran',
    icon: Icons.payments_rounded,
  ),
  ActionItem(
    key: 'announcements',
    title: 'Pengumuman',
    caption: 'Buat & publikasikan',
    icon: Icons.campaign_rounded,
  ),
  ActionItem(
    key: 'timetable',
    title: 'Jadwal',
    caption: 'Lihat jadwal',
    icon: Icons.calendar_month_rounded,
  ),
];

/// Seluruh aksi yang dikenal — dipakai lembar "Semua menu" agar pengguna tetap
/// bisa melihat peta modul lengkap.
const List<ActionItem> kActionCatalog = [
  ..._adminActions,
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
List<ActionItem> visibleActionsFor(User? user) => switch (user?.userType) {
      null => const [],
      'teacher' => _teacherActions,
      _ => _adminActions,
    };
