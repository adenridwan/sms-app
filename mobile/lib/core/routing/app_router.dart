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
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/signup_screen.dart';
import '../auth/local_session_controller.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/finance/presentation/finance_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/notifications/presentation/notification_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/profile/presentation/server_config_screen.dart';
import '../../features/profile/presentation/settings_screen.dart';
import 'app_shell.dart';

/// Menjembatani perubahan state Riverpod ke GoRouter (refresh + redirect).
///
/// Gerbangnya memakai [authControllerProvider] — sesi **backend** — bukan
/// `localSessionProvider`. Seluruh isi aplikasi (absensi, antrean, kunci layar)
/// sudah membaca sesi backend, jadi gerbang yang menanyakan tabel akun lokal
/// menolak masuk orang yang sebenarnya sudah punya token sah: itulah sebabnya
/// "Scan QR Koneksi" berhasil tapi tetap terdampar di layar login.
class _RouterNotifier extends ChangeNotifier {
  _RouterNotifier(this._ref) {
    _ref.listen<AuthState>(
      authControllerProvider,
      (_, __) => notifyListeners(),
    );
    // Akun lokal juga membuka gerbang, selama perangkat belum tersambung.
    _ref.listen<bool>(
      localSessionProvider.select((s) => s.isLocalLoggedIn),
      (_, __) => notifyListeners(),
    );
  }

  final Ref _ref;

  String? redirect(BuildContext context, GoRouterState state) => authRedirect(
        _ref.read(authControllerProvider),
        state.matchedLocation,
        localLoggedIn: _ref.read(localSessionProvider).isLocalLoggedIn,
      );
}

/// Layar yang boleh dibuka tanpa sesi.
///
/// `/connect-scan` dan `/provision-scan` ada di sini karena keduanya justru
/// cara memperoleh sesi. `/provision-scan` sempat tidak terdaftar, sehingga
/// deep link `smsapp://provision?token=…` di perangkat yang belum masuk
/// dilempar ke `/login` dan tokennya terbuang.
const kPublicRoutes = {
  '/login',
  '/signup',
  '/connect-scan',
  '/provision-scan',
};

/// Aturan gerbang autentikasi, dipisah dari GoRouter supaya bisa diuji tanpa
/// merakit seluruh aplikasi. Inilah bagian yang dulu menanyakan sistem yang
/// salah, jadi ia layak punya uji sendiri.
/// [localLoggedIn] — masuk memakai akun lokal, yang hanya mungkin di perangkat
/// yang belum tersambung ke sekolah mana pun (lihat
/// `LocalSession.canUseLocalAccount`). Akun itu tidak punya token, jadi ia
/// membuka aplikasi tanpa menjanjikan apa pun soal API.
String? authRedirect(
  AuthState auth,
  String loc, {
  bool localLoggedIn = false,
}) {
  // Masih memulihkan sesi dari token tersimpan.
  if (auth.status == AuthStatus.unknown) {
    return loc == '/splash' ? null : '/splash';
  }

  if (auth.isAuthenticated || localLoggedIn) {
    // `/connect-scan` sengaja tidak ikut dilempar: pengguna yang sudah masuk
    // membukanya dari Profil untuk berpindah sekolah / server.
    if (loc == '/login' || loc == '/splash') return '/home';
    return null;
  }

  return kPublicRoutes.contains(loc) ? null : '/login';
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
      // Login memakai akun **sekolah** (email + password di server), dengan
      // cadangan offline lewat OfflineCredentialStore. Tabel `local_accounts`
      // beserta layar Buat Akun tidak lagi jadi jalan masuk: passwordnya tak
      // pernah bisa diverifikasi server, sehingga akun yang dibuat di sana
      // selalu tertolak begitu menyentuh API.
      GoRoute(
        path: '/login',
        builder: (_, __) => const LoginScreen(),
      ),
      // Akun lokal untuk menyiapkan perangkat sebelum ada sekolah yang dituju.
      // Tertutup sendiri begitu QR Koneksi sukses — lihat
      // `LocalSession.canUseLocalAccount`.
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
      // Konfigurasi server manual — hanya bisa diakses setelah login
      // untuk alasan keamanan (mencegah phishing ke server palsu).
      GoRoute(
        parentNavigatorKey: _rootNavigatorKey,
        path: '/server-config',
        builder: (_, __) => const ServerConfigScreen(),
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
