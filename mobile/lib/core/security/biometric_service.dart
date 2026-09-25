import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';

/// Apa yang bisa dipakai perangkat ini untuk mengenali pemiliknya.
class BiometricCapability {
  const BiometricCapability({
    required this.available,
    required this.types,
  });

  const BiometricCapability.none()
      : available = false,
        types = const [];

  /// Perangkat punya sensor **dan** pemiliknya sudah mendaftarkan sidik jari
  /// atau wajah. Keduanya harus benar: sensor tanpa pendaftaran hanya
  /// menghasilkan dialog yang langsung gagal.
  final bool available;

  final List<BiometricType> types;

  bool get hasFingerprint => types.contains(BiometricType.fingerprint);
  bool get hasFace => types.contains(BiometricType.face);

  /// Nama yang dipakai di layar, mengikuti apa yang benar-benar terdaftar.
  ///
  /// Android kerap hanya melaporkan `strong`/`weak` tanpa memerinci jenisnya —
  /// menyebut "Sidik Jari" di situ bisa keliru kalau yang terdaftar wajah, jadi
  /// dipakai istilah netral.
  String get label {
    if (hasFingerprint && hasFace) return 'Sidik Jari & Wajah';
    if (hasFingerprint) return 'Sidik Jari';
    if (hasFace) return 'Wajah';
    return 'Biometrik';
  }
}

/// Hasil satu kali permintaan biometrik.
enum BiometricOutcome {
  success,

  /// Pengguna membatalkan, atau sidik jari/wajahnya tidak dikenali.
  failed,

  /// Tidak ada yang terdaftar, sensor terkunci karena terlalu sering gagal,
  /// atau perangkat memang tidak mendukung. Pembeda ini penting: pengguna
  /// tidak bisa berbuat apa-apa dengan "coba lagi".
  unavailable,
}

/// Pembungkus `local_auth`.
///
/// Dipisah jadi kelas sendiri supaya layar tidak perlu tahu kode galat
/// platform, dan supaya sisa aplikasi bisa diuji tanpa sensor sungguhan.
class BiometricService {
  BiometricService([LocalAuthentication? auth])
      : _auth = auth ?? LocalAuthentication();

  final LocalAuthentication _auth;

  Future<BiometricCapability> capability() async {
    try {
      if (!await _auth.isDeviceSupported()) {
        return const BiometricCapability.none();
      }

      final types = await _auth.getAvailableBiometrics();

      // Daftar kosong berarti sensornya ada tapi belum ada yang didaftarkan.
      // Diperlakukan sebagai tidak tersedia, karena menyalakan sakelarnya
      // hanya akan menghasilkan dialog yang gagal terus.
      return BiometricCapability(available: types.isNotEmpty, types: types);
    } on PlatformException {
      return const BiometricCapability.none();
    } on MissingPluginException {
      // Platform tanpa implementasi (mis. web). Bukan galat yang perlu
      // ditampilkan — fiturnya cukup tidak muncul.
      return const BiometricCapability.none();
    }
  }

  /// Minta bukti biometrik. [reason] tampil di dialog sistem.
  Future<BiometricOutcome> authenticate({required String reason}) async {
    try {
      final ok = await _auth.authenticate(
        localizedReason: reason,
        // `biometricOnly: true` — PIN/pola perangkat sengaja tidak diterima.
        // Kunci ini melindungi data sekolah di perangkat yang PIN-nya kerap
        // diketahui bersama di ruang guru; yang diminta adalah orangnya.
        biometricOnly: true,
        // Dialog biometrik memindahkan aplikasi ke latar sesaat di sebagian
        // perangkat; tanpa ini permintaannya gagal sendiri.
        persistAcrossBackgrounding: true,
      );
      return ok ? BiometricOutcome.success : BiometricOutcome.failed;
    } on PlatformException catch (e) {
      return switch (e.code) {
        'NotAvailable' ||
        'NotEnrolled' ||
        'PasscodeNotSet' ||
        'LockedOut' ||
        'PermanentlyLockedOut' =>
          BiometricOutcome.unavailable,
        _ => BiometricOutcome.failed,
      };
    } on MissingPluginException {
      return BiometricOutcome.unavailable;
    }
  }
}

final biometricServiceProvider =
    Provider<BiometricService>((ref) => BiometricService());

/// Kemampuan perangkat, dibaca sekali lalu di-cache.
///
/// Bisa berubah tanpa sepengetahuan aplikasi (pengguna mendaftarkan sidik jari
/// lewat Setelan sistem), jadi layar Pengaturan me-*refresh*-nya saat dibuka.
final biometricCapabilityProvider = FutureProvider<BiometricCapability>(
  (ref) => ref.watch(biometricServiceProvider).capability(),
);
