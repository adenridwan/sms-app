import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';

/// Mengunci aplikasi setelah menganggur, tanpa mengakhiri sesi.
///
/// **Kenapa ini ada di dalam aplikasi, bukan mengandalkan kunci layar OS.**
/// Aplikasi tidak bisa memaksa perangkat mengunci layar setelah 30 detik:
/// mengubah `SCREEN_OFF_TIMEOUT` menuntut izin khusus, dan layar mati pun belum
/// tentu terkunci — masih ada jeda "lock after screen timeout" yang dipegang
/// pengguna. Satu-satunya batas waktu yang benar-benar bisa dijamin adalah
/// batas milik aplikasi sendiri.
///
/// Yang dilindungi: daftar siswa, kode QR, dan antrean absensi yang tersimpan
/// di perangkat. Mengunci **tidak** menghapus sesi — petugas cukup memasukkan
/// password lagi, dan verifikasinya lokal (PBKDF2, lihat
/// [OfflineCredentialStore]) sehingga tetap jalan tanpa server.
class AppLockController extends StateNotifier<bool> {
  AppLockController() : super(false);

  Timer? _timer;

  /// Ada interaksi — mulai lagi hitungan dari nol.
  void poke() {
    if (state) return; // sudah terkunci; sentuhan di layar kunci tak menghitung
    _timer?.cancel();
    _timer = Timer(AppConfig.idleLockTimeout, lock);
  }

  void lock() {
    _timer?.cancel();
    _timer = null;
    if (!state) state = true;
  }

  void unlock() {
    state = false;
    poke();
  }

  /// Berhenti menghitung (mis. saat pengguna keluar dari sesi).
  void stop() {
    _timer?.cancel();
    _timer = null;
    state = false;
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}

final appLockProvider =
    StateNotifierProvider<AppLockController, bool>((ref) => AppLockController());
