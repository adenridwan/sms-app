# Analisa & Plan UI/UX — Jadwal (Jam Pelajaran)

Lanjutan dari [00-ANALISA-KURIKULUM-MAPEL-JADWAL.md](00-ANALISA-KURIKULUM-MAPEL-JADWAL.md) §6 langkah 3, sekarang Kurikulum & Mata Pelajaran sudah jalan.

## 1. Kondisi sebelum perbaikan

- `schedules`+`time_slots` skemanya solid (lihat migration), tapi:
  - `ScheduleController` lama mengimpor `App\Models\Academic\Schedule` yang tidak eksis, dan validasi/query-nya pakai kolom lama (`class_room_id`, `day` string enum, `start_time`/`end_time`) yang **tidak cocok** dengan skema aktual (`classroom_id`, `day_of_week` tinyint, `time_slot_id`). Fatal error kalau di-hit.
  - Route `schedules/generate` menunjuk method `generate` yang **tidak pernah ada** di controller manapun.
  - `TimeSlotController` masih stub 501.
  - `ClassroomController::schedule()` cuma `return []` dengan pesan "Jadwal belum tersedia."
  - Halaman React `academic/Schedules.tsx` belum ada (route web sudah ada, menunjuk komponen yang belum dibuat).
- Konsumen baca-saja yang sudah ada dan **harus tetap jalan**: `TeacherAssignmentService` (jadwal mengajar guru, raw query) dan `User::teachingClassroomIds()` (kontrol akses kelas). Keduanya raw query ke tabel `schedules`, tidak lewat model — implementasi baru tidak boleh mengubah struktur tabel.

## 2. Keputusan scope

- **Tidak** membangun auto-generate jadwal (`schedules/generate`) — fitur itu spekulatif dan tidak diminta; route dihapus daripada dibiarkan jadi dead code lagi.
- **Tidak** menambah item sidebar baru untuk "Jam Pelajaran" (time slots) — `MenuRegistry` sudah final untuk grup Akademik (7 item). Jam Pelajaran dikelola lewat dialog "Kelola Jam Pelajaran" di dalam halaman Jadwal, karena ia data pendukung (prasyarat) buat jadwal, bukan entitas berdiri sendiri di menu.
- Konflik yang divalidasi:
  - **Kelas bentrok** (kelas+semester+hari+jam yang sama) — sudah dijamin unique constraint DB (`schedule_unique`), controller cukup menerjemahkan pelanggaran itu jadi pesan 422 yang jelas.
  - **Guru bentrok** (guru mengajar 2 kelas di hari+jam+semester yang sama) — tidak ada constraint DB (karena secara desain guru boleh saja punya penugasan ganda dalam kasus tertentu), jadi divalidasi di controller sebagai error 422 yang bisa dilewati kalau memang perlu (soft check, bukan hard block DB).

## 3. UI/UX halaman Jadwal (`/academic/schedules`)

Beda dari Kurikulum/Mapel (list biasa), Jadwal secara alami berbentuk **grid mingguan** (Hari × Jam Ke), jadi tidak memakai pola Table+Dialog CRUD standar, tapi tetap pakai komponen shadcn yang sama (Card, Select, Dialog, Badge, Button).

```
Jadwal Pelajaran                                    [⚙ Kelola Jam Pelajaran]
Kelola jadwal pelajaran per kelas per semester

┌─ Card: Filter ────────────────────────────────────────────────────────┐
│ Tahun Ajaran [2025/2026 ▾]   Semester [Semester 1 ▾]   Kelas [X A ▾] │
└─────────────────────────────────────────────────────────────────────┘

┌─ Card: Grid ───────────────────────────────────────────────────────────────────┐
│ Jam         Senin        Selasa       Rabu         Kamis        Jumat   Sabtu  │
│ 07:00-07:45 Matematika   Fisika       [+ Tambah]   B.Indonesia  ...            │
│             Budi S.      Siti A.                   Rina W.                    │
│ 07:45-08:30 [+ Tambah]   ...                                                   │
│ 08:30-08:45 ─────────────── Istirahat ───────────────────────────────         │
│ ...                                                                             │
└──────────────────────────────────────────────────────────────────────────────┘
```

- Filter Tahun Ajaran & Semester wajib dipilih dulu (default: yang aktif); Kelas wajib dipilih sebelum grid tampil ("Pilih kelas untuk menampilkan jadwal" bila belum).
- Baris grid = `time_slots` diurutkan `order`; baris `is_break` dirender sebagai satu baris penuh bertuliskan "Istirahat" (bukan per-hari).
- Kolom grid = Senin–Sabtu (`day_of_week` 1–6). Minggu (7) tidak ditampilkan di grid (sekolah tidak masuk Minggu), meski skema DB mengizinkannya.
- Sel kosong → tombol "+ Tambah" kecil, transparan, muncul saat hover. Sel terisi → menampilkan nama mapel (bold) + nama guru (kecil, muted), seluruh sel bisa diklik untuk **Edit**, ada ikon hapus kecil di pojok saat hover.

### Dialog Tambah/Edit Jadwal

Dipicu dari klik sel grid — Hari & Jam Ke otomatis terisi dari sel yang diklik (read-only, ditampilkan sebagai teks bukan Select, supaya jelas konteksnya), field lain diisi manual:

| Field | Komponen | Wajib | Catatan |
|---|---|---|---|
| Hari & Jam | teks statis | — | contoh: "Senin, 07:00–07:45", dari sel yang diklik |
| Mata Pelajaran | `Select` | ya | dari `subjectsApi` (aktif saja) |
| Guru | `Select` | ya | dari `teachersApi` (aktif saja), tampilkan nama lengkap |
| Ruangan | `Input` | opsional | override nama ruangan kelas, placeholder = ruangan default kelas |
| Status Aktif | `Switch` | — | default aktif |

Submit error dari backend (kelas bentrok / guru bentrok) ditampilkan sebagai toast — pesan persis dari API (`getErrorMessage`), tidak digeneralisasi, supaya user tahu itu konflik guru vs konflik kelas.

### Dialog "Kelola Jam Pelajaran"

Dipicu tombol header. CRUD ringkas (Table+Dialog kecil di dalam dialog besar, mirip pola "Kelola Guru Pengampu" di `ClassRooms.tsx`): Nama (contoh "Jam 1"), Jam Mulai, Jam Selesai, Urutan, toggle "Ini jam istirahat". Dipakai lintas kelas/semester (`time_slots` tidak terikat kelas), jadi perubahan di sini berlaku ke semua grid.

## 4. Konsistensi teknis

- Icon, toast, error-message helper — sama dengan Kurikulum/Mapel.
- `Curriculum`/`Subject`-style types & api service baru: `Schedule`, `TimeSlot`, `scheduleApi`, `timeSlotsApi`.
- Guard permission: `schedules.manage` (sudah ada di `PermissionSeeder`/`RoleSeeder`) untuk create/update/delete Jadwal & Jam Pelajaran.
