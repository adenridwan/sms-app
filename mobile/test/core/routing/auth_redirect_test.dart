// Gerbang autentikasi: dulu ia menanyakan tabel akun lokal, sehingga perangkat
// yang sudah memegang token sah hasil "Scan QR Koneksi" tetap dilempar ke
// /login dan tidak pernah bisa masuk. Uji ini mengunci aturannya.

import 'package:flutter_test/flutter_test.dart';
import 'package:sms_mobile/core/routing/app_router.dart';
import 'package:sms_mobile/features/auth/models/user.dart';
import 'package:sms_mobile/features/auth/presentation/auth_controller.dart';

const _user = User(
  id: 'u1',
  fullName: 'Petugas Gerbang',
  email: 'petugas@sekolah.sch.id',
  userType: 'staff',
);

const _unknown = AuthState();
const _signedOut = AuthState(status: AuthStatus.unauthenticated);
const _signedIn = AuthState(status: AuthStatus.authenticated, user: _user);

void main() {
  group('sesi belum diketahui', () {
    test('menahan semua orang di splash sampai token selesai dibaca', () {
      expect(authRedirect(_unknown, '/home'), '/splash');
      expect(authRedirect(_unknown, '/login'), '/splash');
    });

    test('membiarkan splash apa adanya', () {
      expect(authRedirect(_unknown, '/splash'), isNull);
    });
  });

  group('belum masuk', () {
    test('melempar layar dalam aplikasi ke login', () {
      expect(authRedirect(_signedOut, '/home'), '/login');
      expect(authRedirect(_signedOut, '/attendance'), '/login');
      expect(authRedirect(_signedOut, '/splash'), '/login');
    });

    test('membuka layar yang justru dipakai memperoleh sesi', () {
      for (final route in kPublicRoutes) {
        expect(authRedirect(_signedOut, route), isNull, reason: route);
      }
    });
  });

  group('sudah masuk', () {
    test('tidak menahan di layar login atau splash', () {
      expect(authRedirect(_signedIn, '/login'), '/home');
      expect(authRedirect(_signedIn, '/splash'), '/home');
    });

    test('membiarkan isi aplikasi', () {
      expect(authRedirect(_signedIn, '/home'), isNull);
      expect(authRedirect(_signedIn, '/attendance'), isNull);
    });

    test('tetap mengizinkan pindah sekolah lewat scan koneksi', () {
      // Dibuka dari Profil; melemparnya ke /home akan menutup satu-satunya
      // cara mengarahkan perangkat ke server lain.
      expect(authRedirect(_signedIn, '/connect-scan'), isNull);
    });
  });
}
