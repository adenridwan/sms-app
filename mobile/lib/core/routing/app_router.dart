import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/attendance/presentation/class_attendance_screen.dart';
import '../../features/attendance/presentation/manual_input_screen.dart';
import '../../features/attendance/presentation/offline_queue_screen.dart';
import '../../features/attendance/presentation/scan_camera_screen.dart';
import '../../features/attendance/presentation/scanner_home_screen.dart';
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/provision_scanner_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/finance/presentation/finance_screen.dart';
import '../../features/notifications/presentation/notification_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/profile/presentation/settings_screen.dart';
import 'app_shell.dart';

/// Menjembatani perubahan state Riverpod ke GoRouter (refresh + redirect).
class _RouterNotifier extends ChangeNotifier {
  _RouterNotifier(this._ref) {
    _ref.listen<AuthState>(
      authControllerProvider,
      (_, __) => notifyListeners(),
    );
  }

  final Ref _ref;

  String? redirect(BuildContext context, GoRouterState state) {
    final status = _ref.read(authControllerProvider).status;
    final loc = state.matchedLocation;

    if (status == AuthStatus.unknown) {
      return loc == '/splash' ? null : '/splash';
    }

    if (status == AuthStatus.authenticated) {
      if (loc == '/login' || loc == '/splash') return '/home';
      return null;
    }

    // unauthenticated / authenticating → tetap di login atau provision-scan
    if (loc == '/login' || loc == '/provision-scan') return null;
    return '/login';
  }
}

final _rootNavigatorKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  final notifier = _RouterNotifier(ref);
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/splash',
    refreshListenable: notifier,
    redirect: notifier.redirect,
    routes: [
      GoRoute(
        path: '/splash',
        builder: (_, __) => const SplashScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (_, __) => const LoginScreen(),
      ),
      // Scan QR provisioning — di luar shell karena belum login.
      // Jika ada query param `token`, langsung proses tanpa scan.
      GoRoute(
        path: '/provision-scan',
        builder: (_, state) => ProvisionScannerScreen(
          initialToken: state.uri.queryParameters['token'],
        ),
      ),

      // App shell — bottom nav 4 tab, tiap tab punya tumpukan navigasi sendiri.
      StatefulShellRoute.indexedStack(
        builder: (_, __, navigationShell) =>
            AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/attendance',
              builder: (_, __) => const ScannerHomeScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/finance',
              builder: (_, __) => const FinanceScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
          ]),
        ],
      ),

      // Dibuka dari Profil, bukan tab tersendiri — sesuai rujukan desain.
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/settings',
        builder: (_, __) => const SettingsScreen(),
      ),

      // Notifikasi kehilangan tabnya karena rujukan desain memakai Keuangan
      // sebagai tab ketiga; fiturnya tetap jalan, dibuka dari Profil.
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/notifications',
        builder: (_, __) => const NotificationScreen(),
      ),

      // Layar penuh di atas shell (bottom nav sengaja disembunyikan agar
      // alur scan/input tidak terganggu).
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/scan',
        builder: (_, __) => const ScanCameraScreen(),
      ),
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/manual',
        builder: (_, __) => const ManualInputScreen(),
      ),
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/class-attendance',
        // `?classroom=<uuid>` datang dari kartu kelas di Beranda, supaya kelas
        // itu langsung terpilih tanpa memilih ulang.
        builder: (_, state) => ClassAttendanceScreen(
          initialClassroomId: state.uri.queryParameters['classroom'],
        ),
      ),
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/queue',
        builder: (_, __) => const OfflineQueueScreen(),
      ),
    ],
  );
});
