import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/attendance/presentation/manual_input_screen.dart';
import '../../features/attendance/presentation/offline_queue_screen.dart';
import '../../features/attendance/presentation/scan_camera_screen.dart';
import '../../features/attendance/presentation/scanner_home_screen.dart';
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';

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
      if (loc == '/login' || loc == '/splash') return '/dashboard';
      return null;
    }

    // unauthenticated / authenticating → tetap di login
    return loc == '/login' ? null : '/login';
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  final notifier = _RouterNotifier(ref);
  return GoRouter(
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
      GoRoute(
        path: '/dashboard',
        builder: (_, __) => const ScannerHomeScreen(),
      ),
      GoRoute(
        path: '/scan',
        builder: (_, __) => const ScanCameraScreen(),
      ),
      GoRoute(
        path: '/manual',
        builder: (_, __) => const ManualInputScreen(),
      ),
      GoRoute(
        path: '/queue',
        builder: (_, __) => const OfflineQueueScreen(),
      ),
    ],
  );
});
