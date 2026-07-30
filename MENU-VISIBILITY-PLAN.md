# Analisa: Pengaturan Visibilitas Menu per-Role

> Status: **ANALISA (belum ada eksekusi kode).** Prasyarat sebelum finalisasi
> "Pengaturan Absensi" (lihat `ATTENDANCE-SETTINGS-PLAN.md`), karena menu absen
> nantinya juga akan tunduk pada mekanisme ini. Path relatif dari root `sms-app/`.

---

## 0. Ringkasan

**Permintaan:** buat halaman **"Pengaturan Menu"** di mana admin bisa mengatur
**menu mana muncul untuk role mana**, dengan **nilai default = kondisi yang
berlaku sekarang**.

**Temuan inti:** saat ini visibilitas menu **tidak dikonfigurasi lewat data** —
melainkan **hardcoded** dari kombinasi:
1. Array `menuItems` di `MainLayout.tsx` (tiap menu punya `permission`), dan
2. Matriks permission→role di `RoleSeeder.php`.

Untuk mengubah "role X boleh lihat menu Y", saat ini harus **edit kode +
re-seed + deploy**. Fitur yang diminta = memindahkan keputusan ini ke **data
yang bisa diubah admin dari UI**.

---

## 1. Cara Kerja Visibilitas Menu Sekarang

### 1.1 Dua lapis yang berbeda (WAJIB dibedakan)
| Lapis | Lokasi | Fungsi |
|---|---|---|
| **A. Permission (Spatie)** | `RoleSeeder.php` + middleware route | **Keamanan nyata** — mengontrol akses API. Tanpa permission → 403. |
| **B. Visibilitas menu (sidebar)** | `menuItems` + `hasPermission()` di `MainLayout.tsx:304-308` | **Kosmetik** — hanya menyembunyikan/menampilkan link di sidebar. |

Saat ini lapis B **diturunkan dari** lapis A: menu muncul kalau
`auth.user.permissions` (dishare via Inertia, `HandleInertiaRequests.php:45`)
memuat permission menu tsb. `super_admin` bypass semua
(`MainLayout.tsx:306`).

> **Implikasi desain terpenting:** fitur "pengaturan menu" beroperasi di **lapis
> B saja**. Menyembunyikan menu **tidak** mencabut akses API, dan menampilkan
> menu yang permission-nya tak dimiliki role hanya akan berujung 403/halaman
> kosong saat diklik. Maka aturan mainnya harus jelas (lihat §4).

### 1.2 Inventaris menu lengkap (10 group)
| # | Group | Permission group | Anak (permission anak) |
|---|---|---|---|
| 1 | Dashboard | `dashboard.view` | — (tanpa anak) |
| 2 | Akademik | `academic.view` | Tahun Ajaran(`academic-years.view`), Kurikulum(`curricula.view`), Mata Pelajaran(`subjects.view`), Tingkat Kelas(`grade-levels.view`), Jurusan(`majors.view`), Kelas(`classrooms.view`), Jadwal(`schedules.view`) |
| 3 | Siswa | `students.view` | Data Siswa(—), Pendaftaran(`students.enroll`), Prestasi(—) |
| 4 | Guru & Staff | `teachers.view` | Data Guru(`teachers.view`), Data Staff(`staff.view`), Pengajuan Cuti(—) |
| 5 | Absensi | `attendance.view` | Absensi Siswa(—), Absensi Pegawai(—), Rekap Absensi(—) |
| 6 | Nilai & Ujian | `grades.view` | Ujian(`exams.view`), Input Nilai(`grades.input`), Rekap Nilai(—) |
| 7 | Keuangan | `finance.view` | Tagihan(—), Pembayaran(—), Laporan(`finance.report`) |
| 8 | Perpustakaan | `library.view` | Katalog Buku(—), Peminjaman(—), Anggota(—) |
| 9 | Laporan | `reports.view` | Rapor(—), Generate Laporan(—) |
| 10 | Pengaturan | `settings.view` | Umum(`settings.view`), Pengguna(`superAdminOnly`) |

Catatan: anak tanpa permission (—) mewarisi visibilitas dari **group induknya**
(karena `hasPermission(undefined)` = true).

### 1.3 Temuan sampingan
- Menu **"Umum" (`/settings`)** menunjuk `Api\V1\Setting\SettingController`
  yang **filenya tidak ada** (folder `Api/V1/Setting/` kosong; tak ada tabel
  `settings` generik atau model `Setting`). Jadi menu ini praktis **stub/rusak**
  — relevan karena fitur pengaturan menu sebaiknya **tidak** menumpang di sini.

---

## 2. Default = Kondisi Sekarang (snapshot role → group terlihat)

Diturunkan dari `RoleSeeder.php` × permission tiap group. Inilah "default" yang
harus direproduksi fitur baru:

| Role | Group yang terlihat sekarang (default) |
|---|---|
| **super_admin** | Semua 10 group |
| **admin** | Semua 10 group |
| **kepala_sekolah** | Dashboard, Akademik, Siswa, Guru&Staff, Absensi, Nilai&Ujian, Keuangan, Laporan, Pengaturan *(tanpa Perpustakaan)* |
| **wakil_kepala_sekolah** | Dashboard, Akademik, Siswa, Guru&Staff, Absensi, Nilai&Ujian, Laporan |
| **guru** | Dashboard, Akademik, Siswa, Absensi, Nilai&Ujian, Perpustakaan |
| **wali_kelas** | Dashboard, Akademik, Siswa, Absensi, Nilai&Ujian, Laporan |
| **tata_usaha** | Dashboard, Siswa, Guru&Staff, Absensi, Laporan |
| **bendahara** | Dashboard, Siswa, Keuangan, Laporan |
| **pustakawan** | Dashboard, Siswa, Perpustakaan |
| **siswa** | Dashboard, Perpustakaan |
| **orang_tua** | Dashboard |

> Default menu-config bisa **di-generate otomatis**: untuk tiap role, evaluasi
> permission yang dimilikinya terhadap gate tiap menu → hasilkan baris
> `(role, menu, visible=true/false)`. Dengan begitu default persis = perilaku
> hari ini, lalu admin tinggal menyesuaikan.

---

## 3. Yang Perlu Dibangun (garis besar)

1. **Identitas menu stabil (`menu_key`)** — prasyarat. Menu sekarang hanya punya
   `title`/`href`, tak ada ID stabil. Perlu tambah `key` unik per group & anak
   (mis. `attendance`, `attendance.settings`) agar bisa direferensikan config.
   *Sumber kebenaran menu sebaiknya dipindah/diduplikasi ke backend* supaya UI
   pengaturan dan sidebar membaca definisi yang sama.
2. **Penyimpanan config** — tabel baru **per-tenant**, mis.
   `role_menu_settings(tenant_id, role, menu_key, visible)` atau satu baris JSON
   per tenant `{ role: { menu_key: bool } }`. Pola per-tenant mengikuti
   `attendance_settings`.
3. **Seeder default** — isi dari snapshot §2 (di-generate dari permission).
4. **API** — `GET/PUT /api/v1/settings/menu` (di-guard `permission:settings.manage`
   atau khusus admin/super_admin).
5. **Resolusi ke frontend** — `HandleInertiaRequests.php` share daftar
   `menu_key` yang boleh tampil untuk user (hasil gabungan role-nya), lalu
   `MainLayout.tsx` memfilter `menuItems` berdasarkan itu — **digabung** dengan
   cek permission lama (lihat §4).
6. **Halaman UI** — matriks *role × menu* dengan switch; tombol "Reset ke
   Default".

---

## 4. Keputusan Desain Kritis (perlu jawaban sebelum implementasi)

### 4.1 Semantik: "sembunyikan" saja, atau juga "beri akses"?
- **Opsi A (disarankan) — UX-only, hanya bisa MENYEMBUNYIKAN dalam batas
  permission.** Config hanya boleh menyembunyikan menu yang secara permission
  sebenarnya boleh dilihat role itu. Tak bisa menampilkan menu yang permission-
  nya tak dimiliki (menghindari klik→403). Keamanan tetap 100% di lapis
  permission. Aman & sederhana.
- **Opsi B — Config jadi sumber kebenaran visibilitas penuh** (menggantikan gate
  permission untuk sidebar). Lebih fleksibel tapi berisiko: menu bisa tampil
  padahal API menolak → membingungkan. Perlu sinkronisasi ketat.

### 4.2 Interaksi dengan permission (bila Opsi A)
Menu tampil **jika** `hasPermission(menu)` **DAN** `config.visible(role, menu)`.
Artinya config hanya mempersempit, tak pernah memperluas.

### 4.3 User multi-role
Spatie mengizinkan >1 role. Semantik gabungan: **union** — menu tampil bila
**salah satu** role user mengizinkannya (konsisten dgn cara permission bekerja).

### 4.4 Proteksi lockout (WAJIB)
- `super_admin` **tak boleh** bisa disembunyikan menunya (selalu lihat semua).
- Menu **"Pengaturan Menu" itu sendiri** tak boleh disembunyikan dari role admin
  yang sedang mengaturnya → jika tidak, admin bisa mengunci diri sendiri.
- Sediakan tombol **"Reset ke Default"** sebagai jalan keluar.

### 4.5 Level granularitas
- Cukup **level group** saja, atau sampai **level anak**? Anak lebih presisi
  tapi UI lebih rumit. Rekomendasi: dukung dua-duanya via `menu_key` (group &
  anak sama-sama punya key), UI default menampilkan group dan bisa di-expand.

### 4.6 Cakupan config: per-tenant vs global
Per-tenant (tiap sekolah atur sendiri) konsisten dengan modul lain. Default
di-seed saat tenant dibuat.

---

## 5. Hubungan dengan "Pengaturan Absensi"

- Menu **"Pengaturan Absensi"** (Rencana A di `ATTENDANCE-SETTINGS-PLAN.md`)
  akan punya `menu_key = settings.attendance` dan otomatis ikut governable oleh
  fitur ini.
- Namun **penegakan keamanan tetap** di `permission:settings.attendance`
  (server-side, GAP-1). Fitur menu-config **tidak** menggantikan itu — hanya
  lapisan UX di atasnya.
- Urutan disarankan: **kunci lapis permission dulu** (GAP-1 + §2.3 seeder di plan
  absen) → baru bangun menu-config sebagai kenyamanan admin.

---

## 6. Estimasi Dampak & Risiko
- **Refactor menu_key** menyentuh `MainLayout.tsx` (sumber menu) — perlu hati-hati
  agar tak memecah sidebar yang sudah jalan.
- **Migrasi + seeder** aman (tabel baru, tak mengubah data lama).
- **Risiko utama:** kebingungan dua lapis (permission vs visibilitas). Mitigasi:
  pilih **Opsi A** (config hanya mempersempit).

---

## 7. Pertanyaan Menunggu Jawaban User
- [ ] Semantik **Opsi A** (sembunyikan-saja, aman) atau **Opsi B** (config penuh)? (§4.1)
- [ ] Granularitas **group saja** atau **sampai anak menu**? (§4.5)
- [ ] Perlu dibangun **sekarang** (sebelum menu absen) atau **cukup dianalisa
      dulu**, dan menu absen jalan lewat permission biasa (Rencana A) dahulu?
