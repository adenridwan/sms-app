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
    required this.icon,
    required this.permission,
    this.route,
  });

  /// Kunci stabil untuk menyimpan pintasan pilihan pengguna. **Jangan diubah**
  /// setelah dirilis — pintasan tersimpan di perangkat merujuk kunci ini.
  final String key;

  final String title;
  final IconData icon;

  /// Izin pemicu tampil (nama mengikuti `PermissionSeeder` backend).
  /// `null` = selalu tampil untuk semua persona yang boleh masuk app.
  final String? permission;

  /// Tujuan navigasi. `null` = modul belum dibangun (kartu tampil nonaktif,
  /// supaya peta fitur tetap terbaca tanpa berpura-pura sudah jadi).
  final String? route;

  bool get isAvailable => route != null;
}

/// Katalog aksi transaksi. Urutan = urutan tampil di Beranda.
const List<ActionItem> kActionCatalog = [
  ActionItem(
    key: 'attendance.scan',
    title: 'Scan Absensi',
    icon: Icons.qr_code_scanner_rounded,
    permission: 'attendance.record',
    route: '/attendance',
  ),
  ActionItem(
    key: 'attendance.class',
    title: 'Absen Kelas',
    icon: Icons.fact_check_rounded,
    permission: 'attendance.record',
    route: '/class-attendance',
  ),
  ActionItem(
    key: 'attendance.manual',
    title: 'Input Manual',
    icon: Icons.keyboard_rounded,
    permission: 'attendance.record',
    route: '/manual',
  ),
  ActionItem(
    key: 'attendance.queue',
    title: 'Antrean Offline',
    icon: Icons.cloud_upload_rounded,
    permission: 'attendance.record',
    route: '/queue',
  ),
  ActionItem(
    key: 'attendance.recap',
    title: 'Rekap Absensi',
    icon: Icons.assessment_rounded,
    permission: 'attendance.view',
  ),
  ActionItem(
    key: 'leave.approve',
    title: 'Izin — Approve',
    icon: Icons.how_to_reg_rounded,
    permission: 'attendance.manage',
  ),
  ActionItem(
    key: 'grades.input',
    title: 'Nilai',
    icon: Icons.grade_rounded,
    permission: 'grades.input',
  ),
  ActionItem(
    key: 'payments.create',
    title: 'Pembayaran',
    icon: Icons.payments_rounded,
    permission: 'payments.create',
  ),
  ActionItem(
    key: 'library.loans',
    title: 'Perpustakaan',
    icon: Icons.menu_book_rounded,
    permission: 'loans.create',
  ),
  ActionItem(
    key: 'announcements',
    title: 'Pengumuman',
    icon: Icons.campaign_rounded,
    permission: 'announcements.create',
  ),
  ActionItem(
    key: 'schedules',
    title: 'Jadwal',
    icon: Icons.calendar_month_rounded,
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
