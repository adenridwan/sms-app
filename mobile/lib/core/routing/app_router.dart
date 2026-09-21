import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/attendance/presentation/class_attendance_screen.dart';
import '../../features/attendance/presentation/manual_input_screen.dart';
import '../../features/attendance/presentation/offline_queue_screen.dart';
import '../../features/attendance/presentation/scan_camera_screen.dart';
import '../../features/attendance/presentation/scanner_home_screen.dart';
import '../../features/auth/presentation/change_password_screen.dart';
import '../../features/auth/presentation/connection_scanner_screen.dart';
import '../../features/auth/presentation/provision_scanner_screen.dart';
import '../../features/auth/presentation/local_login_screen.dart';
import '../../features/auth/presentation/signup_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/finance/presentation/finance_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/notifications/presentation/notification_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/profile/presentation/settings_screen.dart';
import '../auth/local_session_controller.dart';
import 'app_shell.dart';

/// Menjembatani perubahan state Riverpod ke GoRouter (refresh + redirect).
class _RouterNotifier extends ChangeNotifier {
  _RouterNotifier(this._ref) {
    _ref.listen<LocalSession>(
      localSessionProvider,
      (_, __) => notifyListeners(),
    );
  }

  final Ref _ref;

  String? redirect(BuildContext context, GoRouterState state) {
    final session = _ref.read(localSessionProvider);
    final loc = state.matchedLocation;

    // Still initializing
    if (session.status == LocalSessionStatus.unknown) {
      return loc == '/splash' ? null : '/splash';
    }

    // Authenticated - redirect away from auth screens
    if (session.isLoggedIn) {
      if (loc == '/login' || loc == '/splash' || loc == '/signup') {
        return '/home';
      }
      return null;
    }

    // Not authenticated - allow auth screens only
    if (loc == '/login' || loc == '/signup' || loc == '/connect-scan') {
      return null;
    }
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
      // Auth flow
      GoRoute(
        path: '/splash',
        builder: (_, __) => const SplashScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (_, __) => const LocalLoginScreen(),
      ),
      GoRoute(
        path: '/signup',
        builder: (_, __) => const SignupScreen(),
      ),
      // Scan QR for backend connection (can be accessed before or after login)
      GoRoute(
        path: '/connect-scan',
        builder: (_, __) => const ConnectionScannerScreen(),
      ),

      // Deep link `smsapp://provision?token=...` mendarat di sini. Rutenya
      // sempat tidak terdaftar sama sekali, sehingga DeepLinkHandler menavigasi
      // ke alamat yang tidak ada dan membuang token yang sudah ditangkap.
      // Token dibaca dari query agar pengguna tidak perlu memindai ulang QR
      // yang barusan membuka aplikasi.
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
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/change-password',
        builder: (_, __) => const ChangePasswordScreen(),
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
