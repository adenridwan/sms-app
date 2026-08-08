import 'package:flutter/material.dart';

import '../../auth/models/user.dart';

/// Satu kartu di grid "Aksi" Beranda.
///
/// Katalog ini mencerminkan `mobile/docs/04-NAVIGATION-MENU.md §5`
/// (menu transaksi → izin → endpoint). Prinsipnya: **hanya transaksi**, semua
/// master tetap di web.
class ActionItem {
  const ActionItem({
    required this.key,
    required this.title,
    required this.caption,
    required this.icon,
    required this.tint,
    required this.permission,
    this.route,
  });

  /// Kunci stabil untuk menyimpan pintasan pilihan pengguna. **Jangan diubah**
  /// setelah dirilis — pintasan tersimpan di perangkat merujuk kunci ini.
  final String key;

  final String title;

  /// Baris kecil di bawah judul — menjelaskan aksi dalam 1–3 kata, agar kartu
  /// besar tidak terasa kosong dan maksudnya jelas tanpa dibuka.
  final String caption;

  final IconData icon;

  /// Warna ikon & latar ubinnya. Membedakan modul sekilas tanpa mengubah
  /// warna kartu itu sendiri (kartu tetap putih).
  final Color tint;

  /// Izin pemicu tampil (nama mengikuti `PermissionSeeder` backend).
  /// `null` = selalu tampil untuk semua persona yang boleh masuk app.
  final String? permission;

  /// Tujuan navigasi. `null` = modul belum dibangun (kartu tampil nonaktif,
  /// supaya peta fitur tetap terbaca tanpa berpura-pura sudah jadi).
  final String? route;

  bool get isAvailable => route != null;
}

// Warna per modul — dipakai hanya untuk ikon & latar ubinnya, bukan untuk
// kartu, sehingga halaman tetap tenang meski modulnya banyak.
const _cBlue = Color(0xFF2563EB);
const _cAmber = Color(0xFFD97706);
const _cViolet = Color(0xFF7C3AED);
const _cTeal = Color(0xFF0D9488);
const _cGreen = Color(0xFF16A34A);
const _cRose = Color(0xFFE11D48);
const _cIndigo = Color(0xFF4F46E5);

/// Katalog aksi transaksi. Urutan = urutan tampil di Beranda.
const List<ActionItem> kActionCatalog = [
  ActionItem(
    key: 'attendance.scan',
    title: 'Scan QR',
    caption: 'Absen cepat',
    icon: Icons.qr_code_scanner_rounded,
    tint: _cBlue,
    permission: 'attendance.record',
    route: '/attendance',
  ),
  ActionItem(
    key: 'attendance.class',
    title: 'Absen Kelas',
    caption: 'Ceklis manual',
    icon: Icons.fact_check_rounded,
    tint: _cAmber,
    permission: 'attendance.record',
    route: '/class-attendance',
  ),
  ActionItem(
    key: 'attendance.manual',
    title: 'Input Manual',
    caption: 'Ketik kode',
    icon: Icons.keyboard_rounded,
    tint: _cViolet,
    permission: 'attendance.record',
    route: '/manual',
  ),
  ActionItem(
    key: 'attendance.queue',
    title: 'Antrean',
    caption: 'Sinkron offline',
    icon: Icons.cloud_upload_rounded,
    tint: _cTeal,
    permission: 'attendance.record',
    route: '/queue',
  ),
  ActionItem(
    key: 'attendance.recap',
    title: 'Rekap',
    caption: 'Riwayat absensi',
    icon: Icons.assessment_rounded,
    tint: _cIndigo,
    permission: 'attendance.view',
  ),
  ActionItem(
    key: 'leave.approve',
    title: 'Izin',
    caption: 'Setujui pengajuan',
    icon: Icons.how_to_reg_rounded,
    tint: _cGreen,
    permission: 'attendance.manage',
  ),
  ActionItem(
    key: 'grades.input',
    title: 'Nilai',
    caption: 'Input & finalisasi',
    icon: Icons.grade_rounded,
    tint: _cAmber,
    permission: 'grades.input',
  ),
  ActionItem(
    key: 'payments.create',
    title: 'Pembayaran',
    caption: 'Terima & verifikasi',
    icon: Icons.payments_rounded,
    tint: _cGreen,
    permission: 'payments.create',
  ),
  ActionItem(
    key: 'library.loans',
    title: 'Perpustakaan',
    caption: 'Pinjam & kembali',
    icon: Icons.menu_book_rounded,
    tint: _cRose,
    permission: 'loans.create',
  ),
  ActionItem(
    key: 'announcements',
    title: 'Pengumuman',
    caption: 'Buat & publikasi',
    icon: Icons.campaign_rounded,
    tint: _cViolet,
    permission: 'announcements.create',
  ),
  ActionItem(
    key: 'schedules',
    title: 'Jadwal',
    caption: 'Minggu ini',
    icon: Icons.calendar_month_rounded,
    tint: _cTeal,
    permission: 'schedules.view',
  ),
];

/// Aksi yang boleh dilihat [user] — super admin melihat semua
/// (lihat [User.hasPermission], cermin aturan RBAC backend).
List<ActionItem> visibleActionsFor(User? user) {
  if (user == null) return const [];
  return kActionCatalog
      .where((a) => a.permission == null || user.hasPermission(a.permission!))
      .toList();
}
