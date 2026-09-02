# User Manual Modul Penggajian (Payroll)

## SMS Absensi - Sistem Manajemen Sekolah

**Versi**: 1.0
**Terakhir Diperbarui**: September 2026

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
   - [Hak Akses dan Permission](#hak-akses-dan-permission)
2. [Akses Menu Penggajian](#2-akses-menu-penggajian)
3. [Konfigurasi Awal](#3-konfigurasi-awal)
   - 3.1 [Golongan Gaji](#31-golongan-gaji)
   - 3.2 [Komponen Gaji](#32-komponen-gaji)
   - 3.3 [Tarif BPJS](#33-tarif-bpjs)
   - 3.4 [Bracket Pajak PPh 21](#34-bracket-pajak-pph-21)
   - 3.5 [Pengaturan Pajak (PTKP)](#35-pengaturan-pajak-ptkp)
4. [Setup Gaji Karyawan](#4-setup-gaji-karyawan)
5. [Proses Penggajian Bulanan](#5-proses-penggajian-bulanan)
   - 5.1 [Membuat Periode Gaji](#51-membuat-periode-gaji)
   - 5.2 [Generate Slip Gaji](#52-generate-slip-gaji) ← **Termasuk integrasi kehadiran**
   - 5.3 [Review & Edit Slip](#53-review--edit-slip)
   - 5.4 [Approval & Finalisasi](#54-approval--finalisasi) ← **Alur approval berbasis permission**
6. [Cetak Slip Gaji](#6-cetak-slip-gaji)
7. [Laporan Penggajian](#7-laporan-penggajian)
   - 7.1 [Laporan Pengeluaran](#71-laporan-pengeluaran-expense-report)
8. [FAQ & Troubleshooting](#8-faq--troubleshooting)
9. [Catatan untuk Administrator Sistem](#catatan-untuk-administrator-sistem)

---

## 1. Pendahuluan

Modul Penggajian (Payroll) pada SMS Absensi adalah fitur untuk mengelola gaji guru dan staf sekolah secara lengkap, termasuk:

- Konfigurasi golongan dan komponen gaji
- Perhitungan otomatis BPJS dan PPh 21
- Workflow approval berlapis
- Pencetakan slip gaji
- Laporan penggajian

### Hak Akses dan Permission

Modul penggajian menggunakan sistem permission berbasis role. Berikut daftar permission yang tersedia:

| Permission | Keterangan |
|------------|------------|
| `payroll.view` | Melihat menu dan data penggajian |
| `payroll.manage` | Membuat/menghapus periode gaji, setup gaji karyawan |
| `payroll.process` | Generate slip, hitung ulang, ajukan persetujuan |
| `payroll.approve` | Menyetujui, menandai dibayar, finalisasi periode |
| `payroll.report` | Melihat laporan penggajian |

**Pembagian Permission per Role:**

| Role | Permission | Keterangan |
|------|------------|------------|
| **Super Admin** | Semua | Akses penuh ke semua fitur |
| **Admin** | `view`, `manage`, `process`, `approve`, `report` | Kelola penggajian lengkap |
| **Kepala Sekolah** | `view`, `approve`, `report` | Review dan approval gaji |
| **Tata Usaha** | `view`, `manage`, `process`, `report` | Proses penggajian harian |
| **Bendahara** | `view`, `report` | Lihat data untuk pembukuan |
| **Guru / Staf** | - | Hanya lihat slip gaji sendiri |

**Siapa yang Bisa Melakukan Apa:**

| Aksi | Permission yang Dibutuhkan | Role Default |
|------|---------------------------|--------------|
| Buat Periode | `payroll.manage` | Admin, TU |
| Generate Slip | `payroll.process` | Admin, TU |
| Generate + Kehadiran | `payroll.process` | Admin, TU |
| Hitung Ulang | `payroll.process` | Admin, TU |
| Hitung Kehadiran | `payroll.process` | Admin, TU |
| Ajukan Persetujuan | `payroll.process` | Admin, TU |
| **Setujui** | `payroll.approve` | **Admin, Kepala Sekolah** |
| **Tandai Dibayar** | `payroll.approve` | **Admin, Kepala Sekolah** |
| **Finalisasi** | `payroll.approve` | **Admin, Kepala Sekolah** |
| Hapus Periode | `payroll.manage` | Admin, TU |

---

## 2. Akses Menu Penggajian

Untuk mengakses modul penggajian:

1. Login ke aplikasi SMS Absensi
2. Pada sidebar, klik menu **Penggajian**
3. Pilih submenu yang diinginkan

![Menu Penggajian](images/payroll/menu-payroll.png)
*Gambar 2.1: Menu Penggajian pada sidebar*

### Daftar Submenu

| Submenu | URL | Fungsi |
|---------|-----|--------|
| Golongan Gaji | `/payroll/salary-grades` | Kelola golongan/grade gaji |
| Komponen Gaji | `/payroll/salary-components` | Kelola tunjangan & potongan |
| Tarif BPJS | `/payroll/bpjs-rates` | Konfigurasi tarif BPJS |
| Bracket Pajak | `/payroll/tax-brackets` | Konfigurasi PPh 21 progresif |
| Pengaturan Pajak | `/payroll/tax-settings` | PTKP dan biaya jabatan |
| Gaji Karyawan | `/payroll/employee-salaries` | Setup gaji per guru/staf |
| Periode Gaji | `/payroll/periods` | Kelola periode penggajian |
| Laporan | `/payroll/reports` | Laporan penggajian |

---

## 3. Konfigurasi Awal

Sebelum memproses penggajian, lakukan konfigurasi awal berikut (hanya perlu sekali di awal atau saat ada perubahan kebijakan).

### 3.1 Golongan Gaji

Golongan gaji menentukan gaji pokok berdasarkan tingkat/grade karyawan.

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Golongan Gaji**

![Halaman Golongan Gaji](images/payroll/salary-grades-list.png)
*Gambar 3.1: Daftar Golongan Gaji*

**Menambah Golongan Gaji:**

1. Klik tombol **+ Tambah Golongan**
2. Isi form:
   - **Kode**: Kode unik golongan (contoh: `I-A`, `II-B`, `III-C`)
   - **Nama**: Nama golongan (contoh: `Golongan I-A`)
   - **Gaji Pokok**: Nominal gaji pokok
   - **Urutan**: Urutan tampil (untuk sorting)
   - **Status**: Aktif/Tidak Aktif
3. Klik **Simpan**

![Form Tambah Golongan](images/payroll/salary-grades-form.png)
*Gambar 3.2: Form Tambah Golongan Gaji*

**Contoh Golongan Gaji:**

| Kode | Nama | Gaji Pokok |
|------|------|------------|
| I-A | Golongan I-A | Rp 2.500.000 |
| I-B | Golongan I-B | Rp 2.750.000 |
| II-A | Golongan II-A | Rp 3.000.000 |
| II-B | Golongan II-B | Rp 3.500.000 |
| III-A | Golongan III-A | Rp 4.000.000 |
| III-B | Golongan III-B | Rp 4.500.000 |
| IV-A | Golongan IV-A | Rp 5.000.000 |

---

### 3.2 Komponen Gaji

Komponen gaji terdiri dari **Tunjangan (Earning)** dan **Potongan (Deduction)**.

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Komponen Gaji**

![Halaman Komponen Gaji](images/payroll/salary-components-list.png)
*Gambar 3.3: Daftar Komponen Gaji*

**Tipe Komponen:**

| Tipe | Keterangan |
|------|------------|
| **Earning** | Tunjangan yang menambah gaji (tunjangan jabatan, transport, makan, dll) |
| **Deduction** | Potongan yang mengurangi gaji (BPJS, PPh 21, pinjaman, dll) |

**Tipe Perhitungan:**

| Tipe | Keterangan | Contoh |
|------|------------|--------|
| **Fixed** | Nilai tetap | Tunjangan jabatan Rp 500.000 |
| **Percentage** | Persentase dari nilai lain | Tunjangan 10% dari gaji pokok |
| **Per Day** | Dikalikan jumlah hari | Uang makan Rp 25.000/hari |
| **Per Hour** | Dikalikan jumlah jam | Lembur Rp 50.000/jam |
| **Formula** | Formula kustom | Perhitungan khusus |

**Menambah Komponen Gaji:**

1. Klik tombol **+ Tambah Komponen**
2. Isi form:
   - **Kode**: Kode unik (contoh: `TJ-001`, `POT-BPJS`)
   - **Nama**: Nama komponen (contoh: `Tunjangan Jabatan`)
   - **Tipe**: Earning atau Deduction
   - **Tipe Perhitungan**: Fixed/Percentage/Per Day/Per Hour/Formula
   - **Nilai Default**: Nilai default komponen
   - **Kena Pajak**: Centang jika komponen ini kena pajak
   - **Wajib**: Centang jika komponen wajib untuk semua karyawan
3. Klik **Simpan**

![Form Komponen Gaji](images/payroll/salary-components-form.png)
*Gambar 3.4: Form Tambah Komponen Gaji*

**Contoh Komponen Gaji:**

| Kode | Nama | Tipe | Perhitungan | Nilai | Kena Pajak |
|------|------|------|-------------|-------|------------|
| TJ-JAB | Tunjangan Jabatan | Earning | Fixed | Rp 500.000 | Ya |
| TJ-TRANS | Tunjangan Transport | Earning | Fixed | Rp 300.000 | Ya |
| TJ-MAKAN | Uang Makan | Earning | Per Day | Rp 25.000 | Ya |
| POT-BPJS-KES | BPJS Kesehatan | Deduction | Percentage | 1% | Tidak |
| POT-BPJS-JHT | BPJS JHT | Deduction | Percentage | 2% | Tidak |

---

### 3.3 Tarif BPJS

Konfigurasi tarif BPJS sesuai peraturan yang berlaku.

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Tarif BPJS**

![Halaman Tarif BPJS](images/payroll/bpjs-rates-list.png)
*Gambar 3.5: Daftar Tarif BPJS*

**Jenis BPJS:**

| Jenis | Iuran Karyawan | Iuran Pemberi Kerja | Batas Gaji |
|-------|----------------|---------------------|------------|
| BPJS Kesehatan | 1% | 4% | Max Rp 12.000.000 |
| BPJS JHT | 2% | 3.7% | - |
| BPJS JP | 1% | 2% | Max Rp 9.559.600 |
| BPJS JKK | - | 0.24% - 1.74% | - |
| BPJS JKM | - | 0.3% | - |

**Menambah/Edit Tarif BPJS:**

1. Klik tombol **+ Tambah Tarif** atau **Edit** pada baris yang ada
2. Isi form:
   - **Jenis**: Pilih jenis BPJS
   - **Tarif Karyawan (%)**: Persentase iuran karyawan
   - **Tarif Pemberi Kerja (%)**: Persentase iuran perusahaan
   - **Gaji Minimum**: Batas bawah perhitungan
   - **Gaji Maksimum**: Batas atas perhitungan (ceiling)
   - **Berlaku Dari**: Tanggal mulai berlaku
   - **Berlaku Sampai**: Tanggal akhir (kosongkan jika masih berlaku)
3. Klik **Simpan**

![Form Tarif BPJS](images/payroll/bpjs-rates-form.png)
*Gambar 3.6: Form Edit Tarif BPJS*

---

### 3.4 Bracket Pajak PPh 21

Konfigurasi tarif pajak penghasilan progresif sesuai UU HPP.

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Bracket Pajak**

![Halaman Bracket Pajak](images/payroll/tax-brackets-list.png)
*Gambar 3.7: Daftar Bracket Pajak PPh 21*

**Tarif PPh 21 Progresif (UU HPP 2022):**

| Lapisan | Penghasilan Kena Pajak (PKP) | Tarif |
|---------|------------------------------|-------|
| 1 | Rp 0 - Rp 60.000.000 | 5% |
| 2 | Rp 60.000.001 - Rp 250.000.000 | 15% |
| 3 | Rp 250.000.001 - Rp 500.000.000 | 25% |
| 4 | Rp 500.000.001 - Rp 5.000.000.000 | 30% |
| 5 | > Rp 5.000.000.000 | 35% |

**Menambah Bracket:**

1. Klik **+ Tambah Bracket**
2. Isi form:
   - **Batas Bawah**: Nilai minimum PKP
   - **Batas Atas**: Nilai maksimum PKP
   - **Tarif (%)**: Persentase pajak
   - **Tahun Efektif**: Tahun berlaku
3. Klik **Simpan**

![Form Bracket Pajak](images/payroll/tax-brackets-form.png)
*Gambar 3.8: Form Tambah Bracket Pajak*

---

### 3.5 Pengaturan Pajak (PTKP)

PTKP (Penghasilan Tidak Kena Pajak) adalah batas penghasilan yang tidak dikenakan pajak.

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Pengaturan Pajak**

![Halaman Pengaturan Pajak](images/payroll/tax-settings-list.png)
*Gambar 3.9: Daftar Pengaturan Pajak*

**Nilai PTKP (2024):**

| Status | Keterangan | Nilai PTKP/Tahun |
|--------|------------|------------------|
| TK/0 | Tidak Kawin, tanpa tanggungan | Rp 54.000.000 |
| TK/1 | Tidak Kawin, 1 tanggungan | Rp 58.500.000 |
| TK/2 | Tidak Kawin, 2 tanggungan | Rp 63.000.000 |
| TK/3 | Tidak Kawin, 3 tanggungan | Rp 67.500.000 |
| K/0 | Kawin, tanpa tanggungan | Rp 58.500.000 |
| K/1 | Kawin, 1 tanggungan | Rp 63.000.000 |
| K/2 | Kawin, 2 tanggungan | Rp 67.500.000 |
| K/3 | Kawin, 3 tanggungan | Rp 72.000.000 |

**Pengaturan Lainnya:**

| Setting | Nilai |
|---------|-------|
| Biaya Jabatan | 5% dari penghasilan bruto (max Rp 500.000/bulan atau Rp 6.000.000/tahun) |

---

## 4. Setup Gaji Karyawan

Setelah konfigurasi awal selesai, setup gaji untuk setiap guru dan staf.

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Gaji Karyawan**

![Halaman Gaji Karyawan](images/payroll/employee-salaries-list.png)
*Gambar 4.1: Daftar Gaji Karyawan*

### Menambah Setup Gaji Karyawan

1. Klik tombol **+ Setup Gaji Baru**
2. Isi form:
   - **Tipe Karyawan**: Pilih Guru atau Staf
   - **Nama Karyawan**: Pilih dari daftar (hanya yang belum punya setup)
   - **Golongan Gaji**: Pilih golongan
   - **Gaji Pokok**: Otomatis terisi dari golongan, bisa di-override
   - **Status PTKP**: Pilih status perkawinan/tanggungan
   - **Tanggal Efektif**: Tanggal mulai berlaku
   - **Tanggal Berakhir**: Kosongkan jika masih berlaku
   - **Catatan**: Catatan tambahan (opsional)
3. Klik **Simpan**

![Form Setup Gaji](images/payroll/employee-salaries-form.png)
*Gambar 4.2: Form Setup Gaji Karyawan*

### Mengelola Komponen Gaji Karyawan

Setiap karyawan bisa memiliki komponen gaji yang berbeda:

1. Klik **Detail** pada baris karyawan
2. Pada tab **Komponen Gaji**, klik **Kelola Komponen**
3. Centang komponen yang aktif untuk karyawan ini
4. Override nilai jika diperlukan
5. Klik **Simpan**

![Komponen Gaji Karyawan](images/payroll/employee-salary-components.png)
*Gambar 4.3: Komponen Gaji per Karyawan*

### Melihat Riwayat Gaji

Setiap perubahan gaji tercatat dalam riwayat:

1. Klik **Detail** pada baris karyawan
2. Buka tab **Riwayat Gaji**

![Riwayat Gaji](images/payroll/salary-history.png)
*Gambar 4.4: Riwayat Perubahan Gaji*

**Tipe Perubahan:**

| Tipe | Keterangan |
|------|------------|
| Initial | Setup gaji pertama kali |
| Promotion | Kenaikan golongan/gaji |
| Adjustment | Penyesuaian gaji |
| Demotion | Penurunan golongan |

---

## 5. Proses Penggajian Bulanan

### 5.1 Membuat Periode Gaji

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Periode Gaji**

![Halaman Periode Gaji](images/payroll/payroll-periods-list.png)
*Gambar 5.1: Daftar Periode Gaji*

**Membuat Periode Baru:**

1. Klik tombol **+ Buat Periode**
2. Isi form:
   - **Tahun**: Pilih tahun
   - **Bulan**: Pilih bulan
   - **Tanggal Mulai**: Tanggal awal periode (default: tanggal 1)
   - **Tanggal Akhir**: Tanggal akhir periode (default: akhir bulan)
   - **Tanggal Pembayaran**: Tanggal gaji dibayarkan
3. Klik **Simpan**

![Form Periode Gaji](images/payroll/payroll-periods-form.png)
*Gambar 5.2: Form Buat Periode Gaji*

**Status Periode:**

| Status | Keterangan | Aksi yang Tersedia |
|--------|------------|-------------------|
| **Draft** | Baru dibuat | Edit, Hapus, Generate Slip |
| **Processing** | Slip sedang diproses | Edit Slip, Hitung Ulang, Submit Approval |
| **Pending Approval** | Menunggu persetujuan | Approve, Tolak |
| **Approved** | Disetujui | Tandai Dibayar |
| **Paid** | Sudah dibayar | Finalisasi |
| **Finalized** | Final/Terkunci | Cetak Laporan |

---

### 5.2 Generate Slip Gaji

Setelah periode dibuat, generate slip gaji untuk semua karyawan. Tersedia **dua opsi** generate:

**Opsi 1: Generate Slip (Tanpa Kehadiran)**

1. Pada daftar periode, klik tombol **⋮** (menu) pada periode **Draft**
2. Pilih **Generate Slip**
3. Sistem akan:
   - Mengambil semua karyawan dengan setup gaji aktif
   - Membuat slip gaji per karyawan
   - Menghitung gaji pokok + tunjangan tetap
   - Menghitung potongan BPJS dan PPh 21
4. Status periode berubah menjadi **Processing**

**Opsi 2: Generate + Kehadiran (Rekomendasi)**

1. Pada daftar periode, klik tombol **⋮** (menu) pada periode **Draft**
2. Pilih **Generate + Kehadiran**
3. Sistem akan melakukan semua langkah di atas, **ditambah**:
   - Mengambil data kehadiran karyawan dalam periode tersebut
   - Menghitung hari hadir, hari absen, hari terlambat
   - Menerapkan komponen berbasis kehadiran (uang makan per hari, potongan absen, dll)
4. Status periode berubah menjadi **Processing**

![Generate Slip](images/payroll/generate-slips.png)
*Gambar 5.3: Proses Generate Slip Gaji*

**Integrasi Data Kehadiran:**

Sistem akan mengambil data dari tabel `employee_attendances` dan menghitung:

| Data | Keterangan |
|------|------------|
| `working_days` | Jumlah hari kerja dalam periode (exclude weekend) |
| `days_present` | Jumlah hari hadir (status: present, late) |
| `days_absent` | Jumlah hari absen (status: absent, alpha) |
| `days_late` | Jumlah hari terlambat |
| `days_leave` | Jumlah hari izin/cuti |
| `total_late_minutes` | Total menit keterlambatan |
| `total_overtime_minutes` | Total menit lembur |

**Komponen Berbasis Kehadiran:**

Komponen gaji dengan tipe perhitungan `per_day` atau `per_hour` akan otomatis dihitung:

| Kode Komponen | Tipe | Perhitungan |
|---------------|------|-------------|
| `TJ-MAKAN` / `UANG_MAKAN` | per_day | × hari hadir |
| `TJ-HADIR` | per_day | × hari hadir |
| `POT-ABSEN` | per_day | × hari absen |
| `POT-TELAT` | per_hour | × jam terlambat |
| `TJ-LEMBUR` | per_hour | × jam lembur |

**Ringkasan Periode:**

Setelah generate, lihat ringkasan:

| Informasi | Keterangan |
|-----------|------------|
| Jumlah Karyawan | Total karyawan yang diproses |
| Total Gaji Kotor | Jumlah seluruh gaji bruto |
| Total Potongan | Jumlah seluruh potongan |
| Total Gaji Bersih | Jumlah seluruh gaji netto |

---

### 5.3 Review & Edit Slip

Setelah slip di-generate, review dan edit jika diperlukan:

1. Klik **Lihat Slip** pada periode
2. Daftar slip gaji per karyawan ditampilkan

![Daftar Slip Gaji](images/payroll/payroll-slips-list.png)
*Gambar 5.4: Daftar Slip Gaji dalam Periode*

**Melihat Detail Slip:**

1. Klik **Detail** pada baris slip
2. Lihat rincian:
   - **Penghasilan**: Gaji pokok + tunjangan
   - **Potongan**: BPJS, PPh 21, potongan lain
   - **Gaji Bersih**: Total yang diterima

![Detail Slip](images/payroll/slip-detail.png)
*Gambar 5.5: Detail Slip Gaji*

**Mengedit Item Slip (Jika Diperlukan):**

1. Pada detail slip, klik **Edit** pada item yang ingin diubah
2. Ubah nilai yang diperlukan:
   - **Jumlah/Hari**: Untuk komponen berbasis kehadiran
   - **Tarif**: Nilai per unit
   - **Nominal**: Nilai total (otomatis dihitung jika ada jumlah × tarif)
3. **Wajib isi alasan perubahan** (untuk audit log)
4. Klik **Simpan**
5. Sistem otomatis menghitung ulang total

![Edit Item Slip](images/payroll/slip-edit-items.png)
*Gambar 5.6: Edit Item Slip Gaji*

**Kapan Slip Bisa Diedit:**

| Status Periode | Status Slip | Bisa Edit? |
|----------------|-------------|------------|
| Draft | Draft | ✅ Ya |
| Processing | Calculated | ✅ Ya |
| Pending Approval | Calculated | ✅ Ya |
| Approved | Approved | ✅ Ya |
| Paid | Paid | ❌ Tidak |
| Finalized | Paid | ❌ Tidak |

> **Catatan:** Setelah periode **Paid** atau **Finalized**, slip tidak bisa diedit lagi. Jika ada kesalahan, buat periode koreksi baru.

**Melihat Riwayat Perubahan (Audit Log):**

1. Pada detail slip, klik tab **Riwayat Perubahan**
2. Lihat daftar perubahan yang pernah dilakukan:
   - Komponen yang diubah
   - Nilai lama → Nilai baru
   - Alasan perubahan
   - Siapa yang mengubah
   - Kapan diubah

![Audit Log](images/payroll/slip-audit-log.png)
*Gambar 5.6b: Riwayat Perubahan Slip Gaji*

**Hitung Ulang Semua Slip:**

Jika ada perubahan tarif BPJS atau pajak:
1. Kembali ke daftar periode
2. Klik tombol **⋮** (menu) → **Hitung Ulang**
3. Semua slip dihitung ulang dengan tarif terbaru

**Hitung Kehadiran (Untuk Slip yang Sudah Ada):**

Jika slip sudah di-generate tanpa kehadiran dan ingin menambahkan data kehadiran:
1. Klik tombol **⋮** (menu) → **Hitung Kehadiran**
2. Sistem akan:
   - Mengambil data kehadiran terbaru dari tabel `employee_attendances`
   - Menerapkan komponen berbasis kehadiran ke slip yang sudah ada
   - Memperbarui item slip tanpa menghapus item manual yang sudah ditambahkan
3. Slip akan diperbarui dengan data kehadiran

> **Catatan:** Aksi ini berguna jika data absensi diinput setelah slip gaji di-generate.

---

### 5.4 Approval & Finalisasi

**Alur Approval dengan Pembagian Tugas:**

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           ALUR APPROVAL                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────┐                                                    │
│  │   ADMIN / TU     │ ← Permission: payroll.process                     │
│  │  (Pelaksana)     │                                                    │
│  ├──────────────────┤                                                    │
│  │ 1. Buat Periode  │                                                    │
│  │ 2. Generate Slip │                                                    │
│  │ 3. Hitung Ulang  │                                                    │
│  │ 4. Review & Edit │                                                    │
│  │ 5. Ajukan        │                                                    │
│  │    Persetujuan   │───────────────┐                                    │
│  └──────────────────┘               │                                    │
│                                     ▼                                    │
│                      ┌──────────────────────┐                            │
│                      │  KEPALA SEKOLAH      │ ← Permission: payroll.approve
│                      │    (Approver)        │                            │
│                      ├──────────────────────┤                            │
│                      │ 6. Review Slip       │                            │
│                      │ 7. SETUJUI           │                            │
│                      └──────────┬───────────┘                            │
│                                 │                                        │
│                                 ▼                                        │
│  ┌──────────────────┐          ┌──────────────────┐                     │
│  │   ADMIN / TU     │          │ KEPALA SEKOLAH   │                     │
│  │   (Eksekusi)     │    atau  │  (Otorisasi)     │                     │
│  ├──────────────────┤          ├──────────────────┤                     │
│  │ 8. Tandai Bayar  │          │ 8. Tandai Bayar  │                     │
│  │ 9. Finalisasi    │          │ 9. Finalisasi    │                     │
│  └──────────────────┘          └──────────────────┘                     │
│         ↓                              ↓                                 │
│  ┌─────────────────────────────────────────────────┐                    │
│  │              PERIODE FINAL (TERKUNCI)           │                    │
│  └─────────────────────────────────────────────────┘                    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

**Status Periode dan Artinya:**

| Status | Keterangan | Siapa yang Bertindak |
|--------|------------|---------------------|
| **Draft** | Periode baru dibuat, belum ada slip | Admin/TU |
| **Processing** | Slip sudah di-generate, sedang review | Admin/TU |
| **Pending Approval** | Diajukan, menunggu persetujuan | Kepala Sekolah |
| **Approved** | Disetujui, siap bayar | Admin/TU atau Kepsek |
| **Paid** | Gaji sudah dibayarkan | Admin/TU atau Kepsek |
| **Finalized** | Terkunci, tidak bisa diubah | - |

---

**Langkah 1-5: Proses oleh Admin/TU**

*Membutuhkan permission: `payroll.process`*

1. **Buat Periode** → Klik **+ Buat Periode**, pilih bulan/tahun
2. **Generate Slip** → Menu **⋮** → **Generate Slip** atau **Generate + Kehadiran**
3. **Review & Edit** → Klik **Lihat Slip** untuk cek/edit slip per karyawan
4. **Hitung Ulang** (jika perlu) → Menu **⋮** → **Hitung Ulang** atau **Hitung Kehadiran**
5. **Ajukan Persetujuan** → Menu **⋮** → **Ajukan Persetujuan**

Setelah diajukan, status berubah ke **Pending Approval** dan menunggu kepala sekolah.

---

**Langkah 6-7: Approval oleh Kepala Sekolah**

*Membutuhkan permission: `payroll.approve`*

6. **Review** → Kepala Sekolah membuka periode, lihat ringkasan dan slip
7. **Setujui** → Menu **⋮** → **Setujui**

![Approval](images/payroll/approval.png)
*Gambar 5.7: Halaman Approval Periode Gaji*

> **Tombol Setujui hanya muncul** jika:
> - User memiliki permission `payroll.approve`
> - Status periode adalah `Pending Approval`

Jika ada yang perlu diperbaiki, Kepala Sekolah memberitahu Admin/TU secara manual untuk melakukan koreksi (sistem akan dikembangkan untuk fitur "Tolak" di versi berikutnya).

---

**Langkah 8: Tandai Sudah Dibayar**

*Membutuhkan permission: `payroll.approve`*

Setelah gaji ditransfer ke rekening karyawan:

1. Klik Menu **⋮** → **Tandai Dibayar**
2. Konfirmasi tindakan
3. Status periode berubah ke **Paid**
4. Status semua slip juga berubah menjadi **Paid**

---

**Langkah 9: Finalisasi**

*Membutuhkan permission: `payroll.approve`*

1. Klik Menu **⋮** → **Finalisasi**
2. Konfirmasi tindakan
3. Setelah final:
   - ❌ Periode tidak bisa diedit lagi
   - ❌ Slip tidak bisa diubah
   - ✅ Data tersimpan untuk laporan dan audit
   - ✅ Dicatat siapa yang memfinalisasi dan kapan

---

**Ringkasan Permission per Aksi:**

| Aksi | Permission | Tampil di UI Jika |
|------|------------|-------------------|
| Ajukan Persetujuan | `payroll.process` | Status = Processing |
| Setujui | `payroll.approve` | Status = Pending Approval |
| Tandai Dibayar | `payroll.approve` | Status = Approved |
| Finalisasi | `payroll.approve` | Status = Paid |

---

## 6. Cetak Slip Gaji

**Cetak Slip Individual:**

1. Buka detail slip gaji
2. Klik tombol **Cetak** atau **Download PDF**

![Cetak Slip](images/payroll/print-slip.png)
*Gambar 6.1: Preview Cetak Slip Gaji*

**Format Slip Gaji:**

```
╔══════════════════════════════════════════════════════════════╗
║                        SLIP GAJI                              ║
║                    [NAMA SEKOLAH]                             ║
║                  Periode: Januari 2026                        ║
╠══════════════════════════════════════════════════════════════╣
║ Nama      : Ahmad Sudrajat                                    ║
║ NIP/NIK   : 198501012010011001                               ║
║ Golongan  : III-A                                             ║
║ Status    : K/2                                               ║
╠══════════════════════════════════════════════════════════════╣
║ PENGHASILAN                           │ POTONGAN              ║
╠───────────────────────────────────────┼───────────────────────╣
║ Gaji Pokok        : Rp  4.000.000     │ BPJS Kesehatan: Rp 40.000 ║
║ Tunj. Jabatan     : Rp    500.000     │ BPJS JHT     : Rp 80.000  ║
║ Tunj. Transport   : Rp    300.000     │ BPJS JP      : Rp 40.000  ║
║ Uang Makan (22hr) : Rp    550.000     │ PPh 21       : Rp 75.000  ║
║                                       │                           ║
╠───────────────────────────────────────┼───────────────────────────╣
║ Total Penghasilan : Rp  5.350.000     │ Total Potongan: Rp 235.000║
╠═══════════════════════════════════════════════════════════════════╣
║                  GAJI BERSIH: Rp 5.115.000                        ║
╚═══════════════════════════════════════════════════════════════════╝
```

**Cetak Massal:**

1. Pada daftar slip, centang slip yang ingin dicetak
2. Klik **Cetak Terpilih**
3. Atau klik **Cetak Semua** untuk cetak seluruh slip dalam periode

---

## 7. Laporan Penggajian

**Cara Mengakses:**
1. Buka menu **Penggajian** > **Laporan**

![Halaman Laporan](images/payroll/reports.png)
*Gambar 7.1: Halaman Laporan Penggajian*

**Jenis Laporan:**

| Laporan | Keterangan |
|---------|------------|
| Rekap Gaji Bulanan | Ringkasan gaji per bulan |
| Rekap Gaji per Golongan | Pengelompokan berdasarkan golongan |
| Laporan PPh 21 | Rekap pajak untuk pelaporan SPT |
| Laporan BPJS | Rekap iuran BPJS bulanan |
| Riwayat Kenaikan Gaji | Daftar perubahan gaji karyawan |

**Mengunduh Laporan:**

1. Pilih jenis laporan
2. Tentukan periode (bulan/tahun)
3. Klik **Generate Laporan**
4. Klik **Download** (format Excel/PDF)

### 7.1 Laporan Pengeluaran (Expense Report)

Laporan pengeluaran menampilkan ringkasan pengeluaran gaji dibandingkan dengan pemasukan SPP.

**Cara Mengakses:**
1. Buka menu **Laporan** > **Pengeluaran** (URL: `/reports/expense`)

![Laporan Pengeluaran](images/payroll/expense-report.png)
*Gambar 7.2: Laporan Pengeluaran Bulanan*

**Informasi yang Ditampilkan:**

| Kartu | Keterangan |
|-------|------------|
| Pemasukan SPP | Total pembayaran SPP yang diterima |
| Pengeluaran Gaji | Total gaji bersih yang dibayarkan |
| Saldo Bersih | Pemasukan - Pengeluaran |
| Karyawan Digaji | Jumlah karyawan dalam periode |

**Ringkasan Gaji:**

| Field | Keterangan |
|-------|------------|
| Total Gaji Kotor | Jumlah gaji bruto semua karyawan |
| Potongan (BPJS + Pajak) | Jumlah seluruh potongan |
| Gaji Bersih (THP) | Take Home Pay yang dibayarkan |

**Filter:**

- **Tahun**: Pilih tahun laporan
- **Bulan**: Pilih bulan spesifik atau "Semua Bulan" untuk laporan tahunan

**Tampilan Per Bulan (Jika pilih Semua Bulan):**

Tabel rincian per bulan menampilkan:
- Pemasukan SPP per bulan
- Pengeluaran Gaji per bulan
- Saldo per bulan
- Status proses gaji (Sudah Proses / Belum Proses)

**Export:**

Klik **Export CSV** untuk mengunduh laporan dalam format CSV.

---

## 8. FAQ & Troubleshooting

### Q: Kenapa slip gaji tidak bisa di-generate?

**A:** Pastikan:
- Periode belum pernah generate slip sebelumnya
- Ada karyawan dengan setup gaji aktif (`is_current = true`)
- Tanggal efektif gaji karyawan sudah melewati tanggal periode

### Q: Kenapa perhitungan PPh 21 berbeda dengan hitungan manual?

**A:** Sistem menggunakan rumus:

```
Gaji Tahunan = Gaji Bruto × 12
Biaya Jabatan = 5% × Gaji Tahunan (max Rp 6.000.000)
PKP = Gaji Tahunan - Biaya Jabatan - BPJS Tahunan - PTKP
PPh 21 Tahunan = Hitung Pajak Progresif dari PKP
PPh 21 Bulanan = PPh 21 Tahunan ÷ 12
```

Pastikan:
- Tarif bracket pajak sudah benar
- PTKP sesuai status karyawan
- Biaya jabatan sudah dikonfigurasi

### Q: Bagaimana cara mengubah gaji yang sudah di-finalize?

**A:** Periode yang sudah **Finalized** tidak bisa diubah. Solusi:
1. Buat periode baru
2. Lakukan penyesuaian pada periode baru
3. Jika perlu koreksi, buat jurnal penyesuaian terpisah

### Q: Bagaimana menangani karyawan baru di tengah bulan?

**A:**
1. Setup gaji karyawan dengan tanggal efektif sesuai tanggal mulai bekerja
2. Saat generate slip, sistem akan menghitung proporsional jika komponen menggunakan perhitungan `per_day`
3. Atau edit manual jumlah hari kerja pada slip

### Q: Bagaimana jika tarif BPJS berubah?

**A:**
1. Edit tarif BPJS yang ada atau tambah tarif baru dengan tanggal efektif
2. Untuk periode yang sudah di-generate:
   - Jika belum finalized, klik **Hitung Ulang**
   - Jika sudah finalized, perubahan berlaku di periode berikutnya

### Q: Slip gaji tidak muncul untuk karyawan tertentu?

**A:** Pastikan:
- Karyawan memiliki setup gaji dengan `is_current = true`
- Tanggal efektif ≤ tanggal akhir periode
- Tanggal berakhir kosong atau ≥ tanggal mulai periode

### Q: Kenapa tombol "Setujui" tidak muncul?

**A:** Tombol Setujui hanya muncul jika:
1. Status periode adalah **Pending Approval**
2. User memiliki permission `payroll.approve`

Role yang memiliki permission ini secara default:
- Super Admin
- Admin
- Kepala Sekolah

Jika kepala sekolah tidak melihat tombol ini, minta administrator untuk menjalankan:
```bash
php artisan db:seed --class=RoleSeeder --force
php artisan permission:cache-reset
```

### Q: Kenapa data kehadiran tidak masuk ke slip gaji?

**A:** Pastikan:
1. Saat generate slip, pilih **Generate + Kehadiran** (bukan Generate Slip biasa)
2. Atau setelah generate, klik **Hitung Kehadiran**
3. Data kehadiran sudah diinput di tabel `employee_attendances` untuk periode tersebut
4. Komponen gaji yang berbasis kehadiran memiliki tipe perhitungan `per_day` atau `per_hour`
5. Kode komponen mengandung kata kunci yang dikenali:
   - Untuk hari hadir: `HADIR`, `PRESENT`, `MAKAN`
   - Untuk hari absen: `ABSEN`, `ABSENT`, `ALPHA`
   - Untuk keterlambatan: `TELAT`, `LATE`
   - Untuk lembur: `LEMBUR`, `OVERTIME`

---

## Lampiran

### A. Alur Kerja Lengkap

```
┌─────────────────────────────────────────────────────────────────┐
│                    KONFIGURASI (Sekali)                         │
├─────────────────────────────────────────────────────────────────┤
│  Golongan → Komponen → Tarif BPJS → Bracket Pajak → PTKP       │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 SETUP GAJI KARYAWAN (Per Orang)                 │
├─────────────────────────────────────────────────────────────────┤
│  Pilih Karyawan → Assign Golongan → Set Komponen → Set PTKP    │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 PROSES BULANAN                                   │
├─────────────────────────────────────────────────────────────────┤
│  Buat Periode → Generate Slip → Review → Approve → Pay → Final │
└─────────────────────────────────────────────────────────────────┘
```

### B. Rumus Perhitungan

**Gaji Bersih:**
```
Gaji Bersih = Gaji Bruto - Total Potongan
```

**BPJS Kesehatan:**
```
Basis = min(Gaji Bruto, Rp 12.000.000)
Iuran Karyawan = Basis × 1%
Iuran Perusahaan = Basis × 4%
```

**BPJS JHT:**
```
Iuran Karyawan = Gaji Bruto × 2%
Iuran Perusahaan = Gaji Bruto × 3.7%
```

**BPJS JP:**
```
Basis = min(Gaji Bruto, Rp 9.559.600)
Iuran Karyawan = Basis × 1%
Iuran Perusahaan = Basis × 2%
```

**PPh 21 (Bulanan):**
```
Gaji Tahunan = Gaji Bruto × 12
Biaya Jabatan = min(5% × Gaji Tahunan, Rp 6.000.000)
BPJS Tahunan = (BPJS Kesehatan + BPJS JHT + BPJS JP) × 12
PKP = Gaji Tahunan - Biaya Jabatan - BPJS Tahunan - PTKP

// Hitung Pajak Progresif
if (PKP ≤ 0) PPh = 0
else:
  PPh = 0
  for each bracket:
    taxable = min(PKP, bracket.max) - bracket.min
    PPh += taxable × bracket.rate

PPh21 Bulanan = PPh ÷ 12
```

---

## Catatan Teknis

**API Endpoint Utama:**

| Endpoint | Method | Permission | Fungsi |
|----------|--------|------------|--------|
| `/api/v1/payroll/periods` | POST | `payroll.manage` | Buat periode |
| `/api/v1/payroll/periods/{id}/generate-slips` | POST | `payroll.process` | Generate slip |
| `/api/v1/payroll/periods/{id}/generate-slips-with-attendance` | POST | `payroll.process` | Generate + kehadiran |
| `/api/v1/payroll/periods/{id}/calculate` | POST | `payroll.process` | Hitung ulang |
| `/api/v1/payroll/periods/{id}/calculate-attendance` | POST | `payroll.process` | Hitung kehadiran |
| `/api/v1/payroll/periods/{id}/submit-for-approval` | POST | `payroll.process` | Ajukan persetujuan |
| `/api/v1/payroll/periods/{id}/approve` | POST | `payroll.approve` | Setujui |
| `/api/v1/payroll/periods/{id}/mark-as-paid` | POST | `payroll.approve` | Tandai dibayar |
| `/api/v1/payroll/periods/{id}/finalize` | POST | `payroll.approve` | Finalisasi |
| `/api/v1/payroll/slips/{id}/print` | GET | `payroll.view` | Data cetak slip |
| `/api/v1/payroll/slips/{slipId}/items/{itemId}` | PUT | `payroll.process` | Edit item dengan audit |
| `/api/v1/payroll/slips/{id}/audits` | GET | `payroll.view` | Riwayat perubahan |

**API Endpoint Laporan:**

| Endpoint | Method | Permission | Fungsi |
|----------|--------|------------|--------|
| `/api/v1/finance/reports/monthly-expense` | GET | `finance.report` | Laporan pengeluaran |
| `/api/v1/finance/reports/export/monthly-expense` | GET | `finance.report` | Export CSV |

---

---

## Catatan untuk Administrator Sistem

### Sinkronisasi Permission

Jika baru mengupdate sistem dan role kepala_sekolah belum memiliki permission `payroll.approve`, jalankan perintah berikut:

```bash
# Update permission untuk semua role
php artisan db:seed --class=RoleSeeder --force

# Reset cache permission
php artisan permission:cache-reset
```

> **Peringatan:** Perintah di atas akan mengupdate permission SEMUA role ke nilai default dari seeder. Jika ada perubahan permission custom, backup terlebih dahulu.

### Menambah Permission ke User/Role Tertentu

Alternatif jika tidak ingin menjalankan seeder penuh:

```bash
# Via tinker
php artisan tinker

# Tambah permission ke role kepala_sekolah
$role = \Spatie\Permission\Models\Role::findByName('kepala_sekolah');
$role->givePermissionTo(['payroll.view', 'payroll.approve', 'payroll.report']);

# Reset cache
\Artisan::call('permission:cache-reset');
```

---

*Dokumen ini dibuat untuk SMS Absensi - Sistem Manajemen Sekolah*
*Versi 1.1 - September 2026*
*Update: Penambahan sistem approval berbasis permission dan integrasi kehadiran*
