# User Manual Modul Pembayaran Siswa (Student Payment)

## SMS Absensi - Sistem Manajemen Sekolah

**Versi**: 1.0
**Terakhir Diperbarui**: September 2026

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
   - [Hak Akses dan Permission](#hak-akses-dan-permission)
2. [Akses Menu Keuangan](#2-akses-menu-keuangan)
3. [Konfigurasi Awal](#3-konfigurasi-awal)
   - 3.1 [Jenis Biaya (Fee Types)](#31-jenis-biaya-fee-types)
   - 3.2 [Struktur Biaya (Fee Structures)](#32-struktur-biaya-fee-structures)
   - 3.3 [Metode Pembayaran](#33-metode-pembayaran)
4. [Mengelola Tagihan Siswa](#4-mengelola-tagihan-siswa)
   - 4.1 [Membuat Tagihan Manual](#41-membuat-tagihan-manual)
   - 4.2 [Generate Tagihan Massal](#42-generate-tagihan-massal)
   - 4.3 [Melihat Riwayat Tagihan Siswa](#43-melihat-riwayat-tagihan-siswa)
   - 4.4 [Membebaskan Tagihan (Waive)](#44-membebaskan-tagihan-waive)
5. [Proses Pembayaran](#5-proses-pembayaran)
   - 5.1 [Membuat Pembayaran Baru](#51-membuat-pembayaran-baru)
   - 5.2 [Pembayaran Tunai](#52-pembayaran-tunai)
   - 5.3 [Pembayaran Transfer](#53-pembayaran-transfer)
   - 5.4 [Verifikasi Pembayaran Transfer](#54-verifikasi-pembayaran-transfer)
   - 5.5 [Pembatalan Pembayaran](#55-pembatalan-pembayaran)
6. [Cetak Kuitansi](#6-cetak-kuitansi)
7. [Dashboard dan Ringkasan](#7-dashboard-dan-ringkasan)
8. [FAQ & Troubleshooting](#8-faq--troubleshooting)
9. [Catatan untuk Administrator Sistem](#9-catatan-untuk-administrator-sistem)

---

## 1. Pendahuluan

Modul Pembayaran Siswa pada SMS Absensi adalah fitur untuk mengelola tagihan dan pembayaran siswa secara lengkap, termasuk:

- Konfigurasi jenis biaya dan struktur biaya per tingkat/jurusan
- Generate tagihan otomatis untuk semua siswa
- Pencatatan pembayaran tunai dan transfer
- Verifikasi bukti transfer
- Pencetakan kuitansi
- Laporan keuangan

### Hak Akses dan Permission

Modul keuangan menggunakan sistem permission berbasis role. Berikut daftar permission yang tersedia:

| Permission | Keterangan |
|------------|------------|
| `finance.fee-types.manage` | Kelola jenis biaya (SPP, UK, dll) |
| `finance.fee-structures.manage` | Kelola struktur biaya per tingkat |
| `finance.student-fees.manage` | Kelola tagihan siswa |
| `finance.payments.manage` | Membuat dan mengelola pembayaran |
| `finance.payments.verify` | Verifikasi pembayaran transfer |
| `finance.discounts.manage` | Kelola diskon/beasiswa |
| `finance.reports.view` | Melihat laporan keuangan |

**Pembagian Permission per Role:**

| Role | Permission | Keterangan |
|------|------------|------------|
| **Super Admin** | Semua | Akses penuh ke semua fitur |
| **Admin** | Semua | Akses penuh ke semua fitur keuangan |
| **Tata Usaha** | `fee-types`, `fee-structures`, `student-fees`, `payments`, `reports` | Operasional pembayaran harian |
| **Bendahara** | `payments.manage`, `payments.verify`, `reports.view` | Terima pembayaran & verifikasi |
| **Kepala Sekolah** | `reports.view` | Lihat laporan saja |
| **Orang Tua / Siswa** | - | Lihat tagihan & riwayat pembayaran sendiri |

**Siapa yang Bisa Melakukan Apa:**

| Aksi | Permission yang Dibutuhkan | Role Default |
|------|---------------------------|--------------|
| Tambah Jenis Biaya | `finance.fee-types.manage` | Admin, TU |
| Buat Struktur Biaya | `finance.fee-structures.manage` | Admin, TU |
| Generate Tagihan | `finance.student-fees.manage` | Admin, TU |
| Terima Pembayaran Tunai | `finance.payments.manage` | Admin, TU, Bendahara |
| **Verifikasi Transfer** | `finance.payments.verify` | **Admin, Bendahara** |
| Batalkan Pembayaran | `finance.payments.manage` | Admin, TU, Bendahara |
| Cetak Kuitansi | `finance.payments.manage` | Admin, TU, Bendahara |
| Lihat Laporan | `finance.reports.view` | Admin, TU, Bendahara, Kepsek |

---

## 2. Akses Menu Keuangan

Untuk mengakses modul keuangan:

1. Login ke aplikasi SMS Absensi
2. Pada sidebar, klik menu **Keuangan**
3. Pilih submenu yang diinginkan

![Menu Keuangan](images/finance/menu-finance.png)
*Gambar 2.1: Menu Keuangan pada sidebar*

### Daftar Submenu

| Submenu | URL | Fungsi |
|---------|-----|--------|
| Jenis Biaya | `/finance/fee-types` | Master data jenis biaya |
| Struktur Biaya | `/finance/fee-structures` | Biaya per tingkat/jurusan |
| Tagihan Siswa | `/finance/fees` | Kelola tagihan siswa |
| Pembayaran | `/finance/payments` | Daftar transaksi pembayaran |
| Metode Pembayaran | `/finance/payment-methods` | Konfigurasi metode pembayaran |
| Diskon/Beasiswa | `/finance/discounts` | Kelola potongan biaya |
| Laporan | `/finance/reports` | Laporan keuangan |

---

## 3. Konfigurasi Awal

Sebelum dapat memproses pembayaran, lakukan konfigurasi berikut (hanya perlu sekali di awal atau saat ada perubahan kebijakan).

### 3.1 Jenis Biaya (Fee Types)

Jenis biaya adalah kategori pembayaran yang harus dibayar siswa.

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Jenis Biaya**

![Halaman Jenis Biaya](images/finance/fee-types-list.png)
*Gambar 3.1: Daftar Jenis Biaya*

**Menambah Jenis Biaya:**

1. Klik tombol **+ Tambah Jenis Biaya**
2. Isi form:
   - **Kode**: Kode unik jenis biaya (contoh: `SPP`, `UK`, `OSIS`)
   - **Nama**: Nama jenis biaya (contoh: `Sumbangan Pembinaan Pendidikan`)
   - **Deskripsi**: Penjelasan singkat (opsional)
   - **Frekuensi**: Pilih frekuensi pembayaran
   - **Wajib**: Centang jika wajib untuk semua siswa
   - **Status**: Aktif/Tidak Aktif
3. Klik **Simpan**

![Form Jenis Biaya](images/finance/fee-types-form.png)
*Gambar 3.2: Form Tambah Jenis Biaya*

**Frekuensi Pembayaran:**

| Frekuensi | Keterangan | Contoh |
|-----------|------------|--------|
| `once` | Sekali bayar | Uang gedung, seragam |
| `monthly` | Setiap bulan | SPP |
| `semester` | Per semester | Ujian semester |
| `yearly` | Per tahun | OSIS, Pramuka |

**Contoh Jenis Biaya:**

| Kode | Nama | Frekuensi | Wajib |
|------|------|-----------|-------|
| SPP | Sumbangan Pembinaan Pendidikan | monthly | Ya |
| UK | Uang Kegiatan | monthly | Ya |
| UG | Uang Gedung | once | Ya |
| OSIS | Iuran OSIS | yearly | Ya |
| EKSKUL | Ekstrakurikuler | semester | Tidak |

---

### 3.2 Struktur Biaya (Fee Structures)

Struktur biaya menentukan nominal pembayaran per jenis biaya, per tingkat kelas, dan per jurusan (jika ada).

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Struktur Biaya**

![Halaman Struktur Biaya](images/finance/fee-structures-list.png)
*Gambar 3.3: Daftar Struktur Biaya*

**Menambah Struktur Biaya:**

1. Klik tombol **+ Tambah Struktur Biaya**
2. Isi form:
   - **Tahun Ajaran**: Pilih tahun ajaran
   - **Jenis Biaya**: Pilih jenis biaya
   - **Tingkat**: Pilih tingkat kelas (X, XI, XII)
   - **Jurusan**: Pilih jurusan (opsional, untuk biaya berbeda per jurusan)
   - **Nominal**: Jumlah yang harus dibayar
   - **Diskon Bawaan**: Potongan default (opsional)
   - **Tanggal Jatuh Tempo**: Tanggal atau hari jatuh tempo
   - **Status**: Aktif/Tidak Aktif
3. Klik **Simpan**

![Form Struktur Biaya](images/finance/fee-structures-form.png)
*Gambar 3.4: Form Tambah Struktur Biaya*

**Bulk Create (Buat Sekaligus):**

Untuk membuat struktur biaya sekaligus untuk semua jurusan dalam satu tingkat:

1. Klik **Bulk Create**
2. Pilih Tahun Ajaran, Jenis Biaya, dan Tingkat
3. Sistem akan menampilkan form untuk setiap jurusan
4. Isi nominal untuk masing-masing jurusan
5. Klik **Simpan Semua**

**Contoh Struktur Biaya:**

| Tahun Ajaran | Jenis Biaya | Tingkat | Jurusan | Nominal |
|--------------|-------------|---------|---------|---------|
| 2025/2026 | SPP | X | Semua | Rp 500.000 |
| 2025/2026 | SPP | XI | IPA | Rp 550.000 |
| 2025/2026 | SPP | XI | IPS | Rp 500.000 |
| 2025/2026 | UK | X | Semua | Rp 100.000 |
| 2025/2026 | Uang Gedung | X | Semua | Rp 2.000.000 |

---

### 3.3 Metode Pembayaran

Konfigurasi cara pembayaran yang diterima sekolah.

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Metode Pembayaran**

![Halaman Metode Pembayaran](images/finance/payment-methods-list.png)
*Gambar 3.5: Daftar Metode Pembayaran*

**Menambah Metode Pembayaran:**

1. Klik tombol **+ Tambah Metode**
2. Isi form:
   - **Kode**: Kode unik (contoh: `CASH`, `BCA`, `MANDIRI`)
   - **Nama**: Nama metode (contoh: `Tunai`, `Transfer BCA`)
   - **Tipe**: Pilih tipe pembayaran
   - **Provider**: Nama penyedia (untuk transfer/VA)
   - **Biaya Admin**: Biaya tambahan (jika ada)
   - **Konfigurasi**: Nomor rekening, dll (JSON)
   - **Status**: Aktif/Tidak Aktif
3. Klik **Simpan**

![Form Metode Pembayaran](images/finance/payment-methods-form.png)
*Gambar 3.6: Form Tambah Metode Pembayaran*

**Tipe Metode Pembayaran:**

| Tipe | Keterangan | Perlu Verifikasi? |
|------|------------|-------------------|
| `cash` | Pembayaran tunai di sekolah | Tidak |
| `bank_transfer` | Transfer ke rekening sekolah | **Ya** |
| `virtual_account` | Virtual account bank | **Ya** |
| `e_wallet` | E-wallet (GoPay, OVO, dll) | **Ya** |
| `credit_card` | Kartu kredit | **Ya** |
| `other` | Lainnya | Tergantung |

**Contoh Metode Pembayaran:**

| Kode | Nama | Tipe | Biaya Admin |
|------|------|------|-------------|
| CASH | Tunai | cash | Rp 0 |
| BCA | Transfer BCA | bank_transfer | Rp 2.500 |
| MANDIRI | Transfer Mandiri | bank_transfer | Rp 2.500 |
| VA-BCA | Virtual Account BCA | virtual_account | Rp 3.500 |
| GOPAY | GoPay | e_wallet | Rp 1.500 |

---

## 4. Mengelola Tagihan Siswa

### 4.1 Membuat Tagihan Manual

Untuk membuat tagihan individual untuk siswa tertentu:

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Tagihan Siswa**
2. Klik tombol **+ Tambah Tagihan**

![Form Tagihan Manual](images/finance/student-fee-form.png)
*Gambar 4.1: Form Tambah Tagihan Manual*

**Langkah-langkah:**

1. Pilih **Siswa** dari daftar
2. Pilih **Struktur Biaya** yang sesuai
3. Pilih **Tahun Ajaran**
4. Isi **Bulan** dan **Tahun** periode tagihan (untuk biaya bulanan)
5. **Nominal** akan terisi otomatis dari struktur biaya
6. Isi **Diskon** jika ada potongan khusus
7. Pilih **Tanggal Jatuh Tempo**
8. Tambahkan **Catatan** jika perlu
9. Klik **Simpan**

---

### 4.2 Generate Tagihan Massal

Untuk membuat tagihan sekaligus untuk banyak siswa:

1. Buka menu **Keuangan** > **Tagihan Siswa**
2. Klik tombol **Generate Tagihan**

![Form Generate Tagihan](images/finance/generate-fees.png)
*Gambar 4.2: Form Generate Tagihan Massal*

**Langkah-langkah:**

1. Pilih **Tahun Ajaran**
2. Pilih **Tingkat** (opsional, kosongkan untuk semua tingkat)
3. Pilih **Kelas** (opsional, untuk kelas spesifik)
4. Pilih **Jenis Biaya** (opsional, untuk jenis biaya tertentu)
5. Pilih **Bulan** dan **Tahun** periode tagihan
6. Pilih **Tanggal Jatuh Tempo**
7. Centang **Terapkan Diskon** jika ingin otomatis menerapkan diskon/beasiswa yang sudah disetujui
8. Klik **Generate**

**Hasil Generate:**

| Informasi | Keterangan |
|-----------|------------|
| Tagihan Dibuat | Jumlah tagihan yang berhasil dibuat |
| Dilewati | Jumlah yang dilewati (sudah ada atau tidak sesuai kriteria) |

> **Catatan:** Sistem otomatis melewati tagihan yang sudah ada untuk menghindari duplikasi.

---

### 4.3 Melihat Riwayat Tagihan Siswa

Untuk melihat semua tagihan seorang siswa:

1. Buka menu **Keuangan** > **Tagihan Siswa**
2. Cari siswa berdasarkan NIS atau nama
3. Klik **Detail** pada baris siswa

Atau:

1. Buka profil siswa
2. Klik tab **Tagihan**

![Riwayat Tagihan Siswa](images/finance/student-fee-history.png)
*Gambar 4.3: Riwayat Tagihan Siswa*

**Status Tagihan:**

| Status | Warna | Keterangan |
|--------|-------|------------|
| `unpaid` | Merah | Belum dibayar |
| `partial` | Kuning | Dibayar sebagian |
| `paid` | Hijau | Lunas |
| `overdue` | Merah Tua | Lewat jatuh tempo |
| `waived` | Abu-abu | Dibebaskan |

---

### 4.4 Membebaskan Tagihan (Waive)

Untuk membebaskan/menghapuskan tagihan siswa:

1. Buka detail tagihan
2. Klik tombol **Bebaskan Tagihan**
3. Isi **Alasan** pembebasan (wajib)
4. Klik **Konfirmasi**

![Bebaskan Tagihan](images/finance/waive-fee.png)
*Gambar 4.4: Form Pembebasan Tagihan*

> **Perhatian:** Tagihan yang sudah lunas tidak dapat dibebaskan. Pembebasan dicatat dalam audit log untuk akuntabilitas.

---

## 5. Proses Pembayaran

### 5.1 Membuat Pembayaran Baru

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Pembayaran**
2. Klik tombol **+ Terima Pembayaran**

![Form Pembayaran Baru](images/finance/payment-form.png)
*Gambar 5.1: Form Terima Pembayaran*

**Langkah-langkah:**

1. Pilih **Siswa** dari daftar
2. Sistem akan menampilkan **tagihan yang belum lunas**
3. Centang tagihan yang akan dibayar
4. Isi **nominal** pembayaran untuk setiap tagihan
   - Bisa bayar penuh atau sebagian
   - Nominal tidak boleh melebihi sisa tagihan
5. Pilih **Metode Pembayaran**
6. Sistem menampilkan **biaya admin** (jika ada)
7. Review **Total Pembayaran** (tagihan + biaya admin)
8. Tambahkan **Catatan** jika perlu
9. Klik **Buat Pembayaran**

**Status Pembayaran Baru:**

| Metode | Status Awal | Langkah Selanjutnya |
|--------|-------------|---------------------|
| Tunai | `pending` | Klik "Selesaikan" untuk mencatat |
| Transfer | `pending` | Upload bukti transfer |
| VA/E-wallet | `pending` | Upload bukti atau tunggu notifikasi |

---

### 5.2 Pembayaran Tunai

Untuk mencatat pembayaran tunai yang sudah diterima:

1. Buat pembayaran baru dengan metode **Tunai**
2. Pada halaman pembayaran, klik **Selesaikan Pembayaran**
3. Isi **ID Transaksi** (opsional, untuk catatan internal)
4. Tambahkan **Catatan** jika perlu
5. Klik **Konfirmasi**

![Selesaikan Pembayaran Tunai](images/finance/complete-cash-payment.png)
*Gambar 5.2: Form Selesaikan Pembayaran Tunai*

**Setelah dikonfirmasi:**
- Status pembayaran berubah ke `completed`
- Status tagihan otomatis diperbarui (`paid` atau `partial`)
- Kuitansi siap dicetak

---

### 5.3 Pembayaran Transfer

Untuk pembayaran via transfer bank:

**Langkah Orang Tua/Siswa:**

1. Buat pembayaran baru dengan metode **Transfer**
2. Lakukan transfer ke rekening sekolah
3. Pada halaman pembayaran, klik **Upload Bukti Transfer**
4. Pilih file gambar bukti transfer (max 5MB)
5. Isi **ID Transaksi/Referensi** dari bank
6. Klik **Upload**

![Upload Bukti Transfer](images/finance/upload-proof.png)
*Gambar 5.3: Form Upload Bukti Transfer*

**Setelah upload:**
- Status pembayaran berubah ke `processing`
- Menunggu verifikasi dari admin/bendahara

---

### 5.4 Verifikasi Pembayaran Transfer

**Permission:** `finance.payments.verify`

Untuk memverifikasi pembayaran transfer:

1. Buka menu **Keuangan** > **Pembayaran**
2. Filter status **Perlu Verifikasi**
3. Klik pada pembayaran yang akan diverifikasi
4. Periksa **bukti transfer** yang diupload
5. Cocokkan dengan mutasi rekening sekolah

![Verifikasi Pembayaran](images/finance/verify-payment.png)
*Gambar 5.4: Halaman Verifikasi Pembayaran*

**Aksi Verifikasi:**

| Tombol | Keterangan |
|--------|------------|
| **Setujui** | Transfer valid, pembayaran dikonfirmasi |
| **Tolak** | Transfer tidak valid, pembayaran gagal |

**Jika Setujui:**
- Status pembayaran berubah ke `completed`
- Tagihan otomatis diperbarui
- Kuitansi siap dicetak

**Jika Tolak:**
- Status pembayaran berubah ke `failed`
- Wajib isi alasan penolakan
- Orang tua/siswa perlu mengajukan pembayaran ulang

---

### 5.5 Pembatalan Pembayaran

Untuk membatalkan pembayaran yang belum selesai:

1. Buka detail pembayaran
2. Klik tombol **Batalkan Pembayaran**
3. Isi **Alasan** pembatalan
4. Klik **Konfirmasi**

![Batalkan Pembayaran](images/finance/cancel-payment.png)
*Gambar 5.5: Form Pembatalan Pembayaran*

**Aturan Pembatalan:**

| Status | Bisa Dibatalkan? |
|--------|-----------------|
| `pending` | Ya |
| `processing` | Ya |
| `completed` | **Tidak** |
| `failed` | Tidak perlu |
| `cancelled` | Sudah batal |

> **Catatan:** Pembayaran yang sudah `completed` tidak dapat dibatalkan. Untuk koreksi, gunakan fitur refund atau buat jurnal penyesuaian.

---

## 6. Cetak Kuitansi

Untuk mencetak kuitansi pembayaran yang sudah selesai:

1. Buka detail pembayaran dengan status `completed`
2. Klik tombol **Cetak Kuitansi** atau **Download PDF**

![Cetak Kuitansi](images/finance/print-receipt.png)
*Gambar 6.1: Preview Kuitansi Pembayaran*

**Format Kuitansi:**

```
╔══════════════════════════════════════════════════════════════╗
║                       KUITANSI PEMBAYARAN                     ║
║                        [NAMA SEKOLAH]                         ║
║                    No: INV-2026-00001234                      ║
╠══════════════════════════════════════════════════════════════╣
║ Tanggal   : 15 September 2026, 10:30                         ║
║                                                               ║
║ Data Siswa:                                                   ║
║   NIS     : 2024001                                           ║
║   Nama    : Ahmad Sudrajat                                    ║
║   Kelas   : X IPA 1                                           ║
╠══════════════════════════════════════════════════════════════╣
║ RINCIAN PEMBAYARAN                                            ║
╠───────────────────────────────────────────────────────────────╣
║ SPP - Sep 2026                           Rp    500.000        ║
║ UK - Sep 2026                            Rp    100.000        ║
╠───────────────────────────────────────────────────────────────╣
║ Subtotal                                 Rp    600.000        ║
║ Biaya Admin                              Rp      2.500        ║
╠═══════════════════════════════════════════════════════════════╣
║ TOTAL PEMBAYARAN                         Rp    602.500        ║
╠═══════════════════════════════════════════════════════════════╣
║ Metode: Transfer BCA                                          ║
║ Ref   : 20260915103012345                                     ║
║                                                               ║
║ Diterima oleh: Siti Bendahara                                 ║
╚═══════════════════════════════════════════════════════════════╝
```

**Cetak Massal:**

1. Pada daftar pembayaran, centang pembayaran yang ingin dicetak
2. Klik **Cetak Terpilih**

---

## 7. Dashboard dan Ringkasan

### Ringkasan Pembayaran

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Pembayaran**
2. Lihat kartu ringkasan di bagian atas

![Dashboard Pembayaran](images/finance/payment-summary.png)
*Gambar 7.1: Dashboard Ringkasan Pembayaran*

**Informasi yang Ditampilkan:**

| Kartu | Keterangan |
|-------|------------|
| Total Diterima | Total pembayaran yang sudah selesai |
| Menunggu Proses | Total pembayaran pending/processing |
| Biaya Admin | Total biaya admin yang terkumpul |
| Perlu Verifikasi | Jumlah pembayaran yang perlu diverifikasi |

### Ringkasan Tagihan

**Cara Mengakses:**
1. Buka menu **Keuangan** > **Tagihan Siswa**
2. Lihat kartu ringkasan di bagian atas

![Dashboard Tagihan](images/finance/fee-summary.png)
*Gambar 7.2: Dashboard Ringkasan Tagihan*

**Informasi yang Ditampilkan:**

| Kartu | Keterangan |
|-------|------------|
| Total Tagihan | Total semua tagihan yang dibuat |
| Total Terbayar | Total yang sudah dibayar |
| Sisa Tagihan | Total yang belum terbayar |
| Tagihan Lunas | Jumlah tagihan berstatus `paid` |
| Belum Bayar | Jumlah tagihan berstatus `unpaid` |
| Sebagian | Jumlah tagihan berstatus `partial` |
| Lewat Tempo | Jumlah tagihan berstatus `overdue` |

**Filter:**

- **Tahun Ajaran**: Filter per tahun ajaran
- **Bulan/Tahun**: Filter per periode
- **Kelas**: Filter per kelas

---

## 8. FAQ & Troubleshooting

### Q: Kenapa generate tagihan tidak membuat tagihan?

**A:** Pastikan:
- Struktur biaya sudah dibuat dan aktif untuk tahun ajaran tersebut
- Ada siswa aktif di kelas yang sesuai dengan struktur biaya
- Tagihan untuk periode tersebut belum pernah dibuat (tidak duplikat)
- Tingkat kelas siswa cocok dengan struktur biaya

### Q: Kenapa nominal tagihan tidak sesuai dengan struktur biaya?

**A:** Kemungkinan ada diskon yang diterapkan otomatis. Cek:
- Diskon bawaan di struktur biaya
- Diskon/beasiswa yang sudah disetujui untuk siswa tersebut
- Opsi "Terapkan Diskon" saat generate

### Q: Kenapa tombol "Verifikasi" tidak muncul?

**A:** Tombol verifikasi hanya muncul jika:
1. Status pembayaran adalah `processing`
2. User memiliki permission `finance.payments.verify`

Role yang memiliki permission ini:
- Super Admin
- Admin
- Bendahara

### Q: Bagaimana jika salah input nominal pembayaran?

**A:** Tergantung status pembayaran:
- **Pending/Processing**: Batalkan pembayaran, buat ulang
- **Completed**: Tidak bisa dibatalkan, buat jurnal penyesuaian atau refund

### Q: Bagaimana menangani pembayaran cicilan?

**A:** Sistem mendukung pembayaran sebagian (partial):
1. Saat membuat pembayaran, isi nominal kurang dari sisa tagihan
2. Status tagihan otomatis menjadi `partial`
3. Pembayaran berikutnya akan mengurangi sisa tagihan

### Q: Bagaimana jika bukti transfer tidak jelas?

**A:** Admin/Bendahara bisa:
1. Tolak pembayaran dengan alasan "Bukti tidak jelas"
2. Minta siswa/orang tua upload ulang bukti yang lebih jelas
3. Buat pembayaran baru

### Q: Kenapa saya tidak bisa menghapus tagihan?

**A:** Tagihan tidak bisa dihapus jika sudah ada pembayaran (meski sebagian). Alternatif:
- Gunakan fitur **Bebaskan Tagihan** untuk menghapuskan sisa tagihan
- Batalkan pembayaran terlebih dahulu (jika masih pending)

### Q: Bagaimana melihat laporan keuangan bulanan?

**A:** Buka menu **Laporan** > **Pengeluaran** (`/reports/expense`):
- Pilih tahun dan bulan
- Lihat pemasukan SPP vs pengeluaran gaji
- Export ke CSV untuk analisis lebih lanjut

---

## 9. Catatan untuk Administrator Sistem

### Sinkronisasi Permission

Jika baru mengupdate sistem dan permission keuangan belum ada, jalankan:

```bash
# Update permission untuk semua role
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force

# Reset cache permission
php artisan permission:cache-reset
```

### Menambah Permission ke Role Tertentu

```bash
php artisan tinker

# Tambah permission ke role bendahara
$role = \Spatie\Permission\Models\Role::findByName('bendahara');
$role->givePermissionTo([
    'finance.payments.manage',
    'finance.payments.verify',
    'finance.reports.view'
]);

# Reset cache
\Artisan::call('permission:cache-reset');
```

### API Endpoint Utama

| Endpoint | Method | Permission | Fungsi |
|----------|--------|------------|--------|
| `/api/v1/finance/fee-types` | GET/POST | `fee-types.manage` | Master jenis biaya |
| `/api/v1/finance/fee-structures` | GET/POST | `fee-structures.manage` | Struktur biaya |
| `/api/v1/finance/fee-structures/bulk` | POST | `fee-structures.manage` | Bulk create struktur |
| `/api/v1/finance/fees` | GET/POST | `student-fees.manage` | Tagihan siswa |
| `/api/v1/finance/fees/generate` | POST | `student-fees.manage` | Generate massal |
| `/api/v1/finance/fees/summary` | GET | `student-fees.manage` | Ringkasan tagihan |
| `/api/v1/finance/fees/{id}/waive` | POST | `student-fees.manage` | Bebaskan tagihan |
| `/api/v1/finance/payments` | GET/POST | `payments.manage` | Daftar pembayaran |
| `/api/v1/finance/payments/summary` | GET | `payments.manage` | Ringkasan pembayaran |
| `/api/v1/finance/payments/{id}/complete` | POST | `payments.manage` | Selesaikan tunai |
| `/api/v1/finance/payments/{id}/upload-proof` | POST | `payments.manage` | Upload bukti |
| `/api/v1/finance/payments/{id}/verify` | POST | `payments.verify` | Verifikasi transfer |
| `/api/v1/finance/payments/{id}/cancel` | POST | `payments.manage` | Batalkan |
| `/api/v1/finance/payments/{id}/receipt` | GET | `payments.manage` | Data kuitansi |
| `/api/v1/finance/payment-methods` | GET/POST | `payments.manage` | Metode pembayaran |

### Database Tables

| Tabel | Keterangan |
|-------|------------|
| `fee_types` | Master jenis biaya |
| `fee_structures` | Struktur biaya per tingkat/jurusan |
| `student_fees` | Tagihan per siswa |
| `payments` | Transaksi pembayaran |
| `payment_items` | Detail item per pembayaran |
| `payment_methods` | Master metode pembayaran |
| `discounts` | Master diskon/beasiswa |
| `student_discounts` | Diskon yang diberikan ke siswa |

---

## Lampiran

### A. Alur Kerja Lengkap

```
┌─────────────────────────────────────────────────────────────────┐
│                    KONFIGURASI (Sekali)                         │
├─────────────────────────────────────────────────────────────────┤
│  Jenis Biaya → Struktur Biaya → Metode Pembayaran → Diskon     │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 GENERATE TAGIHAN (Setiap Bulan)                 │
├─────────────────────────────────────────────────────────────────┤
│  Pilih Periode → Pilih Tingkat/Kelas → Generate → Review       │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 PROSES PEMBAYARAN                                │
├─────────────────────────────────────────────────────────────────┤
│  Tunai: Terima → Selesaikan → Cetak Kuitansi                    │
│  Transfer: Terima → Upload Bukti → Verifikasi → Cetak Kuitansi  │
└─────────────────────────────────────────────────────────────────┘
```

### B. Status Flow

**Tagihan (Student Fee):**
```
unpaid ──────────────────────────────────────► paid
   │                                             ▲
   └──► partial ─────────────────────────────────┘
   │
   └──► overdue (otomatis jika lewat due_date)
   │
   └──► waived (dibebaskan admin)
```

**Pembayaran (Payment):**
```
pending ──────► completed (tunai selesai)
   │
   └──► processing ──────► completed (verifikasi OK)
           │
           └──► failed (verifikasi ditolak)
   │
   └──► cancelled (dibatalkan)
```

---

*Dokumen ini dibuat untuk SMS Absensi - Sistem Manajemen Sekolah*
*Versi 1.0 - September 2026*
