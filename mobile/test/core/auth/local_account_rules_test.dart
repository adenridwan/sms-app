// Aturan akun lokal: dipakai untuk menyiapkan perangkat sebelum ada sekolah
// yang dituju, lalu berhenti berlaku begitu sekolah tersambung.
//
// Yang dikunci di sini adalah keputusan produknya, bukan tampilannya:
// akun lama tidak boleh bisa dipakai lagi sesudah menyambung, tapi membuat
// akun baru harus tetap mungkin selama perangkat tidak tersambung.

import 'package:flutter_test/flutter_test.dart';
import 'package:sms_mobile/core/database/database.dart';
import 'package:sms_mobile/core/auth/local_session_controller.dart';

/// `LocalSession` cukup dibuat langsung — aturannya murni turunan dari ada
/// atau tidaknya catatan koneksi, tanpa menyentuh database.
void main() {
  group('canUseLocalAccount', () {
    test('terbuka selama perangkat belum tersambung ke sekolah', () {
      const session = LocalSession(status: LocalSessionStatus.ready);

      expect(session.isConnectedToBackend, isFalse);
      expect(session.canUseLocalAccount, isTrue);
    });

    test('tertutup begitu ada catatan koneksi', () {
      final session = LocalSession(
        status: LocalSessionStatus.ready,
        connection: _connection(),
      );

      expect(session.isConnectedToBackend, isTrue);
      expect(session.canUseLocalAccount, isFalse);
    });
  });

  group('isLocalLoggedIn', () {
    test('false tanpa akun', () {
      const session = LocalSession(status: LocalSessionStatus.ready);
      expect(session.isLocalLoggedIn, isFalse);
    });

    test('clearAccount menutup sesi akun lokal', () {
      final session = LocalSession(
        status: LocalSessionStatus.ready,
        account: _account(),
      );
      expect(session.isLocalLoggedIn, isTrue);

      expect(session.copyWith(clearAccount: true).isLocalLoggedIn, isFalse);
    });
  });

  test('memutus koneksi membuka jalur akun BARU, bukan yang lama', () {
    // Akun lama dihapus dari database saat menyambung (lihat
    // LocalAuthService.deleteAllAccounts), jadi yang tersisa hanyalah
    // kesempatan mendaftar ulang — bukan kredensial lama yang hidup kembali.
    final connected = LocalSession(
      status: LocalSessionStatus.ready,
      connection: _connection(),
    );
    expect(connected.canUseLocalAccount, isFalse);

    final disconnected = connected.copyWith(clearConnection: true);
    expect(disconnected.canUseLocalAccount, isTrue);
    expect(disconnected.isLocalLoggedIn, isFalse);
  });
}

BackendConnectionData _connection() => BackendConnectionData(
      id: 1,
      apiUrl: 'https://sekolah.test/api/v1',
      syncToken: 'token',
      schoolName: 'SMP Contoh',
      permissionsJson: '[]',
      rolesJson: '[]',
      connectedAt: DateTime(2026, 9, 25),
    );

LocalAccount _account() => LocalAccount(
      id: 'a1',
      email: 'operator@contoh.test',
      fullName: 'Operator Uji',
      passwordHash: 'hash',
      passwordSalt: 'salt',
      createdAt: DateTime(2026, 9, 1),
      updatedAt: DateTime(2026, 9, 1),
    );
