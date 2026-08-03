import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/notifications/presentation/notification_controller.dart';

/// Kerangka aplikasi: bottom navigation 4 tab tetap
/// (`Beranda · Absensi · Notifikasi · Profil`).
///
/// Tab sengaja **tidak** berubah-ubah per peran — perbedaan izin tampil di
/// grid aksi Beranda, bukan di navigasi (lihat docs/04-NAVIGATION-MENU.md §2).
/// Pengecualian: tab Absensi disembunyikan bila user tak punya izin mencatat
/// absensi, karena tab itu tak akan berguna sama sekali baginya.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user;
    final unread = ref.watch(notificationControllerProvider).unreadCount;
    final canAttend = user?.hasPermission('attendance.record') ?? false;

    // Indeks branch di router tetap (0..3); yang disembunyikan hanya tampilan
    // tab-nya, supaya tak perlu menyusun ulang branch saat izin berubah.
    final destinations = <({int branch, NavigationDestination dest})>[
      (
        branch: 0,
        dest: const NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home_rounded),
          label: 'Beranda',
        )
      ),
      if (canAttend)
        (
          branch: 1,
          dest: const NavigationDestination(
            icon: Icon(Icons.qr_code_scanner_outlined),
            selectedIcon: Icon(Icons.qr_code_scanner_rounded),
            label: 'Absensi',
          )
        ),
      (
        branch: 2,
        dest: NavigationDestination(
          icon: Badge.count(
            count: unread,
            isLabelVisible: unread > 0,
            child: const Icon(Icons.notifications_none_rounded),
          ),
          selectedIcon: Badge.count(
            count: unread,
            isLabelVisible: unread > 0,
            child: const Icon(Icons.notifications_rounded),
          ),
          label: 'Notifikasi',
        )
      ),
      (
        branch: 3,
        dest: const NavigationDestination(
          icon: Icon(Icons.person_outline_rounded),
          selectedIcon: Icon(Icons.person_rounded),
          label: 'Profil',
        )
      ),
    ];

    final selected = destinations
        .indexWhere((d) => d.branch == navigationShell.currentIndex)
        .clamp(0, destinations.length - 1);

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selected,
        onDestinationSelected: (i) => navigationShell.goBranch(
          destinations[i].branch,
          // Tap tab yang sedang aktif → kembali ke akar tab tsb.
          initialLocation: destinations[i].branch == navigationShell.currentIndex,
        ),
        destinations: [for (final d in destinations) d.dest],
      ),
    );
  }
}
