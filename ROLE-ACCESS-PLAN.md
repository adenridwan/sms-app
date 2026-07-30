# Rencana Eksekusi: Akses Berbasis Role & Koneksi Data Pengguna

> Status: **SEMUA FASE (1–4) SELESAI** — 48 test Pest lulus + uji E2E tiap fase.
> Diperbarui terakhir: 18 Juli 2026.
>
> Catatan Fase 4: form pengguna kini punya seksi dinamis Data Guru / Data Staf
> (default mengikuti role, bisa keduanya untuk jabatan rangkap) dan pemilih
> siswa multi-pilih untuk role orang tua. Backend menyinkronkan `teachers`,
> `staff`, dan `student_guardians` dalam satu transaksi; penautan memakai
> ulang baris wali tanpa akun (cocok via HP/nama), melepas tautan hanya
> mengosongkan `user_id`. Endpoint baru: `GET admin/users/student-options`.
> Model `Staff` yang dirujuk `User::staff()` ternyata belum pernah ada —
> sudah dibuat. Keputusan terbuka #2: siswa tetap dibuat lewat menu Siswa;
> #3: jumlah anak per akun ortu tidak dibatasi.
>
> Catatan Fase 3: endpoint baru `GET /api/v1/dashboard/class/{classroom}`
> (guard: admin bebas, guru hanya kelas diampu, lainnya 403) memuat jumlah
> siswa, absensi hari ini kelas itu, jadwal guru di kelas itu, dan 5 siswa
> paling sering alfa bulan berjalan. Kelas perwalian menjadi pilihan default
> dropdown via `homeroom_classroom_id`.
>
> Catatan Fase 2: `DashboardController` lama merujuk model `App\Models\...` yang
> tidak pernah ada (selalu fatal) — ditulis ulang di atas `DashboardStatsService`
> (satu sumber untuk API & halaman web). Keputusan terbuka #1 diputuskan sesuai
> matriks R5: kepala sekolah TANPA ringkasan keuangan. Route `/dashboard/stats`
> yang menunjuk method tak ada kini diimplementasikan.
>
> Catatan temuan saat implementasi Fase 1:
> - `schedules.teacher_id` dan `classrooms.homeroom_teacher_id` merujuk **users.id**
>   (bukan teachers.id) — `teacher_classrooms.teacher_id` mengikuti konvensi ini,
>   sehingga rumus R3 dievaluasi langsung dari user login.
> - Status absensi di DB memakai bahasa Inggris ('present', ...) sedangkan
>   controller absensi memvalidasi bahasa Indonesia ('hadir', ...) — ketidaksesuaian
>   lama, perlu dibereskan di fase absensi/dashboard.
> - Relasi `parents` pada model Student tidak pernah ada padahal dipakai
>   StudentResource & show() — sudah ditambahkan sebagai alias guardians.
> - Cache permission Spatie di-hardcode ke Redis; kini `PERMISSION_CACHE_STORE`
>   (testing memakai array).

---

## 1. Latar Belakang & Temuan

| # | Temuan | Dampak |
|---|--------|--------|
| T1 | Form "Tambah Pengguna" hanya membuat `users` + `user_profiles` + role | User guru baru tidak muncul di Data Guru; tidak bisa dipakai di jadwal (`schedules.teacher_id` → `teachers.id`) |
| T2 | Query halaman siswa tidak difilter per guru | Guru melihat SEMUA siswa satu sekolah |
| T3 | Dashboard web tidak menerima data (`stats` tidak dikirim dari `PageController@dashboard`) | Semua role melihat dashboard admin dengan angka hardcoded (dummy) |
| T4 | `DashboardController` API memakai `roles->first()` | User multi-role dapat dashboard salah |
| T5 | `DashboardController` mencocokkan role `wali_murid` yang tidak ada (seed: `orang_tua`); `wali_kelas` tidak ditangani | Orang tua & wali kelas selalu jatuh ke dashboard basic |
| T6 | Belum ada mekanisme orang tua memilih anaknya saat akun dibuat | Scope data orang tua (R4) tidak bisa dievaluasi |

## 2. Skema Database Pendukung (sudah tersedia, tidak perlu migrasi besar)

```
users ──1:1── user_profiles
users ──1:1── teachers        (guru; dipakai schedules.teacher_id)
users ──1:1── staff           (pegawai non-guru; department, position)
users ──1:1── students        (siswa)
users ──1:N── student_guardians ──N:1── students   ← koneksi orang tua ↔ anak
classrooms.homeroom_teacher_id → teachers.id       ← wali kelas
schedules (teacher_id, classroom_id, ...)          ← guru mengajar banyak kelas
student_enrollments (student_id, classroom_id, status, academic_year_id)
```

Kolom penting `student_guardians`: `student_id`, `user_id` (nullable — data wali
bisa ada tanpa akun), `relationship` (ayah/ibu/wali), `nik`, `phone`,
`is_primary_contact`.

Satu-satunya penambahan skema yang diusulkan: tabel `teacher_classrooms`
(penugasan manual guru ↔ kelas, lihat R3).

---

## 3. Rumusan Aturan (R1–R8)

### R1 — Identitas berlapis, satu kali input
Satu orang = satu `users` + satu `user_profiles`. Data domain hidup di record
tertaut (`teachers`/`staff`/`students`/`student_guardians`) dan dibuat dalam
**satu transaksi** dari form pengguna melalui **seksi form dinamis** berdasarkan
role yang dipilih:

| Role dipilih | Record tertaut | Field ekstra pada form |
|---|---|---|
| guru, wali_kelas | `teachers` (+ `teacher_subjects`) | NIP, status kepegawaian, tgl masuk, mapel |
| kepala_sekolah, wakil, tata_usaha, bendahara, pustakawan | `staff` | No. pegawai, departemen, jabatan |
| orang_tua | `student_guardians` (satu baris per anak) | **pemilih siswa multi-pilih** + hubungan (lihat R8) |
| siswa | `students` + `student_enrollments` | tetap lewat menu Siswa (butuh NIS, kelas) |
| admin, super_admin | — | — |

Aturan tambahan:
- Centangan record tertaut **default mengikuti role tapi bisa di-override**
  (kasus rangkap: kepala sekolah yang juga mengajar → `staff` + `teachers`).
- Untuk user lama tanpa record tertaut: aksi **"Lengkapi Data"** (bukan buat duplikat).
- `teachers.user_id`, `staff.user_id`, `students.user_id` unik — satu user maksimal
  satu record per jenis.

### R2 — Tiga lapis akses, tidak boleh dicampur
1. **Permission** (Spatie, sudah ada): boleh/tidak membuka fitur.
2. **Data scope** (baru): baris mana yang terlihat — difilter **di query backend**,
   satu implementasi dipakai bersama oleh halaman siswa, absensi, nilai, dashboard.
3. **Presentasi**: menu & dashboard menyesuaikan role — kosmetik, bukan penjaga utama.

### R3 — Rumus "kelas yang diampu" (sumber tunggal kebenaran)
```
Kelas(G) = kelas_perwalian(G)              -- classrooms.homeroom_teacher_id
         ∪ kelas_dari_jadwal_aktif(G)      -- schedules aktif, TA & semester aktif
         ∪ kelas_penugasan_manual(G)       -- teacher_classrooms (tabel baru)
```
- `teacher_classrooms` diisi admin; menjadi jalan cepat bila modul jadwal belum
  rutin dipakai. Kolom: `id, tenant_id, teacher_id, classroom_id, academic_year_id,
  created_at, updated_at` + unik `(teacher_id, classroom_id, academic_year_id)`.
- Selalu dievaluasi pada **tahun ajaran aktif** — jadwal semester lalu tidak
  memberi akses semester ini.

### R4 — Siswa yang terlihat (turunan R3)
| Role | Siswa yang terlihat |
|---|---|
| super_admin, admin, kepala_sekolah, wakil, tata_usaha | Semua (satu tenant) |
| guru / wali_kelas | Enrollment aktif di `Kelas(G)`; wali kelas + hak edit untuk kelas perwaliannya |
| siswa | Dirinya sendiri |
| orang_tua | Anak-anaknya via `student_guardians.user_id` |
| bendahara, pustakawan | Semua (satu tenant), terbatas modulnya |

### R5 — Dashboard ditentukan role tertinggi
Urutan prioritas eksplisit (periksa SEMUA role user, bukan `roles->first()`):
```
super_admin > admin > kepala_sekolah > wakil_kepala_sekolah > bendahara
> wali_kelas > guru > tata_usaha > pustakawan > siswa > orang_tua
```
Paket widget per role:

| Role | Widget dashboard | Scope |
|---|---|---|
| super_admin / admin | Statistik sekolah, absensi hari ini, ringkasan keuangan | Tenant |
| kepala_sekolah / wakil | Seperti admin tanpa rincian keuangan; tren absensi | Tenant |
| bendahara | Tagihan, pembayaran masuk, tunggakan | Tenant |
| guru | **Dropdown kelas diampu** → jumlah siswa, absensi hari ini kelas terpilih, jadwal berikutnya | `Kelas(G)` |
| wali_kelas | Seperti guru; default = kelas perwalian + widget siswa bermasalah absensi | `Kelas(G)` |
| siswa | Kehadiran diri (persentase, riwayat), jadwal hari ini, tagihan pribadi | Diri sendiri |
| orang_tua | Seperti siswa per anak; **pemilih anak** bila lebih dari satu | Anak-anaknya |
| tata_usaha / pustakawan | Ringkasan operasional modulnya | Tenant, tanpa keuangan/nilai |

### R6 — Tanpa data tertaut, tampil jujur
Guru tanpa record `teachers` / tanpa kelas → dashboard kosong dengan pesan
"Belum ada kelas yang ditugaskan". Orang tua tanpa anak tertaut → "Belum ada
siswa yang terhubung". **Hapus semua fallback angka dummy** di `Dashboard.tsx`.

### R7 — Detail selalu dijaga policy
Akses langsung URL/API detail milik pihak lain → **403**, meski tidak muncul di
daftar. Berlaku untuk siswa, absensi, nilai, pembayaran.

### R8 — Koneksi orang tua ↔ anak (jawaban pertanyaan terakhir)
Mekanisme sistem: baris `student_guardians` dengan `user_id` terisi = akun orang
tua itu "memiliki" siswa tersebut.

**Alur di form pengguna** (role = orang_tua):
1. Muncul seksi **"Siswa yang Diampu"**: pencarian siswa (nama/NIS) multi-pilih,
   per siswa pilih `relationship` (ayah/ibu/wali) dan `is_primary_contact`.
2. Saat simpan, per siswa terpilih:
   - **Jika siswa sudah punya baris wali dengan `user_id` NULL** yang cocok
     (dicek via NIK atau nomor HP yang sama dengan profil user baru) →
     **tautkan** (isi `user_id`) — jangan buat baris baru. Ini mencegah duplikat
     karena data wali biasanya sudah diinput saat pendaftaran siswa.
   - Jika tidak ada yang cocok → buat baris `student_guardians` baru
     (`user_id`, `student_id`, `relationship`, nama/HP diambil dari profil).
3. Di halaman edit pengguna, daftar anak bisa ditambah/dilepas (lepas = set
   `user_id` NULL, **bukan** hapus baris — data wali milik siswa tetap utuh).

**Aturan:**
- Satu akun orang tua ↔ banyak siswa (multi-pilih). Satu siswa ↔ banyak wali.
- Penautan hanya boleh dilakukan admin/super admin (bukan self-service) —
  mencegah orang tua menautkan diri ke siswa orang lain.
- Scope R4 orang tua membaca: `students.id IN (SELECT student_id FROM
  student_guardians WHERE user_id = ?)`.

---

## 4. Tahapan Eksekusi

> Setiap fase selesai = diuji end-to-end dulu (kriteria di §5) sebelum lanjut.

### Fase 1 — Fondasi data scope (R3 + R4)
**File:** migrasi `teacher_classrooms`; scope/trait baru (mis.
`app/Support/Scopes/VisibleToUser.php` atau method `scopeVisibleTo` di model
`Student`); terapkan di `PageController@students`, API `StudentController@index`,
API absensi & nilai; `StudentPolicy` untuk detail (R7).
**Hasil:** guru login hanya melihat siswa kelas diampu; siswa/ortu hanya dirinya/anaknya.

### Fase 2 — Perbaikan wiring & bug dashboard (T3, T4, T5)
**File:** `DashboardController` (prioritas role R5, perbaiki `wali_murid` →
`orang_tua`, tambah `wali_kelas`); `PageController@dashboard` kirim stats nyata;
`Dashboard.tsx` hapus fallback dummy (R6).
**Hasil:** setiap role mendapat data nyata sesuai paketnya; belum ada UI khusus per role.

### Fase 3 — Dashboard per role (R5 + R6)
**File:** `Dashboard.tsx` dipecah per varian (admin/guru/siswa/ortu — komponen
terpisah, satu entry); dropdown kelas diampu (guru), pemilih anak (ortu);
endpoint stats per kelas terpilih.
**Hasil:** dashboard sesuai matriks §R5, dropdown guru hanya berisi `Kelas(G)`.

### Fase 4 — Form pengguna dinamis (R1 + R8)
**File:** `settings/Users.tsx` (seksi dinamis per role: kepegawaian guru, staf,
pemilih siswa untuk ortu); `Admin\UserController` (transaksi buat/taut record
tertaut; endpoint pencarian siswa untuk pemilih); aksi "Lengkapi Data" untuk
user lama.
**Hasil:** input satu kali — akun + data domain + penautan anak langsung jadi.

### Urutan & alasan
Fase 1 lebih dulu karena semua fase lain bergantung padanya (dashboard guru dan
pemilih anak memakai scope yang sama). Fase 2 murah dan menghapus data bohong.
Fase 3–4 menyusul di atas fondasi yang sudah teruji.

---

## 5. Kriteria Uji per Fase (ringkas)

| Fase | Uji |
|---|---|
| 1 | Login `guru1` → daftar siswa hanya kelas diampu; akses URL siswa kelas lain → 403; `siswa1` → hanya dirinya; `admin` → semua |
| 2 | Login tiap role → angka dashboard nyata (cocokkan dengan query manual); tidak ada fallback dummy; ortu & wali kelas tidak lagi jatuh ke basic |
| 3 | Dropdown guru hanya `Kelas(G)`; ganti kelas → widget berubah; guru tanpa kelas → pesan kosong R6 |
| 4 | Tambah user guru → muncul di Data Guru & bisa dipakai jadwal; tambah user ortu + pilih 2 anak → login ortu melihat 2 anak; penautan memakai baris wali lama bila NIK cocok (tidak ada duplikat) |

---

## 6. Keputusan yang Sudah Diambil / Masih Terbuka

**Sudah:** `teacher_classrooms` dipakai sebagai sumber penugasan manual (R3);
penautan anak hanya oleh admin (R8); lepas tautan = NULL-kan `user_id`, bukan hapus.

**Masih terbuka (perlu konfirmasi sebelum fase terkait):**
1. Apakah kepala sekolah boleh melihat ringkasan keuangan penuh atau hanya total? (Fase 2)
2. Siswa dibuat dari form pengguna juga (seksi dinamis) atau tetap eksklusif lewat menu Siswa? (Fase 4)
3. Batas maksimal anak per akun orang tua perlu dibatasi atau bebas? (Fase 4)
