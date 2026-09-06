# User Manual: Modul Absensi

Panduan lengkap penggunaan modul absensi untuk siswa, guru, dan staf.

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Master Data](#2-master-data)
   - [2.1 Pengaturan Absensi](#21-pengaturan-absensi)
   - [2.2 Hari Libur](#22-hari-libur)
   - [2.3 QR Code](#23-qr-code)
   - [2.4 Perangkat Scanner](#24-perangkat-scanner)
3. [Transaksi Absensi](#3-transaksi-absensi)
   - [3.1 Absensi Siswa](#31-absensi-siswa)
   - [3.2 Absensi Guru](#32-absensi-guru)
   - [3.3 Absensi Saya](#33-absensi-saya)
   - [3.4 Permohonan Izin](#34-permohonan-izin)
4. [Laporan](#4-laporan)
5. [Notifikasi](#5-notifikasi)
6. [FAQ & Troubleshooting](#6-faq--troubleshooting)

---

## 1. Pendahuluan

### 1.1 Tentang Modul Absensi

Modul absensi SMS App menyediakan fitur lengkap untuk mencatat kehadiran:
- **Siswa** - Absensi harian per kelas
- **Guru** - Absensi dengan jam masuk/pulang
- **Staf** - Absensi karyawan non-guru

### 1.2 Metode Absensi

| Metode | Deskripsi |
|--------|-----------|
| **QR Code** | Scan QR Code menggunakan aplikasi scanner |
| **RFID** | Tap kartu RFID di mesin absensi |
| **Manual** | Input manual oleh admin/wali kelas |

### 1.3 Status Kehadiran

| Status | Kode | Warna | Keterangan |
|--------|------|-------|------------|
| Hadir | `present` | Hijau | Siswa/guru hadir tepat waktu |
| Terlambat | `late` | Kuning | Hadir melebihi batas toleransi |
| Sakit | `sick` | Biru | Tidak hadir karena sakit |
| Izin | `permitted` | Ungu | Tidak hadir dengan izin |
| Alfa | `alpha` | Merah | Tidak hadir tanpa keterangan |
| Belum Scan | - | Abu-abu | Belum melakukan absensi hari ini |

### 1.4 Hak Akses

| Role | Akses |
|------|-------|
| **Super Admin** | Semua fitur |
| **Admin** | Semua fitur dalam tenant |
| **Kepala Sekolah** | Lihat semua, edit terbatas |
| **Tata Usaha** | Kelola absensi harian |
| **Wali Kelas** | Kelola absensi kelasnya |
| **Guru** | Lihat absensi sendiri |
| **Siswa** | Lihat absensi sendiri |
| **Orang Tua** | Lihat absensi anak |

---

## 2. Master Data

### 2.1 Pengaturan Absensi

**Menu:** Absensi > Pengaturan

#### 2.1.1 Jam Absensi

Atur waktu check-in dan check-out:

```
Jam Masuk
├── Mulai    : 06:00  (siswa bisa scan mulai jam ini)
├── Batas    : 07:30  (setelah ini dianggap terlambat)
└── Toleransi: 15 menit (grace period sebelum terlambat)

Jam Pulang
├── Mulai    : 14:00  (siswa bisa scan pulang mulai jam ini)
└── Batas    : 16:00  (batas akhir scan pulang)
```

**Langkah Konfigurasi:**

1. Buka menu **Absensi > Pengaturan**
2. Pada tab **Jam Absensi**, atur:
   - Jam mulai absensi masuk
   - Jam batas absensi masuk (setelah ini = terlambat)
   - Toleransi keterlambatan (dalam menit)
   - Jam mulai absensi pulang
   - Jam batas absensi pulang
3. Klik **Simpan**

**Screenshot:** *Halaman pengaturan jam absensi*

![Pengaturan Jam](images/attendance/settings-time.png)

#### 2.1.2 Hari Kerja

Tentukan hari kerja dalam seminggu:

```
[x] Senin
[x] Selasa
[x] Rabu
[x] Kamis
[x] Jumat
[ ] Sabtu    (tidak centang = libur)
[ ] Minggu   (tidak centang = libur)
```

**Langkah:**
1. Pada tab **Hari Kerja**, centang hari yang aktif
2. Klik **Simpan**

#### 2.1.3 Lokasi Sekolah (GPS)

Untuk validasi lokasi saat scan:

```
Koordinat Sekolah
├── Latitude : -6.123456
├── Longitude: 106.789012
└── Radius   : 100 meter (jarak maksimal dari titik sekolah)

[x] Wajibkan validasi lokasi saat scan
```

**Langkah:**
1. Pada tab **Lokasi**, aktifkan "Wajibkan validasi lokasi"
2. Masukkan koordinat sekolah (bisa klik peta untuk pilih)
3. Atur radius toleransi (dalam meter)
4. Klik **Simpan**

---

### 2.2 Hari Libur

**Menu:** Absensi > Hari Libur

Kelola kalender hari libur agar tidak dihitung sebagai hari kerja.

#### 2.2.1 Menambah Hari Libur

1. Klik tombol **+ Tambah Libur**
2. Isi form:
   - **Tanggal**: Pilih tanggal libur
   - **Nama**: Nama hari libur (contoh: "Hari Kemerdekaan")
   - **Berulang Tahunan**: Centang jika libur tahunan
3. Klik **Simpan**

**Contoh:**
```
Tanggal         : 17 Agustus 2026
Nama            : Hari Kemerdekaan RI
Berulang Tahunan: [x] Ya
```

#### 2.2.2 Generate Libur Weekend

Untuk membuat libur Sabtu-Minggu otomatis:

1. Klik **Generate Weekend**
2. Pilih bulan dan tahun
3. Klik **Generate**
4. Sistem akan membuat entri libur untuk setiap Sabtu dan Minggu

#### 2.2.3 Hapus Libur Massal

1. Centang hari libur yang ingin dihapus
2. Klik **Hapus Terpilih**
3. Konfirmasi penghapusan

**Screenshot:** *Kalender hari libur*

![Hari Libur](images/attendance/holidays.png)

---

### 2.3 QR Code

**Menu:** Absensi > QR Code

Setiap siswa dan guru memiliki QR Code unik untuk absensi.

#### 2.3.1 Generate QR Code Siswa

**Per Siswa:**
1. Buka **Absensi > QR Code**
2. Pilih tab **Siswa**
3. Cari siswa berdasarkan nama atau NIS
4. Klik ikon QR pada baris siswa
5. QR Code akan ditampilkan
6. Klik **Download** untuk menyimpan gambar

**Per Kelas (Bulk):**
1. Pilih **Kelas** dari dropdown
2. Klik **Generate Semua**
3. Sistem akan membuat QR Code untuk semua siswa di kelas
4. Klik **Download PDF** untuk cetak massal

**Contoh QR Code Siswa:**
```
┌─────────────────┐
│  ▄▄▄▄▄ ▄▄▄ ▄▄▄  │
│  █   █ ▄▄▄ █ █  │
│  █▄▄▄█ █▄█ ▀▄▀  │
│  ▄▄▄▄▄ ▄ ▄ ▄▄▄  │
│  █   █ █▀█ █▄█  │
│  █▄▄▄█ ▀▀▀ ▀▀▀  │
└─────────────────┘
    Ahmad Rifai
    NIS: 2024001
    Kelas: X IPA 1
```

#### 2.3.2 Generate QR Code Guru

1. Pilih tab **Guru**
2. Cari guru berdasarkan nama atau NIP
3. Klik ikon QR untuk lihat
4. Klik **Download** untuk simpan

**Bulk Generate Guru:**
1. Klik **Generate Semua Guru**
2. Download PDF untuk cetak

#### 2.3.3 Regenerate QR Code

Jika QR Code hilang atau bocor:

1. Cari siswa/guru
2. Klik **Regenerate**
3. Konfirmasi regenerate
4. QR Code lama akan tidak valid
5. QR Code baru akan dibuat

**Screenshot:** *Halaman QR Code*

![QR Code](images/attendance/qr-codes.png)

---

### 2.4 Perangkat Scanner

**Menu:** Absensi > Perangkat

Monitor status perangkat scanner yang terhubung.

#### 2.4.1 Daftar Perangkat

Tabel menampilkan:

| Kolom | Deskripsi |
|-------|-----------|
| Nama Perangkat | Nama yang diberikan saat registrasi |
| Status | Online (hijau) / Offline (merah) |
| User Aktif | Operator yang login di perangkat |
| Last Heartbeat | Waktu terakhir perangkat melapor |
| App Version | Versi aplikasi scanner |
| Baterai | Level baterai (%) |
| Network | WiFi/Mobile + kualitas sinyal |
| Pending Sync | Jumlah scan yang belum tersinkron |

#### 2.4.2 Registrasi Perangkat Baru

1. Di aplikasi scanner, pilih **Scan QR Provisioning**
2. Di web admin, buka **Absensi > Perangkat**
3. Klik **+ Tambah Perangkat**
4. QR Code provisioning akan muncul
5. Scan QR dengan aplikasi scanner
6. Perangkat akan terdaftar otomatis

#### 2.4.3 Monitoring Real-time

```
┌─────────────────────────────────────────────────┐
│ Scanner Lobby                                    │
├─────────────────────────────────────────────────┤
│ Status      : 🟢 Online                         │
│ Operator    : Budi Santoso                      │
│ Heartbeat   : 2 detik yang lalu                 │
│ App Version : 2.1.0                             │
│ OS          : Android 13                        │
│ Baterai     : 85% 🔋 (charging)                 │
│ Network     : WiFi "Sekolah_5G" (45ms)          │
│ Pending Sync: 0                                 │
│ Last Scan   : 07:15:23 - Ahmad Rifai (Masuk)    │
└─────────────────────────────────────────────────┘
```

**Screenshot:** *Monitor perangkat scanner*

![Device Monitor](images/attendance/device-monitor.png)

---

## 3. Transaksi Absensi

### 3.1 Absensi Siswa

**Menu:** Absensi > Siswa

#### 3.1.1 Melihat Absensi Harian

1. Pilih **Kelas** dari dropdown
2. Pilih **Tanggal** (default: hari ini)
3. Klik **Tampilkan**
4. Daftar siswa akan muncul dengan status masing-masing

**Tampilan:**
```
┌─────────────────────────────────────────────────────────────────┐
│ Absensi Siswa - X IPA 1 - 03 September 2026                     │
├─────────────────────────────────────────────────────────────────┤
│ Ringkasan: Hadir: 28 | Sakit: 1 | Izin: 1 | Alfa: 0 | Belum: 2 │
├─────┬─────────────────┬───────────┬──────────┬──────────────────┤
│ No  │ Nama            │ NIS       │ Status   │ Jam Masuk        │
├─────┼─────────────────┼───────────┼──────────┼──────────────────┤
│ 1   │ Ahmad Rifai     │ 2024001   │ 🟢 Hadir │ 07:15            │
│ 2   │ Budi Santoso    │ 2024002   │ 🟢 Hadir │ 07:20            │
│ 3   │ Citra Dewi      │ 2024003   │ 🔵 Sakit │ -                │
│ 4   │ Dian Pratama    │ 2024004   │ 🟡 Telat │ 07:45 (+15 mnt)  │
│ 5   │ Eka Putri       │ 2024005   │ ⚫ Belum │ -                │
│ ... │ ...             │ ...       │ ...      │ ...              │
└─────┴─────────────────┴───────────┴──────────┴──────────────────┘
```

#### 3.1.2 Input Absensi Manual

Untuk siswa yang belum scan atau perlu dikoreksi:

1. Klik dropdown **Status** pada baris siswa
2. Pilih status yang sesuai:
   - Hadir
   - Sakit
   - Izin
   - Alfa (tanpa keterangan)
3. Jika memilih "Hadir", isi jam masuk (opsional)
4. Tambahkan catatan jika perlu
5. Klik **Simpan**

#### 3.1.3 Absensi Massal

Untuk mengisi absensi semua siswa sekaligus:

1. Klik **Tandai Semua Hadir**
2. Semua siswa yang belum diisi akan diubah ke "Hadir"
3. Jam masuk diisi dengan waktu saat ini
4. Koreksi individual jika ada yang berbeda
5. Klik **Simpan Semua**

#### 3.1.4 Koreksi Absensi

Untuk mengubah absensi yang sudah tercatat:

1. Klik ikon **Edit** pada baris siswa
2. Dialog edit akan muncul
3. Ubah status, jam masuk/pulang, atau catatan
4. Masukkan alasan perubahan (wajib untuk audit)
5. Klik **Simpan**

**Contoh Koreksi:**
```
┌─────────────────────────────────────┐
│ Edit Absensi - Ahmad Rifai          │
├─────────────────────────────────────┤
│ Status Lama : Alfa                  │
│ Status Baru : [Sakit        ▼]      │
│ Jam Masuk   : [--:--]               │
│ Catatan     : [Surat dokter menyusul]│
│ Alasan Edit : [Koreksi data, ada   ]│
│               [surat dari ortu     ]│
├─────────────────────────────────────┤
│        [Batal]  [Simpan]            │
└─────────────────────────────────────┘
```

#### 3.1.5 Kirim Rekap Harian

Kirim notifikasi absensi ke orang tua/wali:

1. Pastikan semua absensi sudah lengkap
2. Klik **Kirim Rekap**
3. Pilih channel notifikasi (WhatsApp/Telegram/Email)
4. Konfirmasi pengiriman
5. Notifikasi akan dikirim ke semua wali siswa di kelas

**Contoh Pesan WhatsApp:**
```
📋 REKAP ABSENSI HARIAN
Sekolah: SMA Negeri 1
Tanggal: 03 September 2026
Kelas  : X IPA 1

Siswa  : Ahmad Rifai (2024001)
Status : ✅ Hadir
Masuk  : 07:15
Pulang : 14:30

Terima kasih.
```

**Screenshot:** *Halaman absensi siswa*

![Absensi Siswa](images/attendance/student-attendance.png)

---

### 3.2 Absensi Guru

**Menu:** Absensi > Guru

#### 3.2.1 Melihat Absensi Guru

1. Pilih **Tanggal**
2. Daftar guru akan muncul dengan status

**Tampilan:**
```
┌─────────────────────────────────────────────────────────────────────┐
│ Absensi Guru - 03 September 2026                                     │
├─────────────────────────────────────────────────────────────────────┤
│ Ringkasan: Hadir: 25 | Sakit: 1 | Izin: 0 | Dinas Luar: 2 | WFH: 0 │
├─────┬─────────────────┬───────────┬──────────┬─────────┬────────────┤
│ No  │ Nama            │ NIP       │ Status   │ Masuk   │ Pulang     │
├─────┼─────────────────┼───────────┼──────────┼─────────┼────────────┤
│ 1   │ Drs. Agus S.    │ 19700512  │ 🟢 Hadir │ 06:45   │ 14:15      │
│ 2   │ Sri Wahyuni, S.Pd│19850623  │ 🟢 Hadir │ 06:50   │ -          │
│ 3   │ Bambang K., M.Pd│ 19780315  │ 🟡 Telat │ 07:35   │ -          │
│ 4   │ Dewi Lestari    │ 19900801  │ 🔵 Sakit │ -       │ -          │
│ 5   │ Eko Prasetyo    │ 19880910  │ 🟣 Dinas │ -       │ -          │
│ ... │ ...             │ ...       │ ...      │ ...     │ ...        │
└─────┴─────────────────┴───────────┴──────────┴─────────┴────────────┘
```

#### 3.2.2 Status Khusus Guru

| Status | Deskripsi |
|--------|-----------|
| Hadir | Hadir di sekolah |
| Terlambat | Hadir melebihi batas waktu |
| Sakit | Tidak hadir karena sakit |
| Izin | Tidak hadir dengan izin |
| Dinas Luar | Tugas di luar sekolah |
| WFH | Bekerja dari rumah |
| Alfa | Tidak hadir tanpa keterangan |

#### 3.2.3 Input/Edit Absensi Guru

1. Klik dropdown **Status** atau ikon **Edit**
2. Pilih status
3. Isi jam masuk/pulang jika perlu
4. Tambahkan catatan
5. Klik **Simpan**

**Screenshot:** *Halaman absensi guru*

![Absensi Guru](images/attendance/teacher-attendance.png)

---

### 3.3 Absensi Saya

**Menu:** Absensi > Absensi Saya

Halaman self-service untuk melihat absensi sendiri.

#### 3.3.1 Status Hari Ini

```
┌─────────────────────────────────────┐
│         ABSENSI HARI INI            │
│         03 September 2026           │
├─────────────────────────────────────┤
│                                     │
│        ✅ HADIR                      │
│                                     │
│   Jam Masuk  : 06:45                │
│   Jam Pulang : -                    │
│                                     │
├─────────────────────────────────────┤
│  [Lihat QR Code]  [Lihat RFID]      │
└─────────────────────────────────────┘
```

#### 3.3.2 Lihat QR Code Sendiri

1. Klik **Lihat QR Code**
2. QR Code akan ditampilkan
3. Scan QR ini di perangkat scanner untuk absensi

#### 3.3.3 Riwayat Absensi

1. Pilih periode (bulan)
2. Lihat ringkasan:
   - Total hari hadir
   - Total sakit
   - Total izin
   - Total alfa
   - Total terlambat
3. Lihat detail per tanggal

**Contoh Ringkasan Bulan:**
```
Ringkasan Agustus 2026
──────────────────────
Hari Kerja    : 22 hari
Hadir         : 20 hari (90.9%)
Sakit         : 1 hari
Izin          : 1 hari
Alfa          : 0 hari
Terlambat     : 2 kali

Kalender:
Sen Sel Rab Kam Jum Sab Min
 -   1   2   3   4   5   6
     ✅  ✅  ✅  ✅  -   -
 7   8   9  10  11  12  13
 ✅  ✅  ✅  🔵  ✅  -   -
14  15  16  17  18  19  20
 ✅  ✅  🟡  🟢  ✅  -   -
21  22  23  24  25  26  27
 ✅  ✅  ✅  ✅  ✅  -   -
28  29  30  31
 ✅  ✅  ✅  ✅
```

**Screenshot:** *Halaman absensi saya*

![Absensi Saya](images/attendance/my-attendance.png)

---

### 3.4 Permohonan Izin

**Menu:** Absensi > Permohonan Izin

#### 3.4.1 Membuat Permohonan (Siswa/Guru)

1. Klik **+ Buat Permohonan**
2. Isi form:
   - **Tipe**: Sakit / Izin
   - **Tanggal Mulai**: Tanggal awal tidak hadir
   - **Tanggal Selesai**: Tanggal akhir tidak hadir
   - **Alasan**: Jelaskan alasan
   - **Bukti** (opsional): Upload surat dokter/izin
3. Klik **Kirim**

**Contoh Form:**
```
┌─────────────────────────────────────┐
│      PERMOHONAN IZIN BARU           │
├─────────────────────────────────────┤
│ Tipe         : [Sakit        ▼]     │
│ Tanggal Mulai: [04/09/2026   📅]    │
│ Tanggal Akhir: [05/09/2026   📅]    │
│ Alasan       :                      │
│ ┌─────────────────────────────────┐ │
│ │ Demam tinggi dan batuk, perlu   │ │
│ │ istirahat sesuai anjuran dokter │ │
│ └─────────────────────────────────┘ │
│ Bukti        : [📎 Upload File]     │
│               surat-dokter.pdf ✓    │
├─────────────────────────────────────┤
│         [Batal]  [Kirim]            │
└─────────────────────────────────────┘
```

#### 3.4.2 Status Permohonan

| Status | Warna | Keterangan |
|--------|-------|------------|
| Menunggu | Kuning | Belum diproses admin |
| Disetujui | Hijau | Izin diterima |
| Ditolak | Merah | Izin ditolak (lihat alasan) |

#### 3.4.3 Menyetujui/Menolak (Admin)

1. Buka daftar permohonan
2. Filter status "Menunggu"
3. Klik permohonan untuk lihat detail
4. Klik **Setujui** atau **Tolak**
5. Jika tolak, masukkan alasan penolakan
6. Klik **Konfirmasi**

**Contoh Detail Permohonan:**
```
┌─────────────────────────────────────────┐
│      DETAIL PERMOHONAN IZIN             │
├─────────────────────────────────────────┤
│ Pemohon  : Ahmad Rifai (2024001)        │
│ Kelas    : X IPA 1                      │
│ Tipe     : Sakit                        │
│ Periode  : 04 - 05 September 2026       │
│ Status   : 🟡 Menunggu                  │
│ Alasan   : Demam tinggi dan batuk,      │
│            perlu istirahat sesuai       │
│            anjuran dokter               │
│ Bukti    : [📎 surat-dokter.pdf]        │
│ Diajukan : 03 Sep 2026, 08:15           │
├─────────────────────────────────────────┤
│     [Tolak]           [Setujui]         │
└─────────────────────────────────────────┘
```

**Screenshot:** *Halaman permohonan izin*

![Permohonan Izin](images/attendance/leave-permissions.png)

---

## 4. Laporan

**Menu:** Absensi > Laporan

### 4.1 Laporan Bulanan

1. Pilih **Bulan** dan **Tahun**
2. Pilih **Tipe**: Siswa / Guru
3. Jika siswa, pilih **Kelas**
4. Klik **Tampilkan**

**Tampilan Laporan:**
```
┌─────────────────────────────────────────────────────────────────────┐
│              LAPORAN ABSENSI BULANAN                                 │
│              September 2026 - X IPA 1                                │
├─────────────────────────────────────────────────────────────────────┤
│ Hari Kerja: 22 hari | Rata-rata Kehadiran: 95.5%                    │
├─────┬─────────────────┬───────┬───────┬───────┬───────┬─────────────┤
│ No  │ Nama            │ Hadir │ Sakit │ Izin  │ Alfa  │ % Kehadiran │
├─────┼─────────────────┼───────┼───────┼───────┼───────┼─────────────┤
│ 1   │ Ahmad Rifai     │ 20    │ 1     │ 1     │ 0     │ 90.9%       │
│ 2   │ Budi Santoso    │ 22    │ 0     │ 0     │ 0     │ 100%        │
│ 3   │ Citra Dewi      │ 21    │ 1     │ 0     │ 0     │ 95.5%       │
│ ... │ ...             │ ...   │ ...   │ ...   │ ...   │ ...         │
├─────┴─────────────────┴───────┴───────┴───────┴───────┴─────────────┤
│                    [Export PDF]  [Export Excel]                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.2 Export Laporan

**Export PDF:**
1. Klik **Export PDF**
2. File PDF akan diunduh
3. Format siap cetak dengan header sekolah

**Export Excel:**
1. Klik **Export Excel**
2. File Excel akan diunduh
3. Data dapat diolah lebih lanjut

### 4.3 Trend Mingguan

Grafik kehadiran 7 hari terakhir:

```
Trend Kehadiran 7 Hari
100% ┤                    ╭─╮
 95% ┤        ╭──╮  ╭──╮  │ │
 90% ┤  ╭──╮  │  │  │  ╰──╯ │
 85% ┤  │  ╰──╯  ╰──╯       │
 80% ┼──┴─────────────────────
     Sen Sel Rab Kam Jum Sab Min
```

**Screenshot:** *Halaman laporan*

![Laporan](images/attendance/reports.png)

---

## 5. Notifikasi

### 5.1 Konfigurasi Notifikasi

**Menu:** Absensi > Pengaturan > Tab Notifikasi

#### 5.1.1 WhatsApp

Provider yang didukung:
- **Fonnte** - fonnte.com
- **Wablas** - wablas.com

Konfigurasi:
```
Provider   : [Fonnte        ▼]
API Key    : [••••••••••••••••]
Nomor Sender: [6281234567890  ]

[Test Kirim]
```

#### 5.1.2 Telegram

```
Bot Token  : [••••••••••••••••••••]
Chat ID    : [-100123456789       ]

[Test Kirim]  [Info Bot]
```

#### 5.1.3 Email (SMTP)

```
SMTP Host  : [smtp.gmail.com     ]
SMTP Port  : [587                ]
Username   : [sekolah@gmail.com  ]
Password   : [••••••••••••••••   ]
Enkripsi   : [TLS            ▼]

[Test Kirim]
```

### 5.2 Event Notifikasi

Pilih event yang akan memicu notifikasi:

```
[x] Check-in      : Kirim saat siswa scan masuk
[x] Check-out     : Kirim saat siswa scan pulang
[x] Terlambat     : Kirim jika siswa terlambat
[x] Tidak Hadir   : Kirim jika siswa tidak hadir sampai batas
[x] Izin Disetujui: Kirim saat izin disetujui admin
```

### 5.3 Contoh Pesan Notifikasi

**Check-in:**
```
✅ ABSENSI MASUK
Nama  : Ahmad Rifai
NIS   : 2024001
Kelas : X IPA 1
Jam   : 07:15
Status: Hadir Tepat Waktu
```

**Terlambat:**
```
⚠️ PEMBERITAHUAN KETERLAMBATAN
Nama  : Ahmad Rifai
NIS   : 2024001
Kelas : X IPA 1
Jam   : 07:45
Telat : 15 menit

Mohon pastikan anak Anda hadir tepat waktu.
```

**Tidak Hadir:**
```
❌ PEMBERITAHUAN KETIDAKHADIRAN
Nama  : Ahmad Rifai
NIS   : 2024001
Kelas : X IPA 1
Tanggal: 03 September 2026

Anak Anda tidak tercatat hadir hari ini.
Jika ada keperluan, mohon ajukan izin.
```

---

## 6. FAQ & Troubleshooting

### 6.1 FAQ

**Q: Bagaimana jika siswa lupa scan?**
A: Wali kelas atau admin dapat input manual melalui menu Absensi > Siswa.

**Q: Bagaimana jika QR Code hilang?**
A: Admin dapat generate ulang QR Code melalui menu Absensi > QR Code > Regenerate.

**Q: Apakah bisa absensi offline?**
A: Ya, aplikasi scanner mendukung mode offline. Data akan disinkronkan saat online.

**Q: Bagaimana cara mengajukan izin?**
A: Melalui menu Absensi > Permohonan Izin > Buat Permohonan.

**Q: Siapa yang bisa menyetujui izin?**
A: Admin, Tata Usaha, atau Kepala Sekolah.

### 6.2 Troubleshooting

**Masalah: QR Code tidak terbaca**
- Pastikan kamera scanner bersih
- Pastikan pencahayaan cukup
- Coba regenerate QR Code

**Masalah: Scan tidak tercatat**
- Cek koneksi internet perangkat scanner
- Cek status "Pending Sync" di monitor perangkat
- Tunggu sinkronisasi atau input manual

**Masalah: Notifikasi tidak terkirim**
- Cek konfigurasi API key/token
- Test kirim dari menu pengaturan
- Pastikan nomor/chat ID benar
- Cek kuota provider (Fonnte/Wablas)

**Masalah: Siswa tercatat terlambat padahal tidak**
- Cek pengaturan jam batas masuk
- Cek zona waktu server
- Koreksi manual jika perlu

---

## Lampiran

### A. Daftar API Endpoint

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/attendance/students` | GET | List absensi siswa |
| `/api/v1/attendance/students/daily` | POST | Absensi harian kelas |
| `/api/v1/attendance/students/bulk` | POST | Simpan massal |
| `/api/v1/attendance/teachers` | GET | List absensi guru |
| `/api/v1/attendance/teachers/daily` | POST | Absensi harian guru |
| `/api/v1/attendance/me/today` | GET | Absensi saya hari ini |
| `/api/v1/attendance/me/history` | POST | Riwayat absensi saya |
| `/api/v1/attendance/permissions` | GET/POST | Permohonan izin |
| `/api/v1/attendance/holidays` | GET/POST | Kelola hari libur |
| `/api/v1/attendance/settings` | GET/PUT | Pengaturan absensi |
| `/api/v1/scanner/scan` | POST | Proses scan QR/RFID |

### B. Hak Akses per Menu

| Menu | Admin | TU | Kepsek | Guru | Wali | Siswa | Ortu |
|------|-------|----|----|------|------|-------|------|
| Absensi Siswa | ✅ | ✅ | 👁 | ❌ | ✅* | ❌ | ❌ |
| Absensi Guru | ✅ | ✅ | 👁 | ❌ | ❌ | ❌ | ❌ |
| Absensi Saya | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Permohonan Izin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Hari Libur | ✅ | ✅ | 👁 | ❌ | ❌ | ❌ | ❌ |
| QR Code | ✅ | ✅ | 👁 | ❌ | ❌ | ❌ | ❌ |
| Pengaturan | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Laporan | ✅ | ✅ | ✅ | ❌ | ✅* | ❌ | ❌ |

*Keterangan:*
- ✅ = Akses penuh
- 👁 = Hanya lihat
- ✅* = Hanya kelas sendiri
- ❌ = Tidak bisa akses

---

*Dokumen ini dibuat pada: September 2026*
*Versi: 1.0*
