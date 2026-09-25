# 10 — Konsep Menu Jadwal (cache baca-saja)

> Status: **konsep, belum diimplementasikan.** Ditulis 2026-09-25 setelah
> menelusuri endpoint backend dan skema tabel di kedua sisi.

Menu `Jadwal` sudah ada di katalog aksi Beranda (`ActionItem.key = 'timetable'`)
tapi `route`-nya masih `null`, sehingga kartunya tampil nonaktif. Dokumen ini
menetapkan bentuk datanya sebelum layarnya dibangun.

---

## 1. Yang sudah ada di backend

| Endpoint | Catatan |
|---|---|
| `GET /schedules?classroom_id=&semester_id=` | Keduanya **wajib** (`required, uuid`). Berorientasi admin |
| `GET /classrooms/{id}/schedule` | `semester_id` opsional → jatuh ke semester aktif. **Ini yang dipakai mobile** |

Bentuk satu baris (`ScheduleResource`):

```
id, classroom_id, semester_id, day_of_week (1=Senin … 7=Minggu),
subject { id, name, code },
teacher { id, full_name },
time_slot { id, name, start_time "HH:MM", end_time "HH:MM", order, is_break }
```

**Yang belum ada: endpoint "jadwal saya" lintas kelas.** Parameter `?teacher_id=`
ada pada `GET /schedules`, tapi tetap menuntut `classroom_id`, jadi tidak bisa
menjawab "jadwal mengajar saya" dalam satu panggilan.

## 2. Yang belum ada di mobile

Tidak ada tabel jadwal di SQLite. Pola cache yang sudah mapan di proyek ini —
tabel cache + satu baris `SyncMetadata(entityType, lastSyncedAt, …)` — akan
diikuti apa adanya.

---

## 3. Prinsip: jadwal bukan transaksi

Jadwal adalah **data rujukan baca-saja yang jarang berubah**, kebalikan dari
absensi. Konsekuensinya menyederhanakan banyak hal:

- Tidak perlu antrean kirim
- Tidak perlu resolusi konflik
- Tidak perlu kolom `syncStatus` / `syncAttempts` / `syncError`

Arahnya satu: server → perangkat.

## 4. Tabel cache

```dart
class CachedSchedules extends Table {
  TextColumn get id => text()();
  TextColumn get classroomId => text()();
  TextColumn get classroomName => text()();
  TextColumn get semesterId => text()();
  IntColumn  get dayOfWeek => integer()();   // 1=Senin … 7=Minggu
  TextColumn get subjectName => text()();
  TextColumn get teacherName => text().nullable()();
  TextColumn get slotName => text()();
  TextColumn get startTime => text()();      // "07:00"
  TextColumn get endTime => text()();
  IntColumn  get slotOrder => integer()();
  BoolColumn get isBreak => boolean()();

  @override
  Set<Column> get primaryKey => {id};
}
```

**Jam disimpan sebagai TEXT apa adanya, bukan `DateTime`.** Server mengirim
`"07:00"` tanpa tanggal; mengubahnya jadi `DateTime` memaksa menempelkan tanggal
dan zona waktu yang tidak punya arti — sumber bug yang sulit dilacak begitu
perangkat berpindah zona atau melewati pergantian hari.

## 5. Cara menarik

**Tanpa mengubah backend** (bisa dikerjakan hari ini):

- **Guru** — `my_classes` dari `GET /dashboard` sudah memberi daftar kelas yang
  diampu. Panggil `GET /classrooms/{id}/schedule` untuk tiap kelas, lalu
  gabungkan. Jumlahnya kecil (umumnya 1–4 kelas).
- **Admin** — pilih kelas dulu, lalu satu panggilan.

**Bila backend boleh disentuh** (lebih baik): tambahkan `GET /schedules/mine`
yang mengembalikan seluruh baris `teacher_id = user` pada semester aktif. Satu
panggilan, tidak perlu tahu `classroom_id`, dan indeksnya sudah tersedia —
migrasi `0001_01_01_000004_create_academic_tables.php` sudah membuat
`index(['teacher_id', 'semester_id', 'day_of_week'])`.

## 6. Aturan kesegaran

- Tarik saat layar dibuka bila `SyncMetadata(entityType: 'schedules').lastSyncedAt`
  lebih tua dari ±6 jam; plus tarik-segarkan manual.
- Offline → tampilkan cache dengan label "diperbarui &lt;waktu&gt;".
- Belum pernah tersambung ke sekolah → kosong, dengan pesan yang menjelaskan
  sebabnya. Sama seperti Absen Manual, jadwal memang tidak bisa jalan sebelum
  perangkat mengenal sekolahnya.

## 7. Jebakan yang harus ditangani sejak awal

**Jadwal terikat semester.** Saat `semester_id` berganti, baris lama wajib
**dihapus**, bukan ditimpa sebagian — kalau tidak, jadwal semester lalu bercampur
dengan yang baru dan tidak ada cara membedakannya di layar.

Jadi penyimpanannya: hapus semua baris untuk `classroomId` tersebut, lalu masukkan
hasil tarikan yang baru. Bukan `upsert` per baris.

---

## 8. Urutan kerja

1. Tabel `CachedSchedules` + local source + migrasi Drift
2. Repository: tarik per kelas, simpan, baca dari cache saat offline
3. Layar Jadwal: tab hari Senin–Sabtu, daftar slot urut `slotOrder`, baris
   `isBreak` ditampilkan berbeda
4. Isi `route: '/schedule'` pada `ActionItem(key: 'timetable')` — begitu terisi,
   kartu Beranda ikut hidup tanpa perubahan lain

## 9. Keputusan yang masih terbuka

- Pakai jalur per-kelas yang sudah jalan, atau sekalian tambah
  `GET /schedules/mine` di backend?
- Apakah jadwal perlu ikut ditarik saat provisioning (supaya langsung ada), atau
  cukup saat menu Jadwal pertama kali dibuka?
