import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../widgets/sync_status_banner.dart';

/// Kerangka aplikasi: bottom navigation 4 tab tetap
/// (`Beranda · Absensi · Keuangan · Profil`) — susunan dan namanya persis
/// mengikuti `bottomTabs` pada rujukan desain (artifact `0af1e5c5`).
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
        dest: const NavigationDestination(
          icon: Icon(Icons.account_balance_wallet_outlined),
          selectedIcon: Icon(Icons.account_balance_wallet_rounded),
          label: 'Keuangan',
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

    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      body: Column(
        children: [
          // Banner sinkronisasi / offline status di atas konten.
          const SyncStatusBanner(),
          Expanded(child: navigationShell),
        ],
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: scheme.surface,
          border: Border(top: BorderSide(color: scheme.outlineVariant)),
        ),
        child: NavigationBar(
          selectedIndex: selected,
          backgroundColor: Colors.transparent,
          surfaceTintColor: Colors.transparent,
          elevation: 0,
          height: 66,
          // Label selalu tampil: tab di sini jarang ditebak dari ikon saja
          // (Absensi vs Riwayat mudah tertukar).
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
          // Pil bertinta aksen di balik ikon aktif — penanda posisi yang
          // terbaca sekilas, sesuai rujukan desain.
          indicatorColor: scheme.primaryContainer,
          indicatorShape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          onDestinationSelected: (i) => navigationShell.goBranch(
            destinations[i].branch,
            // Tap tab yang sedang aktif → kembali ke akar tab tsb.
            initialLocation:
                destinations[i].branch == navigationShell.currentIndex,
          ),
          destinations: [for (final d in destinations) d.dest],
        ),
      ),
    );
  }
}
