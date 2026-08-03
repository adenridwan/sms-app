import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/action_item.dart';

/// Pintasan pilihan pengguna untuk grid Beranda.
///
/// Disimpan **lokal di perangkat**, bukan di server: ini preferensi perangkat,
/// bukan data sekolah. Konsekuensinya pengaturan tetap berfungsi saat offline
/// dan tak menambah tabel, endpoint, maupun sinkronisasi.
class ShortcutStore {
  static const _key = 'home_shortcuts';

  Future<List<String>?> load() async {
    final prefs = await SharedPreferences.getInstance();
    final list = prefs.getStringList(_key);
    return (list == null || list.isEmpty) ? null : list;
  }

  Future<void> save(List<String> keys) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(_key, keys);
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}

final shortcutStoreProvider = Provider<ShortcutStore>((ref) => ShortcutStore());

/// Kunci aksi yang disematkan pengguna. `null` = belum pernah diatur, sehingga
/// grid memakai urutan bawaan katalog.
class ShortcutController extends StateNotifier<List<String>?> {
  ShortcutController(this._store) : super(null) {
    _load();
  }

  final ShortcutStore _store;

  /// Banyaknya pintasan yang tampil di Beranda sebelum ubin "Semua menu".
  /// Dengan batas ini, tinggi Beranda tidak ikut tumbuh saat menu bertambah.
  static const maxVisible = 7;

  Future<void> _load() async {
    state = await _store.load();
  }

  Future<void> toggle(String key) async {
    final current = [...?state];
    if (current.contains(key)) {
      current.remove(key);
    } else {
      current.add(key);
    }
    state = current;
    await _store.save(current);
  }

  Future<void> reset() async {
    state = null;
    await _store.clear();
  }

  bool isPinned(String key) => state?.contains(key) ?? false;
}

final shortcutControllerProvider =
    StateNotifierProvider<ShortcutController, List<String>?>(
  (ref) => ShortcutController(ref.watch(shortcutStoreProvider)),
);

/// Aksi yang tampil di grid Beranda: pilihan pengguna bila ada, jika tidak
/// urutan bawaan katalog — keduanya dipotong pada [ShortcutController.maxVisible].
List<ActionItem> visibleShortcuts(
  List<ActionItem> allowed,
  List<String>? pinned,
) {
  if (pinned == null || pinned.isEmpty) {
    return allowed.take(ShortcutController.maxVisible).toList();
  }

  // Urutan mengikuti urutan penyematan, dan tetap disaring izin agar pintasan
  // lama tidak menembus batas peran setelah izin user berubah.
  final byKey = {for (final a in allowed) a.key: a};
  return pinned
      .map((k) => byKey[k])
      .whereType<ActionItem>()
      .take(ShortcutController.maxVisible)
      .toList();
}
