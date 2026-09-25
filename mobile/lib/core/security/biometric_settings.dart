import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Pilihan pengguna soal biometrik.
class BiometricSettings {
  const BiometricSettings({
    this.unlockEnabled = false,
    this.requireOnLaunch = false,
    this.loaded = false,
  });

  /// Boleh membuka kunci menganggur dengan sidik jari / wajah, bukan password.
  final bool unlockEnabled;

  /// Kunci langsung dipasang tiap aplikasi dibuka atau kembali dari latar,
  /// tanpa menunggu batas menganggur.
  final bool requireOnLaunch;

  /// False selama nilai dari disk belum terbaca.
  ///
  /// Dibedakan dari "mati" supaya [AppLockGate] tidak salah mengunci — atau
  /// salah membiarkan terbuka — pada beberapa frame pertama.
  final bool loaded;

  BiometricSettings copyWith({
    bool? unlockEnabled,
    bool? requireOnLaunch,
    bool? loaded,
  }) {
    return BiometricSettings(
      unlockEnabled: unlockEnabled ?? this.unlockEnabled,
      requireOnLaunch: requireOnLaunch ?? this.requireOnLaunch,
      loaded: loaded ?? this.loaded,
    );
  }
}

/// Menyimpan pilihan biometrik di SharedPreferences.
///
/// Sengaja **tidak** di secure storage: yang disimpan hanya dua sakelar, bukan
/// rahasia. Bukti identitasnya sendiri tidak pernah meninggalkan sensor —
/// aplikasi cuma menerima jawaban ya/tidak dari sistem.
class BiometricSettingsController extends StateNotifier<BiometricSettings> {
  BiometricSettingsController() : super(const BiometricSettings()) {
    _load();
  }

  static const _kUnlock = 'biometric_unlock_enabled';
  static const _kOnLaunch = 'biometric_require_on_launch';

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    state = BiometricSettings(
      unlockEnabled: prefs.getBool(_kUnlock) ?? false,
      requireOnLaunch: prefs.getBool(_kOnLaunch) ?? false,
      loaded: true,
    );
  }

  Future<void> setUnlockEnabled(bool value) async {
    // Mematikan biometrik ikut mematikan kunci-saat-dibuka: tanpa biometrik,
    // sakelar itu hanya berarti mengetik password tiap kali membuka aplikasi —
    // hukuman yang tidak pernah diminta siapa pun.
    state = state.copyWith(
      unlockEnabled: value,
      requireOnLaunch: value ? state.requireOnLaunch : false,
    );

    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kUnlock, value);
    if (!value) await prefs.setBool(_kOnLaunch, false);
  }

  Future<void> setRequireOnLaunch(bool value) async {
    if (value && !state.unlockEnabled) return;

    state = state.copyWith(requireOnLaunch: value);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kOnLaunch, value);
  }
}

final biometricSettingsProvider =
    StateNotifierProvider<BiometricSettingsController, BiometricSettings>(
  (ref) => BiometricSettingsController(),
);
