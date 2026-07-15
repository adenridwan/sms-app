# Spesifikasi Aplikasi: Absensi Sekolah

Dokumen ini mengekstrak **logika bisnis, skema database, dan fitur** dari implementasi saat ini (Laravel 12 + Filament 3 + Livewire), sebagai referensi independen-teknologi untuk rebuild dengan stack lain. Ini bukan dokumentasi cara pakai Filament — semua yang tertulis di sini adalah *apa* yang harus dilakukan sistem, bukan *bagaimana* Filament melakukannya.

## Daftar Isi

1. [Ringkasan Sistem](#1-ringkasan-sistem)
2. [Peran & Hak Akses](#2-peran--hak-akses)
3. [Skema Database](#3-skema-database)
4. [Logika Bisnis Inti](#4-logika-bisnis-inti)
5. [Fitur per Modul](#5-fitur-per-modul)
6. [Kontrak API (Scanner PWA & Portal Publik)](#6-kontrak-api-scanner-pwa--portal-publik)
7. [Data Seed / Default](#7-data-seed--default)
8. [Catatan Penting untuk Rebuild](#8-catatan-penting-untuk-rebuild)

---

## 1. Ringkasan Sistem

Sistem absensi sekolah berbasis scan QR Code / kartu RFID, dengan:
- **Panel Admin** — kelola data master (guru, siswa, kelas, jurusan), edit kehadiran, approve izin, generate QR, laporan PDF, backup/restore, pengaturan umum, log aktivitas.
- **Panel Guru (Wali Kelas)** — versi terbatas dari panel admin, hanya untuk kelas yang diampu sendiri.
- **Scanner Kiosk (PWA, offline-first)** — perangkat scan QR/RFID untuk check-in/check-out, bisa bekerja tanpa koneksi internet (antrian lokal, sync otomatis saat online kembali).
- **Portal publik** — pengajuan izin/sakit (dengan foto bukti) dan cek riwayat kehadiran (oleh siswa/orang tua, tanpa login).
- **Notifikasi WhatsApp** (opsional) saat check-in/check-out.

Bahasa/istilah database sengaja dalam Bahasa Indonesia (nama tabel/kolom) — ini bukan typo, melainkan skema asli yang dipertahankan agar kompatibel dengan data lama.

---

## 2. Peran & Hak Akses

5 role (via permission-based, bukan hardcode role check di kebanyakan tempat):

| Role | Permission yang dimiliki |
|---|---|
| `superadmin` | **Semua** permission di bawah |
| `admin` | `dashboard.view-admin`, `admin.access`, `attendance.edit`, `attendance.view`, `qr.generate`, `audit.view` |
| `kepsek` (kepala sekolah) | `dashboard.view-admin`, `admin.access`, `attendance.view`, `audit.view` |
| `scanner` | `admin.access`, `attendance.view` |
| `guru` | `teacher.access`, `attendance.edit`, `attendance.view` |

Daftar permission lengkap: `dashboard.view-admin`, `admin.access`, `students.manage`, `teachers.manage`, `classes.manage`, `attendance.edit`, `attendance.view`, `qr.generate`, `petugas.manage` (staff/user), `settings.manage`, `backup.manage`, `teacher.access`, `audit.view`.

**Catatan penting:**
- `students.manage`/`teachers.manage`/`classes.manage`/`settings.manage`/`backup.manage` **tidak diberikan ke role manapun kecuali superadmin** di seeder default — ini berarti hanya superadmin yang bisa CRUD data master siswa/guru/kelas/jurusan/pengaturan/backup di instalasi default. `admin`/`kepsek`/`scanner` hanya bisa mengedit/melihat kehadiran.
- Akses ke panel Admin vs Teacher ditentukan oleh permission `admin.access` vs `teacher.access`, bukan nama role langsung (lihat `User::canAccessPanel()`).
- Halaman "Data Petugas" (kelola akun staff) di panel admin sebenarnya digate oleh **role check langsung** (`hasRole('superadmin')`), bukan permission `petugas.manage` — inkonsistensi yang ada di kode saat ini, perlu diputuskan saat rebuild apakah ingin diseragamkan.
- Guru (wali kelas) di panel Teacher **hanya melihat/mengedit kelas yang dia jadi wali kelasnya** — ini di-resolve dari `users.id_guru` → `tb_guru.id_guru` → `tb_kelas.id_wali_kelas`, **tidak pernah dari input client**. Setiap aksi tulis di panel guru re-verifikasi kepemilikan kelas di server (defense-in-depth terhadap IDOR).

---

## 3. Skema Database

Semua tabel utama pakai prefix `tb_` (warisan dari aplikasi lama), kecuali tabel framework (`users`, `general_settings`, dsb).

### `tb_jurusan` (Jurusan/Program Studi)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `jurusan` | string(32), unique | |
| `created_at`, `updated_at`, `deleted_at` | timestamps | **soft delete** |

### `tb_kelas` (Kelas)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_kelas` | PK | |
| `tingkat` | string(10) | "X", "XI", "XII" |
| `id_jurusan` | FK → `tb_jurusan.id`, cascade on update | |
| `index_kelas` | string(5) | "A", "B", dst |
| `id_wali_kelas` | FK → `tb_guru.id_guru`, nullable, **restrict on delete** | guru tidak bisa dihapus jika masih jadi wali kelas |
| `created_at`, `updated_at`, `deleted_at` | timestamps | **soft delete** |

Label tampilan kelas selalu format: `"{tingkat} {nama_jurusan} {index_kelas}"` (mis. "X RPL A") — dipakai konsisten di semua dropdown/kolom kelas di seluruh aplikasi.

### `tb_guru` (Guru)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_guru` | PK | |
| `nuptk` | string(24) | nomor identitas guru |
| `nama_guru` | string(255) | |
| `jenis_kelamin` | enum: Laki-laki, Perempuan | |
| `alamat` | text | |
| `no_hp` | string(32) | dipakai untuk notifikasi WA |
| `unique_code` | string(64), unique | payload QR code, **auto-generated** saat create (lihat §4.5) |
| `rfid_code` | string(100), nullable, indexed | kode kartu RFID, **harus unik lintas tabel guru+siswa** (lihat §4.6) |
| `created_at`, `updated_at` | timestamps | (tidak ada soft delete) |

### `tb_siswa` (Siswa)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_siswa` | PK | |
| `nis` | string(16) | nomor induk siswa |
| `nama_siswa` | string(255) | |
| `id_kelas` | FK → `tb_kelas.id_kelas`, **cascade on delete** | siswa ikut terhapus jika kelasnya dihapus |
| `jenis_kelamin` | enum: Laki-laki, Perempuan | |
| `no_hp` | string(32) | notifikasi WA (ke ortu biasanya) |
| `poin_pelanggaran` | integer, default 0 | akumulasi menit keterlambatan (lihat §4.2) |
| `unique_code` | string(64), unique | payload QR, auto-generated |
| `rfid_code` | string(100), nullable, indexed | unik lintas guru+siswa |
| `created_at`, `updated_at` | timestamps | |

### `tb_kehadiran` (Status Kehadiran — lookup table statis)
| Kolom | Tipe |
|---|---|
| `id_kehadiran` | PK |
| `kehadiran` | enum: Hadir, Sakit, Izin, Tanpa keterangan |

4 baris tetap, id **hardcoded** dalam kode (`Kehadiran::HADIR=1, SAKIT=2, IZIN=3, TANPA_KETERANGAN=4`). "Tanpa keterangan" bermakna ganda tergantung waktu — lihat §4.1.

### `tb_presensi_guru` (Presensi Harian Guru)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_presensi` | PK | |
| `id_guru` | FK → `tb_guru`, nullable, cascade on delete | |
| `tanggal` | date | |
| `jam_masuk`, `jam_keluar` | time, nullable | |
| `id_kehadiran` | FK → `tb_kehadiran`, cascade on delete | |
| `keterangan` | string(255) | |
| **UNIQUE** (`id_guru`, `tanggal`) | | satu guru hanya satu baris presensi per hari |

### `tb_presensi_siswa` (Presensi Harian Siswa)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_presensi` | PK | |
| `id_siswa` | FK → `tb_siswa`, cascade on update+delete | |
| `id_kelas` | FK → `tb_kelas`, nullable, cascade on delete | snapshot kelas siswa **saat presensi dibuat** (bisa beda dari kelas siswa saat ini jika siswa pindah kelas) |
| `tanggal` | date | |
| `jam_masuk`, `jam_keluar` | time, nullable | |
| `id_kehadiran` | FK → `tb_kehadiran`, cascade on delete | |
| `menit_keterlambatan` | integer, default 0 | dihitung saat check-in (lihat §4.2) |
| `keterangan` | string(255) | |
| **UNIQUE** (`id_siswa`, `tanggal`) | | satu siswa hanya satu baris presensi per hari |

### `tb_hari_libur` (Hari Libur/Non-Kerja)
| Kolom | Tipe |
|---|---|
| `id` | PK |
| `tanggal` | date, unique |
| `keterangan` | string(255) |

Ini adalah **satu-satunya** sumber kebenaran untuk "hari ini libur atau tidak" pada gating scan (lihat §4.7) — bukan `general_settings.hari_kerja`.

### `tb_perizinan` (Pengajuan Izin/Sakit)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_perizinan` | PK | |
| `id_siswa` | FK → `tb_siswa`, nullable, cascade | exactly satu dari `id_siswa`/`id_guru` terisi |
| `id_guru` | FK → `tb_guru`, nullable, cascade | |
| `tanggal_mulai`, `tanggal_selesai` | date | rentang izin (bisa multi-hari) |
| `tipe_izin` | enum: Sakit, Izin | default Sakit |
| `alasan` | text, nullable | |
| `bukti` | string(255), nullable | path file foto bukti (disk `public`, folder `perizinan`) |
| `status` | enum: Pending, Disetujui, Ditolak | default Pending |
| `id_petugas` | FK → `users`, nullable, **null on delete** | siapa yang approve/reject |
| `created_at`, `updated_at` | timestamps | |

### `tb_audit_logs` (Log Aktivitas)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `id_user` | FK → `users`, nullable, null on delete | |
| `aksi` | string(255) | deskripsi human-readable aksi |
| `tabel` | string(100) | nama tabel yang terdampak |
| `id_record` | integer, nullable | |
| `data_lama`, `data_baru` | text (JSON), nullable | snapshot before/after |
| `ip_address` | string(45) | |
| `created_at` | datetime, nullable | **tidak ada `updated_at`** — log immutable |

Tidak ada observer/hook otomatis — setiap penulisan log adalah **panggilan eksplisit** dari service terkait (lihat §4.8).

### `general_settings` (Pengaturan Umum — single row, id selalu 1)
| Kolom | Tipe | Default |
|---|---|---|
| `id` | PK | |
| `logo` | string(225), nullable | |
| `school_name` | string(225), nullable | "SMK 1 Indonesia" |
| `school_year` | string(225), nullable | "2024/2025" |
| `jam_masuk_limit` | time, nullable | "07:00:00" — batas jam masuk sebelum dianggap terlambat |
| `jam_pulang_standard` | time, nullable | "14:00:00" — jam pulang standar, dipakai utk tentukan "Alfa" vs "Belum Scan" |
| `hari_kerja` | string(30), nullable | "1,2,3,4,5" — CSV hari kerja ISO weekday (1=Senin..7=Minggu). **Lihat §8 catatan penting** — kolom ini TIDAK dipakai untuk gating scan. |
| `copyright` | string(225), nullable | |

### `users` (Akun Login)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `name` | string | dipakai sebagai username |
| `email` | string, unique | |
| `password` | string, hashed | |
| `id_guru` | FK → `tb_guru.id_guru`, nullable, restrict on delete | menghubungkan akun login ke record guru (untuk role `guru`) |
| roles/permissions | via tabel Spatie (`model_has_roles`, `model_has_permissions`, `roles`, `permissions`) | |

Role tersedia: `superadmin`, `admin`, `kepsek`, `scanner`, `guru` — satu user bisa punya lebih dari satu role (mis. superadmin biasanya juga diberi role `admin`).

### Tabel pendukung framework
`personal_access_tokens` (Sanctum, untuk auth API scanner), `sessions`, `cache`, `jobs`, `imports`/`exports`/`failed_import_rows` (import CSV via Filament — bisa diabaikan jika rebuild tidak pakai fitur import serupa).

---

## 4. Logika Bisnis Inti

### 4.1 Status Kehadiran: "Belum Scan" vs "Alfa"

Aturan paling penting dan paling mudah salah diimplementasikan ulang:

> Seseorang (siswa/guru) yang **tidak punya baris presensi** (Hadir/Sakit/Izin) untuk tanggal tertentu berstatus **"Belum Scan"** (status sementara/pending) **sampai** `jam_pulang_standard` terlewati pada tanggal itu (atau tanggalnya sudah di masa lalu) — setelah itu statusnya berubah jadi **"Alfa"** (final, dianggap tidak hadir tanpa keterangan).

Pseudocode:
```
isAfterSchool(tanggal, jam_pulang_standard):
    hari_ini = today()
    jika hari_ini > tanggal: return true
    jika hari_ini == tanggal DAN waktu_sekarang > jam_pulang_standard: return true
    return false

resolveStatus(orang, tanggal):
    presensi = cari baris tb_presensi_* untuk (orang, tanggal)
    jika presensi ada: return presensi.kehadiran  (Hadir/Sakit/Izin/Tanpa keterangan)
    jika isAfterSchool(tanggal): return "Alfa"
    else: return "Belum Scan"
```

Ini dipakai di: widget dashboard (hitung jumlah per status), tampilan tabel presensi harian, deteksi ketidakhadiran beruntun.

**Ketidakhadiran beruntun** ("Absentee Alerts"): siswa yang tidak punya satupun baris Hadir/Sakit/Izin di **N tanggal terakhir yang benar-benar ada di tabel presensi** (bukan N hari kalender berturut — jika tabel presensi kosong di suatu tanggal karena hari libur, tanggal itu tidak dihitung). Siswa yang belum pernah presensi sama sekali (baru diimpor) **dikecualikan** dari daftar ini. Default N=3 hari.

### 4.2 Check-in (Scan Masuk)

1. Cek dulu: apakah tanggal ini hari libur (`tb_hari_libur`)? Jika ya, **tolak scan** dengan pesan "sistem presensi dinonaktifkan karena: {alasan}".
2. Cari orang berdasarkan kode (unique_code ATAU rfid_code, dicoba di tb_siswa dulu, baru tb_guru).
3. Jika sudah ada baris presensi untuk (orang, tanggal ini) → tolak, "Anda sudah absen hari ini".
4. **Untuk siswa saja**: hitung `menit_keterlambatan` = selisih menit antara waktu scan dan `jam_masuk_limit`, dibulatkan ke atas jika lebih besar dari limit (0 jika tidak terlambat atau `jam_masuk_limit` tidak diset). Jika terlambat > 0:
   - `keterangan` diisi otomatis: `"Terlambat {N} menit"`.
   - `siswa.poin_pelanggaran` **ditambah** (increment) sebesar N menit — ini adalah **akumulasi permanen**, tidak pernah dikurangi otomatis.
5. Simpan baris presensi baru dengan `id_kehadiran = Hadir`, `jam_masuk = waktu scan`.
6. Kirim notifikasi WhatsApp (best-effort, gagal tidak membatalkan scan) — lihat §4.9.

### 4.3 Check-out (Scan Pulang)

1. Cari baris presensi (orang, tanggal ini). Jika tidak ada → tolak, "Anda belum absen hari ini".
2. Update `jam_keluar` = waktu scan, reset `keterangan` jadi kosong.
3. Kirim notifikasi WhatsApp.

**Catatan penting untuk scan offline**: waktu yang dipakai untuk semua logika di atas adalah **`scanned_at`** yang dikirim client (waktu scan sebenarnya terjadi di device, disimpan dulu di penyimpanan lokal), **bukan** waktu request diterima server — supaya antrian scan offline yang baru ter-sync belakangan tetap tercatat dengan waktu yang benar, bukan waktu sync-nya.

### 4.4 Edit Kehadiran Manual (oleh Admin/Guru)

Upsert satu baris presensi untuk (orang, tanggal):
- Field `jam_masuk`/`jam_keluar` **hanya diupdate jika dikirim** (tidak null) — kalau dikosongkan di form, nilai lama dipertahankan (bukan ditimpa null).
- `keterangan` — jika tidak dikirim, pakai keterangan lama (fallback ke string kosong jika belum ada baris sama sekali).
- **Setiap edit menulis 1 baris audit log** (snapshot data lama vs baru).
- Guru (wali kelas) hanya boleh mengedit siswa di kelasnya sendiri — re-verifikasi di server sebelum menulis, meski query dasar tabel sudah difilter.

### 4.5 Generate Kode Unik (QR payload)

- **Guru**: `sha1(nama_guru . md5(nuptk . nama_guru . no_hp)) + 24 karakter pertama dari sha1(nuptk . random_int)`.
- **Siswa**: `str_replace('.', '-', uniqid(lebih_presisi=true)) + '-' + random_int(8 digit)`.

Digenerate otomatis saat record dibuat (hanya jika kolom kosong) — **tidak perlu direplikasi persis** saat rebuild selama hasilnya tetap unik & stabil (tidak berubah setelah pertama kali dibuat, karena inilah payload yang sudah tercetak di kartu/QR fisik).

QR code image di-generate on-the-fly (bukan disimpan permanen sampai diminta) via library QR (300px, error correction level tinggi, warna beda untuk siswa [biru] vs guru [hijau]), payload = `unique_code`, label nama dicetak di bawah QR.

### 4.6 Validasi RFID Unik Lintas Tabel

`rfid_code` harus unik **baik di dalam `tb_siswa` maupun `tb_guru` DAN lintas keduanya** — satu kartu fisik tidak boleh terdaftar ke siswa dan guru sekaligus. Validasi ini jalan di form create/edit, cek kedua tabel.

### 4.7 Hari Libur / Hari Kerja

- `tb_hari_libur` (tanggal eksplisit) = **satu-satunya** yang dicek untuk **menonaktifkan scan** pada tanggal tertentu.
- `general_settings.hari_kerja` (CSV hari kerja) dipakai **hanya** untuk menentukan tanggal mana yang masuk hitungan "hari kerja" di **laporan bulanan** (kolom-kolom tanggal yang muncul di tabel laporan) — **tidak** memengaruhi apakah scan diterima/ditolak. Dua mekanisme ini sengaja terpisah dan tidak sinkron satu sama lain (warisan dari aplikasi lama, dipertahankan apa adanya).

### 4.8 Approval Izin/Sakit

Saat status diubah jadi **Disetujui**:
- Untuk **setiap tanggal** dalam rentang `tanggal_mulai`..`tanggal_selesai` (inklusif):
  - Upsert baris presensi (siswa/guru, tanggal itu) dengan `id_kehadiran` = Sakit atau Izin (sesuai `tipe_izin`), `keterangan` = `alasan` pengajuan.
  - Ini **menimpa** baris presensi yang mungkin sudah ada di tanggal itu (mis. kalau siswa sempat scan hadir lalu izinnya baru disetujui belakangan).
- Tulis 1 baris audit log untuk perubahan status pengajuan itu sendiri (terlepas dari berapa banyak baris presensi yang ditulis).
- Saat status diubah jadi **Ditolak**: hanya update status, **tidak** menyentuh tabel presensi.
- Semua ini jalan dalam 1 database transaction.
- Guru (wali kelas) hanya boleh approve/reject pengajuan siswa di kelasnya — re-verifikasi ownership di server.

### 4.9 Notifikasi WhatsApp

- Fitur opsional, di-gate oleh flag `WA_NOTIFICATION` (env/config) — kalau off, semua notify jadi no-op.
- Provider: hanya "Fonnte" yang diimplementasi (arsitektur mendukung provider lain via interface, tapi cuma 1 yang ada).
- Dikirim ke `no_hp` milik siswa/guru, saat check-in DAN check-out (bukan saat edit manual atau approval izin).
- **Kegagalan kirim WA tidak pernah membatalkan/menggagalkan proses scan** — di-catch dan cuma dicatat ke log server.
- Isi pesan mencantumkan nama, NIS/NUPTK, tanggal, jam, dan info keterlambatan (jika ada, khusus check-in siswa).

### 4.10 Laporan Bulanan (PDF)

Untuk satu kelas (siswa) atau semua guru, dalam 1 bulan:
- Ambil semua "tanggal kerja" di bulan itu (lihat §4.7).
- Untuk tiap siswa/guru × tiap tanggal kerja: ambil `id_kehadiran` dari baris presensi (atau kosong jika tidak ada baris — **laporan TIDAK menerapkan logika Alfa/Belum Scan §4.1**, hanya menampilkan apa adanya di tabel presensi).
- Hitung jumlah laki-laki/perempuan sebagai ringkasan.
- Render sebagai PDF (matriks: baris = orang, kolom = tanggal, isi sel = status kehadiran).
- Jika hasil kosong (kelas/bulan tidak punya data sama sekali) → tampilkan pesan error, jangan generate PDF kosong.

### 4.11 Backup & Restore Database

- Backup: dump seluruh database via `mysqldump` (bukan tabel tertentu), hasil didownload sebagai file `.sql`.
- Restore: upload file `.sql`, dijalankan via `mysql` CLI dengan file itu sebagai stdin — **menimpa** database yang ada saat ini (butuh konfirmasi user sebelum eksekusi).
- Terpisah: backup/restore file upload (`storage/app/public` — logo, foto bukti izin, QR image cache) sebagai file `.zip`.
- **Catatan portabilitas**: implementasi saat ini shell-out ke binary `mysql`/`mysqldump` — kalau rebuild pakai bahasa lain, pertimbangkan pendekatan native (mis. driver DB export) supaya tidak bergantung pada binary eksternal di PATH.

### 4.12 Audit Log

- Ditulis eksplisit (bukan otomatis via observer) di titik-titik ini: edit kehadiran manual (guru & siswa), approval/reject izin. **Tidak** ditulis untuk CRUD data master (siswa/guru/kelas/dst) di implementasi saat ini — keputusan desain, bukan bug, tapi bisa dipertimbangkan ulang saat rebuild jika ingin cakupan audit lebih luas.
- Format: aksi (deskripsi human-readable), tabel & id record terdampak, snapshot JSON data lama & baru, siapa (user id) & dari IP mana, kapan.
- Read-only setelahnya — tidak ada fitur edit/hapus log dari UI.

---

## 5. Fitur per Modul

### 5.1 Panel Admin
- **Dashboard**: 4 angka ringkas kehadiran siswa hari ini (Hadir/Sakit/Izin/Alfa-atau-Belum Scan), grafik tren 7 hari terakhir, top 5 siswa paling sering terlambat (berdasar `poin_pelanggaran`), daftar siswa dengan ketidakhadiran beruntun.
- **Data Guru & Siswa**: CRUD guru (NUPTK, nama, jenis kelamin, alamat, no HP, kode RFID) dan siswa (NIS, nama, kelas, jenis kelamin, no HP, kode RFID) — masing-masing bisa import massal via CSV.
- **Kelas & Jurusan**: CRUD kelas (tingkat, jurusan, indeks, wali kelas opsional) dan jurusan — kelas tidak bisa dihapus jika masih ada siswa; jurusan tidak bisa dihapus jika masih ada kelas.
- **Presensi**: tabel harian (pilih tanggal, untuk guru; pilih tanggal+kelas, untuk siswa) menampilkan status/jam masuk/jam keluar tiap orang, dengan aksi "Ubah Kehadiran" (modal edit manual, §4.4).
- **QR Code**: generate/download/cetak QR per orang, atau download semua (zip) / cetak semua (halaman print 4-kolom) — untuk siswa bisa difilter per kelas.
- **Laporan**: pilih bulan (+ kelas, untuk siswa) → download PDF matriks kehadiran (§4.10).
- **Hari Libur**: CRUD tanggal libur + keterangan; ada aksi khusus "generate akhir pekan" (materialisasi semua hari Sabtu/Minggu dalam suatu rentang jadi baris hari libur otomatis).
- **Pengajuan Izin**: daftar semua pengajuan (siswa & guru), filter status, aksi Setujui/Tolak (§4.8), bisa dihapus.
- **Data Petugas**: CRUD akun staff (username, email, password, role, opsional link ke guru), sync role Spatie setelah create/edit.
- **Log Aktivitas**: daftar read-only + detail (lihat data lama/baru dalam format JSON rapi), filter per tabel.
- **Pengaturan**: form 1 baris (nama sekolah, tahun ajaran, upload logo, batas jam masuk, jam pulang standar, checkbox hari kerja, copyright footer).
- **Backup & Restore**: 4 aksi (backup DB, restore DB, backup foto/upload, restore foto/upload) — lihat §4.11.

### 5.2 Panel Guru (Wali Kelas)
Subset dari panel admin, **selalu di-scope ke kelas yang diampu sendiri** (server-derived, tidak pernah dari input client):
- Dashboard: ringkasan kehadiran kelasnya sendiri (4 angka, grafik tren, top 5 terlambat, ketidakhadiran beruntun — semua discope per kelas).
- Kehadiran Siswa: sama seperti Presensi Siswa di admin, tapi read-only ke 1 kelas.
- Pengajuan Izin: hanya pengajuan siswa di kelasnya.
- QR Code Siswa: hanya siswa di kelasnya.
- Laporan Kelas: PDF bulanan hanya untuk kelasnya, pakai service yang sama persis dengan laporan admin.
- Jika guru belum ditugaskan sebagai wali kelas manapun: semua halaman menampilkan state kosong dengan pesan yang jelas, bukan error.

### 5.3 Scanner Kiosk PWA (`/scan`, butuh login)
- Scan via kamera (baca QR) **atau** pembaca RFID (perilaku seperti keyboard input).
- **Bekerja penuh offline**:
  - Saat online, cache seluruh roster (semua unique_code + rfid_code + nama + nomor, siswa & guru) dan pengaturan (`jam_masuk_limit`, `jam_pulang_standard`, `hari_kerja`, status libur hari ini) ke penyimpanan lokal (IndexedDB).
  - Scan divalidasi terhadap roster lokal dulu (identitas siapa) tanpa perlu request ke server.
  - Hasil scan disimpan ke antrian lokal dengan timestamp device saat itu juga.
  - Saat online kembali, antrian di-flush ke server satu per satu (via Background Sync API jika didukung browser, atau retry loop foreground) — setiap scan dikirim dengan `scanned_at` = waktu asli scan (§4.3), bukan waktu sync.
  - Instalable sebagai app (manifest + service worker, installable/standalone display mode).

### 5.4 Portal Publik (tanpa login)
- **Pengajuan Izin (`/izin`)**: 
  1. Cari identitas dulu (by NIS untuk siswa, atau NUPTK untuk guru) → dapat konfirmasi nama.
  2. Submit form: rentang tanggal, tipe (Sakit/Izin), alasan, **wajib upload foto bukti** (jpg/jpeg/png, maks 2MB) → status awal selalu "Pending", menunggu approval admin/guru.
- **Cek Kehadiran (`/cek-kehadiran`)**:
  - "Autentikasi" hanya kombinasi NIS + nomor HP yang cocok (bukan password sebenarnya — kelemahan yang disengaja dipertahankan dari aplikasi lama, di-mitigasi dengan rate-limit request, bukan diperbaiki penuh).
  - Tampilkan riwayat presensi bulan berjalan + ringkasan jumlah Hadir/Sakit/Izin/Alfa.

---

## 6. Kontrak API (Scanner PWA & Portal Publik)

Semua response JSON. Endpoint scan butuh sesi login (Sanctum stateful, cookie-based); endpoint izin/cek-kehadiran publik tapi rate-limited (20 request/menit).

### `GET /api/scan/bootstrap` (auth)
Response:
```json
{
  "roster": [{ "id": 1, "unique_code": "...", "rfid_code": "...", "nama": "...", "nomor": "...", "type": "siswa|guru" }, ...],
  "settings": { "jam_masuk_limit": "07:00:00", "jam_pulang_standard": "14:00:00", "hari_kerja": "1,2,3,4,5" },
  "today": "2026-07-14",
  "holiday_reason": null
}
```

### `POST /api/scan` (auth)
Request: `{ "unique_code": "...", "waktu": "masuk|pulang", "scanned_at": "2026-07-14T07:05:00" }` (`scanned_at` opsional, default sekarang).

Response sukses (200): `{ "status": true, "message": "...", "presensi": {...}, "type": "siswa|guru", "nama": "...", "nomor": "..." }`
Response gagal (409 sudah absen / 404 tidak ditemukan / 422 hari libur): `{ "status": false, "message": "..." }`

### `POST /api/izin/lookup` (public, rate-limited)
Request: `{ "type": "siswa|guru", "identifier": "NIS-atau-NUPTK" }` → `{ "status": "success", "data": { "id": ..., "nama": "..." } }` atau 404.

### `POST /api/izin/submit` (public, rate-limited, multipart)
Request: `type`, `id_target`, `tanggal_mulai`, `tanggal_selesai`, `tipe_izin`, `alasan`, `bukti` (file gambar wajib) → `{ "status": "success", "message": "..." }`.

### `POST /api/cek-kehadiran` (public, rate-limited)
Request: `{ "nis": "...", "no_hp": "..." }` → `{ "status": "success", "siswa": {...}, "history": [...], "stats": {"hadir":n,"sakit":n,"izin":n,"alfa":n}, "month_name": "Juli 2026" }` atau 404 jika kombinasi tidak cocok.

---

## 7. Data Seed / Default

- **Superadmin**: `adminsuper@gmail.com` / password `superadmin`, role `superadmin`+`admin`.
- **Kehadiran**: 4 baris tetap (id harus persis 1=Hadir, 2=Sakit, 3=Izin, 4=Tanpa keterangan — banyak kode mengasumsikan id ini secara hardcoded).
- **Jurusan** default: OTKP, BDP, AKL, RPL.
- **Kelas** default: kombinasi X/XI/XII × setiap jurusan × indeks "A" (bukan data riil, placeholder untuk instalasi baru).
- **General settings**: 1 baris default (lihat §3).
- **Permission matrix**: lihat §2.

---

## 8. Catatan Penting untuk Rebuild

1. **Logika status "Belum Scan"/"Alfa" (§4.1) adalah yang paling gampang salah dibangun ulang** — pastikan waktu sekolah (jam pulang standar) benar-benar dicek relatif terhadap *waktu saat ini*, bukan tanggal presensi, karena statusnya berubah otomatis seiring waktu berjalan meski tidak ada aksi apapun.
2. **`poin_pelanggaran` adalah akumulasi permanen** menit keterlambatan — tidak pernah direset otomatis oleh sistem manapun. Jika ingin ada reset periodik (semester baru dsb.), itu harus jadi fitur baru, bukan asumsi ulang perilaku lama.
3. **Snapshot `id_kelas` di `tb_presensi_siswa`** direkam saat presensi dibuat, bisa berbeda dari kelas siswa saat ini — penting untuk laporan historis tetap akurat meski siswa pindah kelas di kemudian hari.
4. **IDOR protection untuk panel guru**: SELALU derive kelas dari `user → guru → wali_kelas`, jangan pernah percaya `id_kelas` dari client, dan re-verifikasi kepemilikan di titik tulis (bukan cuma di query baca) — ini pola yang konsisten dipakai di semua endpoint guru pada implementasi saat ini.
5. **`hari_kerja` vs `tb_hari_libur` sengaja tidak sinkron** (§4.7) — putuskan secara sadar saat rebuild apakah ingin disatukan (ini akan mengubah perilaku dibanding aplikasi lama) atau dipertahankan terpisah.
6. **RFID unik lintas tabel siswa+guru** (§4.6), bukan cuma unik per tabel.
7. **Offline-first scanner (§5.3) adalah fitur non-trivial** — kalau stack baru tidak butuh PWA/offline, ini bisa disederhanakan jadi request langsung ke server, tapi pertimbangkan apakah kiosk sekolah punya koneksi internet yang bisa diandalkan sebelum menghilangkan kemampuan ini.
8. **Kegagalan notifikasi WA tidak boleh menggagalkan scan** — selalu best-effort/non-blocking di titik manapun ada side-effect notifikasi.
9. Root cause performa Livewire yang lambat (alasan rebuild ini) sudah dianalisis terpisah di riwayat kerja sebelumnya: kombinasi OPcache mati, `php artisan serve` single-threaded, dan tidak ada SPA-mode navigasi — **bukan berarti arsitektur data/logika di atas bermasalah**. Kalau stack baru punya server yang proper (bukan built-in dev server) + best-practice caching, sebagian besar keluhan performa sebenarnya sudah teratasi tanpa perlu membongkar logika bisnis ini. Pertimbangkan itu sebelum commit penuh ke penulisan ulang semuanya dari nol.
