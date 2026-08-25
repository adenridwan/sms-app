import 'package:shared_preferences/shared_preferences.dart';

/// Kapan terakhir kali antrean berhasil dikirim ke server.
///
/// Dipakai layar Pengaturan untuk menjawab satu pertanyaan yang sering muncul
/// di lapangan: "data saya sudah masuk belum?". Jumlah antrean saja tak cukup —
/// antrean kosong bisa berarti "sudah terkirim" atau "memang belum ada apa-apa".
class SyncStatusStore {
  static const _key = 'last_synced_at';

  Future<DateTime?> lastSyncedAt() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return null;
    return DateTime.tryParse(raw);
  }

  Future<void> markSynced([DateTime? at]) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, (at ?? DateTime.now()).toIso8601String());
  }
}
