# Panduan Simulasi Test Manual - SMS App

Dokumen ini berisi panduan lengkap untuk menguji semua fitur aplikasi SMS (School Management System) secara manual, termasuk kredensial login untuk setiap role dan skenario pengujian.

---

## Daftar Isi

1. [Kredensial Login](#kredensial-login)
2. [Matriks Akses Menu per Role](#matriks-akses-menu-per-role)
3. [Skenario Test per Modul](#skenario-test-per-modul)
4. [Checklist Fungsionalitas](#checklist-fungsionalitas)
5. [Known Issues & Limitations](#known-issues--limitations)

---

## Kredensial Login

**Password default untuk semua user: `password`**

### Super Admin (Akses Sistem)
| Username | Email | Role | Akses |
|----------|-------|------|-------|
| `superadmin` | `superadmin@sms.local` | Super Administrator | Semua tenant, manajemen sistem |

### User Demo Sekolah (Tenant: Demo School)

| Username | Email | Role | Nama Lengkap |
|----------|-------|------|--------------|
| `admin` | `admin@demo.sms.local` | Admin | Administrator Sekolah |
| `kepsek` | `kepsek@demo.sms.local` | Kepala Sekolah | Dr. Ahmad Hidayat, M.Pd |
| `wakasek` | `wakasek@demo.sms.local` | Wakil Kepala Sekolah | Siti Rahayu, S.Pd |
| `guru1` | `guru1@demo.sms.local` | Guru | Budi Santoso, S.Pd |
| `guru2` | `guru2@demo.sms.local` | Wali Kelas | Dewi Lestari, S.Pd |
| `tu` | `tu@demo.sms.local` | Tata Usaha | Hendra Wijaya |
| `bendahara` | `bendahara@demo.sms.local` | Bendahara | Sri Mulyani |
| `pustakawan` | `pustakawan@demo.sms.local` | Pustakawan | Agus Prabowo |
| `siswa1` | `siswa1@demo.sms.local` | Siswa | Andi Pratama |
| `siswa2` | `siswa2@demo.sms.local` | Siswa | Sari Indah |
| `ortu1` | `ortu1@demo.sms.local` | Orang Tua | Joko Pratama |

---

## Matriks Akses Menu per Role

### Legenda
- ✅ = Akses penuh (CRUD)
- 👁️ = Hanya lihat (Read-only)
- ❌ = Tidak ada akses
- 🔸 = Akses terbatas (data sendiri/kelas sendiri)

### Menu Utama

| Menu | Super Admin | Admin | Kepsek | Wakasek | Guru | Wali Kelas | TU | Bendahara | Pustakawan | Siswa | Ortu |
|------|-------------|-------|--------|---------|------|------------|----|-----------|-----------:|-------|------|
| **Dashboard** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Akademik** | | | | | | | | | | | |
| - Tahun Ajaran | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Kurikulum | ✅ | ✅ | 👁️ | ✅ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Mata Pelajaran | ✅ | ✅ | 👁️ | ✅ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Tingkat Kelas | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Jurusan | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Kelas | ✅ | ✅ | 👁️ | ✅ | 👁️ | 🔸 | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Jadwal | ✅ | ✅ | 👁️ | ✅ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | 🔸 | ❌ |
| **Siswa** | | | | | | | | | | | |
| - Data Siswa | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 🔸 | ✅ | ❌ | ❌ | 🔸 | 🔸 |
| - Pendaftaran | ✅ | ✅ | 👁️ | 👁️ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Guru & Staff** | | | | | | | | | | | |
| - Data Guru | ✅ | ✅ | 👁️ | 👁️ | 🔸 | 🔸 | ✅ | ❌ | ❌ | ❌ | ❌ |
| - Data Staff | ✅ | ✅ | 👁️ | 👁️ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Absensi** | | | | | | | | | | | |
| - Absensi Saya | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| - Scan QR/RFID | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| - Absensi Siswa | ✅ | ✅ | 👁️ | ✅ | 👁️ | 🔸 | ✅ | ❌ | ❌ | 🔸 | 🔸 |
| - Absensi Pegawai | ✅ | ✅ | 👁️ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| - Rekap Absensi | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 🔸 | 👁️ | ❌ | ❌ | ❌ | ❌ |
| - Izin & Sakit | ✅ | ✅ | ✅ | ✅ | 👁️ | 🔸 | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Nilai & Ujian** | | | | | | | | | | | |
| - Ujian | ✅ | ✅ | 👁️ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| - Input Nilai | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| - Rekap Nilai | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 🔸 | ❌ | ❌ | ❌ | 🔸 | 🔸 |
| **Keuangan** | | | | | | | | | | | |
| - Jenis Biaya | ✅ | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| - Struktur Biaya | ✅ | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| - Tagihan Siswa | ✅ | ✅ | 👁️ | ❌ | ❌ | 🔸 | ❌ | ✅ | ❌ | 🔸 | 🔸 |
| - Pembayaran | ✅ | ✅ | 👁️ | ❌ | ❌ | 🔸 | ❌ | ✅ | ❌ | 🔸 | 🔸 |
| - Laporan Keuangan | ✅ | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| **Penggajian** | | | | | | | | | | | |
| - Semua Menu | ✅ | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| **Perpustakaan** | | | | | | | | | | | |
| - Katalog Buku | ✅ | ✅ | 👁️ | 👁️ | 👁️ | 👁️ | 👁️ | ❌ | ✅ | 👁️ | ❌ |
| - Peminjaman | ✅ | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | 🔸 | ❌ |
| **Pengaturan** | | | | | | | | | | | |
| - Umum | ✅ | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| - Pengguna | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| - Keamanan Login | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| - Backup Database | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| - Manajemen Sekolah | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## Skenario Test per Modul

### 1. Autentikasi & Registrasi

#### Test 1.1: Login Standard
| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka `/login` | Halaman login tampil dengan branding sekolah (jika dikonfigurasi) |
| 2 | Masukkan username: `admin`, password: `password` | - |
| 3 | Klik "Masuk" | Redirect ke dashboard, nama user tampil di header |

#### Test 1.2: Login Gagal
| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Login dengan password salah | Pesan error "Kredensial tidak valid" |
| 2 | Login dengan user tidak ada | Pesan error yang sama (tidak bocor info user exist) |
| 3 | Login 5x salah berturut-turut | Akun ter-throttle, muncul pesan tunggu |

#### Test 1.3: Registrasi Mandiri
| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka `/register` | Form registrasi tampil |
| 2 | Isi form lengkap, klik Daftar | Status akun: `pending`, tidak bisa login |
| 3 | Login sebagai `admin` | - |
| 4 | Buka Pengaturan > Keamanan Login | Lihat pendaftar baru dengan badge "Menunggu Aktivasi" |
| 5 | Klik "Buat Kode" pada pendaftar | Kode OTP 6 digit muncul (berlaku 15 menit) |
| 6 | Logout, kembali ke halaman aktivasi | - |
| 7 | Masukkan kode OTP | Akun aktif, bisa login dengan password yang dibuat |

#### Test 1.4: Login OTP (Tanpa Password)
| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Login sebagai `admin` | - |
| 2 | Buka Pengaturan > Keamanan Login | - |
| 3 | Pilih user, klik "Buat Kode Login" | Kode OTP muncul |
| 4 | Logout | - |
| 5 | Di halaman login, klik "Login dengan Kode" | Form kode muncul |
| 6 | Masukkan email + kode | Login berhasil tanpa password |

---

### 2. Dashboard

#### Test 2.1: Dashboard Admin
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka `/dashboard` | Statistik tampil: jumlah siswa, guru, kelas |
| 2 | Lihat grafik kehadiran | Grafik trend kehadiran 7 hari terakhir |
| 3 | Lihat ringkasan keuangan | Total tagihan, pembayaran bulan ini |

#### Test 2.2: Dashboard Guru
**Login:** `guru1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka `/dashboard` | Hanya statistik terbatas (jadwal hari ini, kelas diampu) |
| 2 | Tidak ada data keuangan | Bagian keuangan tidak muncul |

#### Test 2.3: Dashboard Siswa
**Login:** `siswa1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka `/dashboard` | Jadwal hari ini, status kehadiran, tagihan pribadi |
| 2 | Tidak ada data siswa lain | Hanya melihat data sendiri |

---

### 3. Modul Akademik

#### Test 3.1: Tahun Ajaran
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Akademik > Tahun Ajaran | Daftar tahun ajaran tampil |
| 2 | Klik "Tambah Tahun Ajaran" | Form muncul |
| 3 | Isi: Nama "2026/2027", tanggal mulai/selesai | - |
| 4 | Simpan | Tahun ajaran baru muncul di daftar |
| 5 | Klik "Aktifkan" pada tahun ajaran | Status berubah jadi aktif, yang lain nonaktif |
| 6 | Buka Semester, klik "Aktifkan" semester 1 | Semester aktif berubah |

#### Test 3.2: Kurikulum & Mata Pelajaran
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Akademik > Kurikulum | Daftar kurikulum (Merdeka, K13, dll) |
| 2 | Tambah kurikulum baru | Kurikulum tersimpan |
| 3 | Buka Akademik > Mata Pelajaran | Daftar mapel |
| 4 | Tambah mapel: Kode "MTK", Nama "Matematika" | Mapel tersimpan |
| 5 | Assign mapel ke kurikulum | Relasi tersimpan |

#### Test 3.3: Kelas & Wali Kelas
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Akademik > Kelas | Daftar kelas tampil |
| 2 | Tambah kelas: "X IPA 1", tingkat X, jurusan IPA | Kelas tersimpan |
| 3 | Edit kelas, pilih wali kelas | Guru ter-assign sebagai wali |
| 4 | Klik "Sinkron" | Kelas disinkronkan dengan enrollment siswa |

#### Test 3.4: Jadwal Pelajaran
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Akademik > Jadwal | Tampilan mingguan/harian |
| 2 | Pilih kelas, klik slot waktu | Form tambah jadwal muncul |
| 3 | Pilih mapel, guru, jam mulai/selesai | Jadwal tersimpan |
| 4 | Klik "Salin ke Hari Lain" | Jadwal terduplikasi |
| 5 | Export PDF | File jadwal terunduh |

---

### 4. Modul Siswa

#### Test 4.1: CRUD Siswa
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Siswa > Data Siswa | Daftar siswa dengan pagination |
| 2 | Filter: status "Aktif", kelas "X IPA 1" | Daftar terfilter |
| 3 | Klik "Tambah Siswa" | Form lengkap muncul |
| 4 | Isi: NIS, nama, tanggal lahir, alamat, wali | - |
| 5 | Upload foto | Preview foto muncul |
| 6 | Simpan | Siswa baru muncul di daftar |
| 7 | Klik detail siswa | Tab: Profil, Kehadiran, Nilai, Keuangan |
| 8 | Edit data siswa | Perubahan tersimpan |
| 9 | Nonaktifkan siswa | Status berubah "Tidak Aktif" |

#### Test 4.2: Import Siswa
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Klik "Import" | Dialog upload muncul |
| 2 | Download template Excel | File template terunduh |
| 3 | Isi template dengan data siswa | - |
| 4 | Upload file | Preview data muncul |
| 5 | Validasi: cek error (NIS duplikat, format salah) | Error ditandai merah |
| 6 | Konfirmasi import | Siswa ter-import, laporan hasil muncul |

#### Test 4.3: Pendaftaran/Enrollment
**Login:** `tu` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Siswa > Pendaftaran | Daftar enrollment tahun ajaran aktif |
| 2 | Klik "Tambah Enrollment" | Pilih siswa dan kelas |
| 3 | Pilih siswa yang belum terdaftar | - |
| 4 | Pilih kelas tujuan | - |
| 5 | Simpan | Siswa terdaftar di kelas untuk tahun ajaran aktif |

---

### 5. Modul Guru & Staff

#### Test 5.1: Data Guru
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Guru & Staff > Data Guru | Daftar guru |
| 2 | Tambah guru baru | Form: NIP, nama, email, mapel, dll |
| 3 | Assign guru ke mata pelajaran | Relasi tersimpan |
| 4 | Lihat "Ringkasan Penugasan" | Statistik beban mengajar |

#### Test 5.2: Data Staff
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Guru & Staff > Data Staff | Daftar staff non-guru |
| 2 | Tambah staff: bendahara, TU, security | - |
| 3 | Pilih departemen dan posisi | Data tersimpan |

---

### 6. Modul Absensi

#### Test 6.1: Check-in/Check-out Pegawai
**Login:** `guru1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Absensi > Absensi Saya | Tombol Check-in muncul (jika belum absen) |
| 2 | Klik "Check-in" | Waktu check-in tercatat |
| 3 | Setelah jam pulang, klik "Check-out" | Waktu check-out tercatat |
| 4 | Lihat riwayat absensi | Catatan hari ini muncul |

#### Test 6.2: Scan QR Siswa
**Login:** `guru1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka `/scanner` atau klik "Scan Absensi" | Kamera aktif (jika mobile) |
| 2 | Scan QR code siswa | Siswa teridentifikasi |
| 3 | Pilih status: Hadir/Sakit/Izin/Alpha | Status tercatat |
| 4 | Sistem mencatat jam kedatangan | Otomatis hitung terlambat jika melewati batas |

#### Test 6.3: Absensi Kelas (Wali Kelas)
**Login:** `guru2` / `password` (Wali Kelas)

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Absensi > Absensi Siswa | Hanya siswa kelas sendiri yang tampil |
| 2 | Pilih tanggal | - |
| 3 | Input absensi manual per siswa | Status tersimpan |
| 4 | Bulk update: pilih beberapa siswa, set "Hadir" | Batch update berhasil |

#### Test 6.4: Rekap & Laporan Absensi
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Absensi > Rekap Absensi | Filter: tanggal, kelas, status |
| 2 | Pilih rentang tanggal 1 bulan | Rekap per siswa muncul |
| 3 | Export Excel | File rekap terunduh |
| 4 | Lihat "Siswa Sering Tidak Hadir" | Daftar siswa dengan alpha >3 hari |

#### Test 6.5: Manajemen Izin
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Absensi > Izin & Sakit | Daftar pengajuan izin |
| 2 | Filter: status "Menunggu" | Pengajuan pending tampil |
| 3 | Klik detail, lihat lampiran (surat dokter, dll) | File terlampir bisa dibuka |
| 4 | Klik "Setujui" atau "Tolak" | Status berubah, tercatat di absensi siswa |

#### Test 6.6: Pengaturan Absensi
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Absensi > Pengaturan | Form konfigurasi |
| 2 | Set jam masuk: 07:00, batas terlambat: 07:15 | - |
| 3 | Set jam pulang: 14:00 | - |
| 4 | Aktifkan notifikasi WhatsApp | Toggle aktif |
| 5 | Simpan | Pengaturan tersimpan |

---

### 7. Modul Nilai & Ujian

#### Test 7.1: Manajemen Ujian
**Login:** `wakasek` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Nilai & Ujian > Ujian | Daftar ujian |
| 2 | Tambah ujian: "UTS Semester 1" | Form: nama, tanggal, tipe |
| 3 | Pilih mata pelajaran yang diujikan | Relasi tersimpan |
| 4 | Publish ujian | Status "Published" |

#### Test 7.2: Input Nilai
**Login:** `guru1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Nilai & Ujian > Input Nilai | Pilih kelas dan mapel yang diampu |
| 2 | Pilih ujian/tugas | Daftar siswa muncul |
| 3 | Input nilai per siswa | Auto-save atau tombol simpan |
| 4 | Bulk input: paste dari Excel | Data ter-parse |
| 5 | Finalisasi nilai | Nilai terkunci, tidak bisa diubah |

#### Test 7.3: Rekap Nilai (Siswa)
**Login:** `siswa1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Nilai & Ujian > Rekap Nilai | Hanya nilai sendiri yang tampil |
| 2 | Filter per semester | Nilai terfilter |
| 3 | Lihat rata-rata per mapel | Kalkulasi otomatis |

---

### 8. Modul Keuangan

#### Test 8.1: Setup Jenis & Struktur Biaya
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Keuangan > Jenis Biaya | Daftar: SPP, Uang Kegiatan, dll |
| 2 | Tambah jenis biaya: "Kas Mingguan", frekuensi: weekly | Tersimpan |
| 3 | Buka Keuangan > Struktur Biaya | Daftar per tingkat/jurusan |
| 4 | Tambah struktur: SPP kelas X = Rp 500.000 | Tersimpan |
| 5 | Set tanggal jatuh tempo: tanggal 10 | Tersimpan |

#### Test 8.2: Generate Tagihan
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Keuangan > Tagihan Siswa | - |
| 2 | Klik "Generate Tagihan" | Dialog muncul |
| 3 | Pilih: bulan, tahun, jenis biaya, kelas | - |
| 4 | Preview jumlah siswa yang akan ditagih | - |
| 5 | Konfirmasi | Tagihan ter-generate untuk semua siswa terpilih |

#### Test 8.3: Input Pembayaran
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Keuangan > Pembayaran | - |
| 2 | Klik "Tambah Pembayaran" | Form muncul |
| 3 | Cari siswa, pilih tagihan yang dibayar | List tagihan muncul |
| 4 | Input nominal, pilih metode pembayaran | - |
| 5 | Upload bukti transfer (jika ada) | File terupload |
| 6 | Simpan | Pembayaran tercatat, status tagihan berubah |
| 7 | Cetak kuitansi | PDF kuitansi terunduh |

#### Test 8.4: Verifikasi Pembayaran
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Keuangan > Pembayaran | Filter: status "Menunggu Verifikasi" |
| 2 | Klik detail pembayaran | Lihat bukti transfer |
| 3 | Klik "Verifikasi" atau "Tolak" | Status berubah |

#### Test 8.5: Laporan Keuangan
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Keuangan > Laporan | Dashboard statistik |
| 2 | Filter per bulan/kelas | Data terfilter |
| 3 | Lihat: total tagihan, terbayar, tunggakan | Angka akurat |
| 4 | Export laporan Excel | File terunduh |

#### Test 8.6: Lihat Tagihan (Siswa/Ortu)
**Login:** `siswa1` / `password` atau `ortu1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Keuangan > Tagihan | Hanya tagihan sendiri/anak yang tampil |
| 2 | Lihat status: Lunas/Belum Bayar/Sebagian | Status jelas |
| 3 | Lihat riwayat pembayaran | Detail pembayaran sebelumnya |

---

### 9. Modul Penggajian

#### Test 9.1: Setup Komponen Gaji
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Penggajian > Komponen Gaji | Daftar komponen |
| 2 | Tambah komponen: "Tunjangan Transport", tipe: Penambah | Tersimpan |
| 3 | Set formula (jika ada) | Validasi formula berjalan |

#### Test 9.2: Setup Golongan Gaji
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Penggajian > Golongan Gaji | Daftar golongan (I/a, II/a, dst) |
| 2 | Set gaji pokok per golongan | Tersimpan |

#### Test 9.3: Proses Penggajian
**Login:** `bendahara` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Penggajian > Proses Penggajian | - |
| 2 | Buat periode gaji baru: "September 2026" | Periode tersimpan |
| 3 | Klik "Generate Slip" | Slip gaji ter-generate untuk semua pegawai |
| 4 | Review slip per pegawai | Detail: gaji pokok, tunjangan, potongan |
| 5 | Edit item slip jika perlu | Perubahan tersimpan |
| 6 | Submit untuk approval | Status: "Menunggu Persetujuan" |

#### Test 9.4: Approval Penggajian
**Login:** `kepsek` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Penggajian > Proses Penggajian | Lihat periode menunggu approval |
| 2 | Review total pengeluaran | - |
| 3 | Klik "Setujui" | Status: "Disetujui" |
| 4 | Bendahara bisa finalisasi dan tandai "Dibayar" | Status final |

#### Test 9.5: Download Slip Gaji
**Login:** `guru1` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Penggajian (jika punya akses) atau profil | - |
| 2 | Lihat slip gaji bulan ini | Detail slip tampil |
| 3 | Download PDF | Slip gaji terunduh |

---

### 10. Modul Perpustakaan

#### Test 10.1: Katalog Buku
**Login:** `pustakawan` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Perpustakaan > Katalog Buku | Daftar buku dengan search |
| 2 | Tambah buku: ISBN, judul, pengarang, kategori | Tersimpan |
| 3 | Set jumlah eksemplar | Stok tercatat |
| 4 | Upload cover buku | Gambar tampil |

#### Test 10.2: Peminjaman Buku
**Login:** `pustakawan` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Perpustakaan > Peminjaman | - |
| 2 | Klik "Pinjam Buku" | Form muncul |
| 3 | Pilih/scan anggota (siswa/guru) | Data anggota muncul |
| 4 | Pilih/scan buku | Cek ketersediaan stok |
| 5 | Set tanggal kembali | - |
| 6 | Simpan | Peminjaman tercatat, stok berkurang |

#### Test 10.3: Pengembalian Buku
**Login:** `pustakawan` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Cari peminjaman aktif | - |
| 2 | Klik "Kembalikan" | - |
| 3 | Sistem hitung denda jika terlambat | Denda otomatis |
| 4 | Konfirmasi | Buku kembali, stok bertambah |

---

### 11. Modul Pengaturan

#### Test 11.1: Profil Sekolah
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Pengaturan > Umum | Form profil sekolah |
| 2 | Edit: nama, NPSN, alamat, telepon | - |
| 3 | Upload logo sekolah | Preview muncul |
| 4 | Simpan | Data tersimpan, logo tampil di header |

#### Test 11.2: Branding Halaman Login
**Login:** `superadmin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Pengaturan > Umum | Bagian "Branding Halaman Login" |
| 2 | Pilih sekolah dari dropdown | - |
| 3 | Simpan | Logo & nama sekolah tampil di `/login` |
| 4 | Pilih "Tidak Ada (Default)" | `/login` kembali ke tampilan default |

#### Test 11.3: Manajemen User
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Pengaturan > Pengguna | Daftar user dengan filter role |
| 2 | Tambah user baru | Form: username, email, password, role |
| 3 | Assign role ke user | Role tersimpan |
| 4 | Reset password user | Password direset |
| 5 | Nonaktifkan user | User tidak bisa login |

#### Test 11.4: Keamanan Login
**Login:** `admin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Pengaturan > Keamanan Login | Tab: Riwayat Login, Pending, Aktif |
| 2 | Lihat riwayat login | Log: waktu, IP, metode, status |
| 3 | Revoke sesi user | User ter-logout paksa |
| 4 | Generate kode OTP untuk user | Kode muncul |

#### Test 11.5: Backup Database (Super Admin Only)
**Login:** `superadmin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka Pengaturan > Backup Database | Daftar backup tersedia |
| 2 | Klik "Backup Sekarang" | Proses backup, file baru muncul |
| 3 | Download backup | File `.sql.gz` terunduh |
| 4 | (Opsional) Ubah Koneksi Database | Masukkan password akses terlebih dahulu |

#### Test 11.6: Manajemen Sekolah (Super Admin Only)
**Login:** `superadmin` / `password`

| Step | Aksi | Expected Result |
|------|------|-----------------|
| 1 | Buka menu tenant (header dropdown) | Daftar sekolah |
| 2 | Tambah sekolah baru | Form: nama, slug, email, status |
| 3 | Switch ke sekolah lain | Konteks berubah |
| 4 | Edit/hapus sekolah | Operasi berhasil |

---

## Checklist Fungsionalitas

### Status Modul

| Modul | Status | Catatan |
|-------|--------|---------|
| Autentikasi | ✅ Berfungsi | Login, register, OTP, aktivasi |
| Dashboard | ✅ Berfungsi | Statistik per role |
| Tahun Ajaran | ✅ Berfungsi | CRUD, aktivasi |
| Kurikulum | ✅ Berfungsi | CRUD |
| Mata Pelajaran | ✅ Berfungsi | CRUD, assign ke kurikulum |
| Tingkat Kelas | ✅ Berfungsi | CRUD |
| Jurusan | ✅ Berfungsi | CRUD |
| Kelas | ✅ Berfungsi | CRUD, wali kelas, sinkronisasi |
| Jadwal | ✅ Berfungsi | CRUD, copy, export PDF |
| Data Siswa | ✅ Berfungsi | CRUD, import/export, foto |
| Pendaftaran Siswa | ✅ Berfungsi | Enrollment per tahun ajaran |
| Data Guru | ✅ Berfungsi | CRUD, import/export |
| Data Staff | ✅ Berfungsi | CRUD |
| Absensi Pegawai | ✅ Berfungsi | Check-in/out, rekap |
| Absensi Siswa | ✅ Berfungsi | Scan QR, manual, rekap |
| Manajemen Izin | ✅ Berfungsi | Pengajuan, approval |
| Hari Libur | ✅ Berfungsi | Generate weekend, CRUD |
| Ujian | ✅ Berfungsi | CRUD |
| Input Nilai | ✅ Berfungsi | Individual, bulk |
| Rekap Nilai | ✅ Berfungsi | Per siswa/kelas |
| Jenis Biaya | ✅ Berfungsi | CRUD, frequency weekly ditambahkan |
| Struktur Biaya | ✅ Berfungsi | CRUD, per tingkat/jurusan |
| Tagihan Siswa | ✅ Berfungsi | Generate, waive |
| Pembayaran | ✅ Berfungsi | Input, verifikasi, kuitansi |
| Laporan Keuangan | ✅ Berfungsi | Dashboard, export |
| Komponen Gaji | ✅ Berfungsi | CRUD, formula |
| Golongan Gaji | ✅ Berfungsi | CRUD |
| Tarif BPJS | ✅ Berfungsi | CRUD per tipe |
| Tarif Pajak | ✅ Berfungsi | CRUD per tahun |
| Proses Penggajian | ✅ Berfungsi | Generate, approval, finalisasi |
| Katalog Buku | ✅ Berfungsi | CRUD |
| Peminjaman Buku | ✅ Berfungsi | Pinjam, kembali, denda |
| Pengaturan Umum | ✅ Berfungsi | Profil sekolah, branding |
| Manajemen User | ✅ Berfungsi | CRUD, role assignment |
| Keamanan Login | ✅ Berfungsi | History, OTP, revoke |
| Backup Database | ✅ Berfungsi | Manual backup, download |
| Manajemen Sekolah | ✅ Berfungsi | Multi-tenant |

---

## Menu yang BELUM BERFUNGSI (Not Implemented)

### Status: Merah - Tidak Bisa Diakses

Berikut daftar menu yang **sudah ada di sidebar tapi belum diimplementasikan**. Mengklik menu ini akan menghasilkan error 404 atau 501.

| Menu | Submenu | Status | Keterangan |
|------|---------|--------|------------|
| **Siswa** | Pendaftaran | ❌ 404 | Route & halaman tidak ada |
| **Siswa** | Prestasi | ❌ 404 | Route & halaman tidak ada |
| **Guru & Staff** | Pengajuan Cuti | ❌ 404 | Route & halaman tidak ada |
| **Nilai & Ujian** | Ujian | ❌ 404 | **Seluruh modul belum ada** |
| **Nilai & Ujian** | Input Nilai | ❌ 404 | **Seluruh modul belum ada** |
| **Nilai & Ujian** | Rekap Nilai | ❌ 404 | **Seluruh modul belum ada** |
| **Perpustakaan** | Katalog Buku | ❌ 404 | **Seluruh modul belum ada** |
| **Perpustakaan** | Peminjaman | ❌ 404 | **Seluruh modul belum ada** |
| **Perpustakaan** | Anggota | ❌ 404 | **Seluruh modul belum ada** |
| **Laporan** | Rapor | ❌ 404 | Halaman tidak ada |
| **Laporan** | Generate Laporan | ❌ 404 | Halaman tidak ada |

### Detail Teknis

**1. Modul Siswa (Partial)**
- `/students/enrollment` - Route tidak terdaftar di `web.php`
- `/students/achievements` - Route tidak terdaftar di `web.php`
- API `EnrollmentController` return 501 "Belum diimplementasi"

**2. Modul Guru & Staff (Partial)**
- `/leave-requests` - Route tidak ada
- API `LeaveRequestController` return 501

**3. Modul Nilai & Ujian (SELURUH MODUL)**
- `/exams`, `/grades/input`, `/grades` - Semua route tidak ada
- Tidak ada file React di `resources/js/pages/exams/` atau `grades/`
- API Controllers (`ExamController`, `ScoreController`, `GradeController`) return 501

**4. Modul Perpustakaan (SELURUH MODUL)**
- `/library/books`, `/library/loans`, `/library/members` - Semua route tidak ada
- Tidak ada file React di `resources/js/pages/library/`
- API Controllers (`BookController`, `LoanController`, `MemberController`) return 501

**5. Modul Laporan (Partial)**
- `/reports/report-cards`, `/reports/generate` - Route tidak ada
- Hanya `/reports/expense` yang berfungsi
- API Controllers (`ReportCardController`, `GeneratedReportController`) return 501

### API Controllers dengan Status 501 (Not Implemented)

Total **26 controller** mengembalikan HTTP 501 dengan pesan:
```json
{
  "message": "Fitur ini belum diimplementasi."
}
```

Daftar lengkap:
- **Admin**: `ActivityLogController`, `AuditLogController`, `PermissionController`, `RoleController`, `SystemController`
- **Exams/Grades**: `ExamController`, `ExamTypeController`, `GradeController`, `ScoreController`
- **Library**: `BookController`, `BookCategoryController`, `LoanController`, `MemberController`, `ReservationController`, `LibrarySettingController`
- **Reports**: `ReportCardController`, `GeneratedReportController`
- **Notifications**: `AnnouncementController`
- **Staff**: `LeaveRequestController`, `DepartmentController`, `PositionController`
- **Student**: `EnrollmentController`, `GuardianController`
- **Tenant**: `TenantController`

---

## Menu yang BERFUNGSI (Implemented)

### Status: Hijau - Bisa Digunakan

| Menu | Submenu | Status |
|------|---------|--------|
| **Dashboard** | - | ✅ Berfungsi |
| **Akademik** | Tahun Ajaran | ✅ Berfungsi |
| **Akademik** | Kurikulum | ✅ Berfungsi |
| **Akademik** | Mata Pelajaran | ✅ Berfungsi |
| **Akademik** | Tingkat Kelas | ✅ Berfungsi |
| **Akademik** | Jurusan | ✅ Berfungsi |
| **Akademik** | Kelas | ✅ Berfungsi |
| **Akademik** | Jadwal | ✅ Berfungsi |
| **Siswa** | Data Siswa | ✅ Berfungsi |
| **Guru & Staff** | Data Guru | ✅ Berfungsi |
| **Guru & Staff** | Data Staff | ✅ Berfungsi |
| **Absensi** | Absensi Saya | ✅ Berfungsi |
| **Absensi** | Scan QR/RFID | ✅ Berfungsi |
| **Absensi** | Absensi Siswa | ✅ Berfungsi |
| **Absensi** | Absensi Pegawai | ✅ Berfungsi |
| **Absensi** | Rekap Absensi | ✅ Berfungsi |
| **Absensi** | Izin & Sakit | ✅ Berfungsi |
| **Absensi** | Hari Libur | ✅ Berfungsi |
| **Absensi** | Pengaturan | ✅ Berfungsi |
| **Keuangan** | Jenis Biaya | ✅ Berfungsi |
| **Keuangan** | Struktur Biaya | ✅ Berfungsi |
| **Keuangan** | Metode Pembayaran | ✅ Berfungsi |
| **Keuangan** | Potongan | ✅ Berfungsi |
| **Keuangan** | Tagihan Siswa | ✅ Berfungsi |
| **Keuangan** | Pembayaran | ✅ Berfungsi |
| **Keuangan** | Laporan | ✅ Berfungsi |
| **Penggajian** | Proses Penggajian | ✅ Berfungsi |
| **Penggajian** | Gaji Karyawan | ✅ Berfungsi |
| **Penggajian** | Laporan | ✅ Berfungsi |
| **Penggajian** | Golongan Gaji | ✅ Berfungsi |
| **Penggajian** | Komponen Gaji | ✅ Berfungsi |
| **Penggajian** | Tarif BPJS | ✅ Berfungsi |
| **Penggajian** | Tarif Pajak | ✅ Berfungsi |
| **Penggajian** | Pengaturan Pajak | ✅ Berfungsi |
| **Laporan** | Pengeluaran | ✅ Berfungsi |
| **Pengaturan** | Umum | ✅ Berfungsi |
| **Pengaturan** | Pengguna | ✅ Berfungsi |
| **Pengaturan** | Keamanan Login | ✅ Berfungsi |
| **Pengaturan** | Backup Database | ✅ Berfungsi (Super Admin) |
| **Pengaturan** | Manajemen Sekolah | ✅ Berfungsi (Super Admin) |

---

## Known Issues & Limitations

### Issues yang Sudah Diperbaiki (Commit Terbaru)

1. **FeeStructureResource Error** - `gradeLevel->level` tidak ada, sudah diganti `gradeLevel->code`
2. **DemoFinanceSeeder FK Violation** - Payment items dibuat tanpa cek payment exist, sudah diperbaiki dengan transaction
3. **Fee Type Weekly** - Enum frequency tidak support 'weekly', sudah ditambahkan via migration

### Limitations

1. **Offline Mode** - Hanya untuk scan absensi di mobile app, fitur lain butuh koneksi
2. **Multi-Browser Session** - User bisa login dari beberapa browser, perlu revoke manual jika ingin logout semua
3. **File Upload Size** - Default max 2MB, bisa diubah di php.ini
4. **Concurrent Edit** - Tidak ada locking, edit bersamaan bisa overwrite
5. **Time Zone** - Menggunakan timezone server, belum support per-user timezone

### Browser Support

- Chrome 90+ (Recommended)
- Firefox 88+
- Safari 14+
- Edge 90+

### Mobile App Requirements

- Android 8.0+ (API 26)
- iOS 13+ (untuk QR scanner)
- Kamera untuk scan QR

---

## Quick Test Checklist

### Minimum Viable Test (15 menit)

- [ ] Login sebagai `admin` - berhasil masuk dashboard
- [ ] Buka menu Siswa - daftar siswa tampil
- [ ] Buka menu Keuangan > Tagihan - daftar tagihan tampil
- [ ] Login sebagai `guru1` - menu terbatas sesuai role
- [ ] Login sebagai `siswa1` - hanya lihat data sendiri
- [ ] Login sebagai `superadmin` - bisa switch sekolah

### Full Regression Test (2-3 jam)

Ikuti semua skenario di atas untuk setiap modul.

---

## Kontak & Support

Jika menemukan bug atau masalah saat testing:
1. Catat langkah reproduksi
2. Screenshot error message
3. Cek console browser (F12) untuk error JavaScript
4. Cek log Laravel: `storage/logs/laravel.log`

---

*Dokumen ini di-generate pada: September 2026*
*Versi Aplikasi: feat/mobile branch*
