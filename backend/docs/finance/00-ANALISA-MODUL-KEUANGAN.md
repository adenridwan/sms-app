# Analisis Modul Keuangan SMS Enterprise

> Dokumen ini berisi analisis kebutuhan fitur keuangan sekolah, termasuk pembayaran siswa dan penggajian (payroll) guru/staf. Implementasi diurutkan dari yang **termudah** ke yang **paling kompleks**.

---

## Daftar Isi

1. [Status Implementasi Saat Ini](#1-status-implementasi-saat-ini)
2. [Arsitektur & Entitas Terkait](#2-arsitektur--entitas-terkait)
3. [Modul A: Pembayaran Siswa](#3-modul-a-pembayaran-siswa)
4. [Modul B: Penggajian (Payroll) Guru/Staf](#4-modul-b-penggajian-payroll-gurustaf)
5. [Roadmap Implementasi (Urut Termudah)](#5-roadmap-implementasi-urut-termudah)
6. [Struktur Menu yang Disarankan](#6-struktur-menu-yang-disarankan)
7. [Estimasi Tabel Database Baru](#7-estimasi-tabel-database-baru)

---

## 1. Status Implementasi Saat Ini

### Tabel Database yang Sudah Ada

| Tabel | Fungsi | Status Controller |
|-------|--------|-------------------|
| `fee_types` | Jenis biaya (SPP, UK, UPRAK) | ✅ CRUD lengkap |
| `fee_structures` | Tarif per tahun/kelas/jurusan | ❌ Stub (501) |
| `student_fees` | Tagihan per siswa | ❌ Stub (501) |
| `payments` | Transaksi pembayaran | ⚠️ Partial |
| `payment_items` | Detail item pembayaran | ⚠️ Partial |
| `payment_methods` | Metode pembayaran | ❌ Stub (501) |
| `discounts` | Definisi potongan/beasiswa | ❌ Stub (501) |
| `student_discounts` | Aplikasi potongan per siswa | ❌ Stub (501) |
| `financial_reports` | Ringkasan bulanan | ❌ Stub (501) |

### Frontend

- Folder `resources/js/pages/finance/` **kosong** — belum ada UI sama sekali.

### Kesimpulan

- **Database schema** untuk pembayaran siswa sudah lengkap.
- **Backend logic** sebagian besar masih stub.
- **Payroll guru/staf** belum ada sama sekali (tabel maupun logic).

---

## 2. Arsitektur & Entitas Terkait

### Entitas yang Sudah Ada (Relevan untuk Keuangan)

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  AcademicYear   │────▶│    Semester     │     │   GradeLevel    │
└─────────────────┘     └─────────────────┘     └─────────────────┘
         │                                               │
         ▼                                               ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  FeeStructure   │────▶│    StudentFee   │◀────│     Student     │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                                │
                                ▼
                        ┌─────────────────┐
                        │     Payment     │
                        └─────────────────┘
```

### Entitas Baru untuk Payroll

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│     Teacher     │────▶│  TeacherSalary  │────▶│  PayrollPeriod  │
└─────────────────┘     │   (komponen)    │     └─────────────────┘
                        └─────────────────┘              │
                                                         ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│      Staff      │────▶│   StaffSalary   │────▶│  PayrollSlip    │
└─────────────────┘     │   (komponen)    │     │  (slip gaji)    │
                        └─────────────────┘     └─────────────────┘
```

---

## 3. Modul A: Pembayaran Siswa

### 3.1 Master Data Keuangan

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Jenis Biaya** | SPP, Uang Pangkal (UK), UPRAK, Seragam, Buku, Kegiatan | 🟢 Mudah |
| **Metode Pembayaran** | Cash, Transfer Bank, VA, E-Wallet, QRIS | 🟢 Mudah |
| **Struktur Biaya** | Tarif per tahun ajaran × kelas × jurusan | 🟡 Sedang |
| **Jenis Potongan** | Beasiswa prestasi, ekonomi, karyawan, saudara kandung | 🟡 Sedang |
| **Aturan Denda** | Persentase atau nominal per hari/minggu keterlambatan | 🟡 Sedang |

### 3.2 Penagihan (Billing)

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Generate Tagihan Massal** | Buat tagihan untuk semua siswa per bulan/semester | 🟡 Sedang |
| **Tagihan Individual** | Buat/edit tagihan untuk 1 siswa | 🟢 Mudah |
| **Status Tagihan** | unpaid → partial → paid, overdue, waived | 🟢 Mudah |
| **Hitung Denda Otomatis** | Kalkulasi denda saat jatuh tempo terlewat | 🟡 Sedang |
| **Cicilan** | Pembayaran tagihan secara bertahap | 🟡 Sedang |
| **Void/Hapus Tagihan** | Batalkan tagihan dengan alasan | 🟢 Mudah |

### 3.3 Pembayaran (Payment)

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Input Pembayaran Manual** | Catat pembayaran tunai/transfer | 🟢 Mudah |
| **Verifikasi Pembayaran** | Approval untuk bukti transfer | 🟡 Sedang |
| **Cetak Kuitansi** | Generate PDF kuitansi pembayaran | 🟢 Mudah |
| **Riwayat Pembayaran Siswa** | Lihat semua transaksi per siswa | 🟢 Mudah |
| **Pembatalan/Refund** | Cancel atau kembalikan pembayaran | 🟡 Sedang |
| **Virtual Account** | Integrasi Midtrans/Xendit | 🔴 Kompleks |
| **QRIS/E-Wallet** | Pembayaran digital real-time | 🔴 Kompleks |

### 3.4 Potongan & Beasiswa

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Definisi Beasiswa** | Nama, tipe (% / nominal), periode berlaku | 🟢 Mudah |
| **Assign Beasiswa ke Siswa** | Pilih siswa, pilih beasiswa, periode | 🟢 Mudah |
| **Potongan Otomatis** | Beasiswa auto-teraplikasi saat generate tagihan | 🟡 Sedang |
| **Laporan Penerima Beasiswa** | Daftar siswa + total potongan | 🟢 Mudah |

### 3.5 Laporan Keuangan Siswa

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Dashboard Ringkasan** | Total tagihan, terbayar, tunggakan, grafik | 🟡 Sedang |
| **Laporan Per Bulan** | Breakdown per jenis biaya per bulan | 🟢 Mudah |
| **Laporan Tunggakan** | Siswa dengan tagihan belum lunas | 🟢 Mudah |
| **Laporan Per Kelas** | Rekap pembayaran per kelas/rombel | 🟢 Mudah |
| **Export Excel/PDF** | Download laporan dalam berbagai format | 🟡 Sedang |
| **Rekonsiliasi Bank** | Cocokkan dengan mutasi rekening | 🔴 Kompleks |

### 3.6 Notifikasi & Reminder

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Reminder Jatuh Tempo** | Kirim notif H-7, H-3, H-1 sebelum due date | 🟡 Sedang |
| **Notifikasi Overdue** | Kirim pengingat tagihan tertunggak | 🟡 Sedang |
| **Konfirmasi Pembayaran** | Kirim notif saat pembayaran sukses | 🟢 Mudah |
| **Channel: WhatsApp/Telegram** | Integrasi dengan sistem notifikasi existing | 🟡 Sedang |

### 3.7 Portal Orang Tua (Self-Service)

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Lihat Tagihan Anak** | Daftar tagihan pending/overdue | 🟢 Mudah |
| **Riwayat Pembayaran** | Lihat history + download kuitansi | 🟢 Mudah |
| **Upload Bukti Transfer** | Submit bukti untuk verifikasi admin | 🟡 Sedang |
| **Bayar Online** | Redirect ke payment gateway | 🔴 Kompleks |

---

## 4. Modul B: Penggajian (Payroll) Guru/Staf

### 4.1 Komponen Gaji

#### Komponen Pendapatan (Earnings)

| Komponen | Deskripsi | Tipe |
|----------|-----------|------|
| **Gaji Pokok** | Sesuai golongan/grade | Fixed |
| **Tunjangan Jabatan** | Kepala Sekolah, Wakil, Wali Kelas, dll | Fixed |
| **Tunjangan Fungsional** | Guru tetap, sertifikasi | Fixed |
| **Tunjangan Keluarga** | Istri/suami + anak (maks 2) | Fixed |
| **Tunjangan Transport** | Per kehadiran atau flat | Variable |
| **Tunjangan Makan** | Per kehadiran atau flat | Variable |
| **Honor Mengajar** | Per jam pelajaran (untuk guru honorer) | Variable |
| **Honor Piket** | Per hari piket | Variable |
| **Honor Ekstrakurikuler** | Per kegiatan/bulan | Variable |
| **Lembur** | Jam tambahan di luar jadwal | Variable |
| **Bonus/THR** | Tahunan atau insidentil | Occasional |

#### Komponen Potongan (Deductions)

> **PENTING:** Semua tarif potongan di bawah **harus dari master data** (tabel `bpjs_rates`, `tax_brackets`, `tax_settings`, `salary_components`), **TIDAK BOLEH hardcode** di kode program. Tarif bisa berubah sewaktu-waktu sesuai regulasi.

| Komponen | Deskripsi | Sumber Tarif | Tipe |
|----------|-----------|--------------|------|
| **BPJS Kesehatan** | % dari gaji (ditanggung karyawan) | `bpjs_rates` | Mandatory |
| **BPJS JHT** | Jaminan Hari Tua | `bpjs_rates` | Mandatory |
| **BPJS JP** | Jaminan Pensiun | `bpjs_rates` | Mandatory |
| **BPJS JKK** | Jaminan Kecelakaan Kerja | `bpjs_rates` | Mandatory |
| **BPJS JKM** | Jaminan Kematian | `bpjs_rates` | Mandatory |
| **PPh 21** | Pajak penghasilan (progresif) | `tax_brackets` + `tax_settings` | Mandatory |
| **Potongan Absensi** | Per hari tidak hadir | `salary_components` | Variable |
| **Potongan Keterlambatan** | Per menit/jam terlambat | `salary_components` | Variable |
| **Pinjaman/Kasbon** | Cicilan pinjaman karyawan | `employee_loans` | Variable |
| **Iuran Koperasi** | Jika ada koperasi sekolah | `salary_components` | Optional |
| **Zakat** | % jika opt-in | `salary_components` | Optional |
| **Potongan Lainnya** | Custom per kebutuhan | `salary_components` | Optional |

### 4.2 Master Data Payroll

| Fitur | Deskripsi | Tabel | Kompleksitas |
|-------|-----------|-------|--------------|
| **Golongan/Grade Gaji** | Level I, II, III, IV dengan range gaji | `salary_grades` | 🟢 Mudah |
| **Komponen Gaji** | Definisi tunjangan & potongan custom | `salary_components` | 🟢 Mudah |
| **Tarif BPJS** | Kesehatan, JHT, JKK, JKM, JP | `bpjs_rates` | 🟢 Mudah |
| **Bracket PPh 21** | Tarif progresif per range PKP | `tax_brackets` | 🟢 Mudah |
| **PTKP & Pengaturan Pajak** | TK/0, K/0, K/1, K/2, K/3, dll | `tax_settings` | 🟢 Mudah |
| **Template Gaji** | Preset komponen per tipe karyawan | `salary_templates` | 🟡 Sedang |
| **Kalender Kerja** | Hari kerja, libur, cuti bersama | `work_calendars` | 🟢 Mudah |

> **Catatan:** Setiap kali ada perubahan regulasi (tarif BPJS naik, bracket pajak berubah), cukup update master data — tidak perlu ubah kode program.

### 4.3 Alur Kalkulasi Gaji (Menggunakan Master Data)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        PROSES HITUNG GAJI                           │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 1. AMBIL DATA KARYAWAN                                              │
│    - Gaji pokok dari `employee_salaries` → `salary_grades`          │
│    - Status PTKP (TK/K + jumlah tanggungan) dari profil karyawan    │
│    - Komponen aktif dari `employee_salary_components`               │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 2. HITUNG PENDAPATAN (dari `salary_components` type='earning')      │
│    - Gaji pokok (fixed)                                             │
│    - Tunjangan jabatan, fungsional, keluarga (fixed)                │
│    - Tunjangan kehadiran (variable, dari data attendance)           │
│    - Honor, lembur (variable, input manual)                         │
│    ─────────────────────────────────────────────────────────────    │
│    TOTAL PENDAPATAN BRUTO                                           │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 3. HITUNG POTONGAN BPJS (dari `bpjs_rates`)                         │
│    - BPJS Kesehatan = bruto × employee_rate (max ceiling)           │
│    - BPJS JHT = bruto × employee_rate                               │
│    - BPJS JP = bruto × employee_rate (max ceiling)                  │
│    (JKK & JKM ditanggung perusahaan, tidak potong karyawan)         │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 4. HITUNG PPh 21 (dari `tax_brackets` + `tax_settings`)             │
│    a. Penghasilan Bruto Setahun = bruto × 12                        │
│    b. Biaya Jabatan = 5% × bruto (max 6 juta/tahun)                 │
│    c. Iuran BPJS (yang bisa dikurangi)                              │
│    d. Penghasilan Neto = a - b - c                                  │
│    e. PTKP = ambil dari `tax_settings` sesuai status                │
│    f. PKP (Penghasilan Kena Pajak) = d - e                          │
│    g. PPh 21 Setahun = hitung pakai `tax_brackets` (progresif)      │
│    h. PPh 21 Sebulan = g ÷ 12                                       │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 5. POTONGAN LAINNYA (dari `salary_components` type='deduction')     │
│    - Potongan absensi/keterlambatan (dari data attendance)          │
│    - Cicilan pinjaman (dari `loan_installments`)                    │
│    - Iuran koperasi, zakat, dll (dari komponen)                     │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 6. HASIL AKHIR                                                      │
│    GAJI BERSIH = TOTAL PENDAPATAN - TOTAL POTONGAN                  │
│    Simpan ke `payroll_slips` + detail ke `payroll_slip_items`       │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.4 Setup Gaji Karyawan

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Assign Gaji Pokok** | Per guru/staf sesuai golongan | 🟢 Mudah |
| **Assign Tunjangan** | Pilih tunjangan yang berlaku | 🟢 Mudah |
| **Set Status PTKP** | TK/0, K/0, K/1, K/2, K/3 untuk hitung pajak | 🟢 Mudah |
| **Riwayat Perubahan Gaji** | Log setiap kenaikan/perubahan | 🟡 Sedang |
| **Import Gaji Massal** | Upload Excel untuk setup awal | 🟡 Sedang |

### 4.5 Proses Penggajian Bulanan

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Buat Periode Gaji** | Periode bulanan (1-30/31) | 🟢 Mudah |
| **Hitung Otomatis dari Absensi** | Ambil data kehadiran dari modul attendance | 🟡 Sedang |
| **Input Manual Variabel** | Honor, lembur, potongan khusus | 🟡 Sedang |
| **Preview Slip Gaji** | Review sebelum finalisasi | 🟢 Mudah |
| **Approval Payroll** | Persetujuan Kepala Sekolah / Yayasan | 🟡 Sedang |
| **Finalisasi & Lock** | Kunci periode, tidak bisa diubah | 🟢 Mudah |
| **Generate Slip Gaji PDF** | Cetak slip per karyawan | 🟢 Mudah |

### 4.6 Pembayaran Gaji

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Pembayaran Manual** | Catat pembayaran cash/transfer | 🟢 Mudah |
| **Export untuk Bank** | File transfer massal (CSV/TXT) | 🟡 Sedang |
| **Integrasi Bank API** | Auto-transfer ke rekening karyawan | 🔴 Kompleks |
| **Notifikasi Slip Gaji** | Kirim slip via email/WA | 🟡 Sedang |

### 4.7 Pinjaman Karyawan

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Pengajuan Pinjaman** | Request dari karyawan | 🟡 Sedang |
| **Approval Pinjaman** | Persetujuan atasan | 🟡 Sedang |
| **Setup Cicilan** | Tenor, nominal per bulan | 🟢 Mudah |
| **Auto-Potong Gaji** | Cicilan otomatis terpotong | 🟡 Sedang |
| **Riwayat Pinjaman** | Track semua pinjaman per karyawan | 🟢 Mudah |

### 4.8 Laporan Payroll

| Fitur | Deskripsi | Kompleksitas |
|-------|-----------|--------------|
| **Rekap Gaji Bulanan** | Total per komponen, per departemen | 🟢 Mudah |
| **Laporan PPh 21** | Untuk pelaporan pajak | 🟡 Sedang |
| **Laporan BPJS** | Untuk pelaporan BPJS | 🟡 Sedang |
| **Slip Gaji Tahunan** | Rekap setahun per karyawan | 🟡 Sedang |
| **Proyeksi Anggaran** | Estimasi biaya gaji periode depan | 🟡 Sedang |
| **Export Excel/PDF** | Download semua laporan | 🟢 Mudah |

---

## 5. Roadmap Implementasi (Urut Termudah)

### Legend Kompleksitas

- 🟢 **Mudah**: CRUD sederhana, sedikit logic
- 🟡 **Sedang**: Logic bisnis, kalkulasi, relasi kompleks
- 🔴 **Kompleks**: Integrasi eksternal, workflow kompleks

---

### Phase 1: Foundation (Master Data)

> Target: Master data & CRUD dasar bisa dipakai

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 1.1 | **CRUD Metode Pembayaran** (payment_methods) | 🟢 Mudah | - |
| 1.2 | **CRUD Struktur Biaya** (fee_structures) | 🟢 Mudah | fee_types |
| 1.3 | **CRUD Jenis Potongan/Beasiswa** (discounts) | 🟢 Mudah | - |
| 1.4 | **CRUD Golongan Gaji** (salary_grades) | 🟢 Mudah | - |
| 1.5 | **CRUD Komponen Gaji** (salary_components) | 🟢 Mudah | - |
| 1.6 | **CRUD Tarif BPJS** (bpjs_rates) | 🟢 Mudah | - |
| 1.7 | **CRUD Tarif PPh 21** (tax_brackets) | 🟢 Mudah | - |
| 1.8 | **CRUD Pengaturan Pajak** (tax_settings: PTKP, dll) | 🟢 Mudah | - |
| 1.9 | **UI: Halaman Master Keuangan Siswa** | 🟢 Mudah | 1.1-1.3 |
| 1.10 | **UI: Halaman Master Payroll** | 🟢 Mudah | 1.4-1.8 |

**Deliverable:** Admin bisa setup jenis biaya, tarif, metode bayar, komponen gaji, tarif BPJS & pajak.

---

### Phase 2: Student Billing

> Target: Generate tagihan & input pembayaran manual

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 2.1 | **Tagihan Individual** — create/edit/void | 🟢 Mudah | Phase 1 |
| 2.2 | **List Tagihan** — filter status, kelas, periode | 🟢 Mudah | 2.1 |
| 2.3 | **Input Pembayaran Manual** — cash/transfer | 🟢 Mudah | 2.1 |
| 2.4 | **Cetak Kuitansi PDF** | 🟢 Mudah | 2.3 |
| 2.5 | **Riwayat Pembayaran per Siswa** | 🟢 Mudah | 2.3 |
| 2.6 | **Generate Tagihan Massal** — per bulan/semester | 🟡 Sedang | 2.1 |
| 2.7 | **Assign Beasiswa ke Siswa** | 🟢 Mudah | 1.3 |
| 2.8 | **Potongan Otomatis saat Generate** | 🟡 Sedang | 2.6, 2.7 |
| 2.9 | **UI: Halaman Tagihan & Pembayaran** | 🟡 Sedang | 2.1-2.5 |

**Deliverable:** Bendahara bisa generate tagihan, input pembayaran, cetak kuitansi.

---

### Phase 3: Employee Salary Setup

> Target: Setup gaji per guru/staf

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 3.1 | **Assign Gaji Pokok per Guru/Staf** | 🟢 Mudah | 1.5 |
| 3.2 | **Assign Tunjangan per Guru/Staf** | 🟢 Mudah | 1.6 |
| 3.3 | **Template Gaji** — preset untuk tipe karyawan | 🟡 Sedang | 1.6 |
| 3.4 | **Import Gaji Massal (Excel)** | 🟡 Sedang | 3.1-3.2 |
| 3.5 | **UI: Halaman Setup Gaji Karyawan** | 🟡 Sedang | 3.1-3.3 |

**Deliverable:** Admin bisa setup gaji per karyawan.

---

### Phase 4: Payroll Processing

> Target: Proses gaji bulanan end-to-end

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 4.1 | **Buat Periode Gaji** (payroll_periods) | 🟢 Mudah | - |
| 4.2 | **Generate Slip Gaji** — dari komponen fixed | 🟡 Sedang | Phase 3 |
| 4.3 | **Hitung dari Absensi** — kehadiran, terlambat | 🟡 Sedang | 4.2, Attendance Module |
| 4.4 | **Input Variabel** — honor, lembur, potongan | 🟡 Sedang | 4.2 |
| 4.5 | **Preview & Edit Slip** | 🟢 Mudah | 4.2-4.4 |
| 4.6 | **Approval Workflow** | 🟡 Sedang | 4.5 |
| 4.7 | **Finalisasi & Lock Periode** | 🟢 Mudah | 4.6 |
| 4.8 | **Cetak Slip Gaji PDF** | 🟢 Mudah | 4.7 |
| 4.9 | **UI: Halaman Proses Payroll** | 🟡 Sedang | 4.1-4.8 |

**Deliverable:** Proses gaji bulanan lengkap dengan slip PDF.

---

### Phase 5: Reports & Dashboard

> Target: Laporan keuangan & dashboard

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 5.1 | **Dashboard Ringkasan Keuangan Siswa** | 🟡 Sedang | Phase 2 |
| 5.2 | **Laporan Tunggakan** | 🟢 Mudah | Phase 2 |
| 5.3 | **Laporan Per Kelas** | 🟢 Mudah | Phase 2 |
| 5.4 | **Laporan Bulanan Siswa** | 🟢 Mudah | Phase 2 |
| 5.5 | **Rekap Gaji Bulanan** | 🟢 Mudah | Phase 4 |
| 5.6 | **Laporan PPh 21** | 🟡 Sedang | Phase 4 |
| 5.7 | **Laporan BPJS** | 🟡 Sedang | Phase 4 |
| 5.8 | **Export Excel semua laporan** | 🟡 Sedang | 5.1-5.7 |
| 5.9 | **UI: Halaman Laporan Keuangan** | 🟡 Sedang | 5.1-5.8 |

**Deliverable:** Dashboard & laporan lengkap dengan export.

---

### Phase 6: Advanced Billing

> Target: Fitur billing lanjutan

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 6.1 | **Aturan Denda Keterlambatan** | 🟡 Sedang | Phase 2 |
| 6.2 | **Hitung Denda Otomatis** | 🟡 Sedang | 6.1 |
| 6.3 | **Pembayaran Cicilan** | 🟡 Sedang | Phase 2 |
| 6.4 | **Verifikasi Pembayaran Transfer** | 🟡 Sedang | Phase 2 |
| 6.5 | **Pembatalan/Refund** | 🟡 Sedang | Phase 2 |
| 6.6 | **UI: Update Halaman Pembayaran** | 🟡 Sedang | 6.1-6.5 |

**Deliverable:** Denda otomatis, cicilan, verifikasi transfer.

---

### Phase 7: Loan Management

> Target: Pinjaman karyawan

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 7.1 | **CRUD Pinjaman** | 🟡 Sedang | - |
| 7.2 | **Approval Workflow Pinjaman** | 🟡 Sedang | 7.1 |
| 7.3 | **Setup Cicilan** | 🟢 Mudah | 7.1 |
| 7.4 | **Auto-Potong di Slip Gaji** | 🟡 Sedang | 7.3, Phase 4 |
| 7.5 | **UI: Halaman Pinjaman** | 🟡 Sedang | 7.1-7.4 |

**Deliverable:** Kelola pinjaman karyawan dengan auto-potong.

---

### Phase 8: Notifications

> Target: Notifikasi otomatis

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 8.1 | **Reminder Jatuh Tempo Tagihan** | 🟡 Sedang | Phase 2 |
| 8.2 | **Notifikasi Tagihan Overdue** | 🟡 Sedang | Phase 2 |
| 8.3 | **Konfirmasi Pembayaran** | 🟢 Mudah | Phase 2 |
| 8.4 | **Notifikasi Slip Gaji** | 🟢 Mudah | Phase 4 |
| 8.5 | **Integrasi WhatsApp/Telegram** | 🟡 Sedang | Notification Module |

**Deliverable:** Notifikasi otomatis via WA/Telegram.

---

### Phase 9: Parent Portal

> Target: Self-service untuk orang tua

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 9.1 | **Lihat Tagihan Anak** | 🟢 Mudah | Phase 2 |
| 9.2 | **Riwayat Pembayaran** | 🟢 Mudah | Phase 2 |
| 9.3 | **Download Kuitansi** | 🟢 Mudah | 2.4 |
| 9.4 | **Upload Bukti Transfer** | 🟡 Sedang | 6.4 |
| 9.5 | **UI: Portal Orang Tua** | 🟡 Sedang | 9.1-9.4 |

**Deliverable:** Orang tua bisa cek tagihan & upload bukti bayar.

---

### Phase 10: Payment Gateway

> Target: Pembayaran online

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 10.1 | **Integrasi Midtrans/Xendit** | 🔴 Kompleks | Phase 2 |
| 10.2 | **Virtual Account** | 🔴 Kompleks | 10.1 |
| 10.3 | **QRIS** | 🔴 Kompleks | 10.1 |
| 10.4 | **Webhook Handler** | 🔴 Kompleks | 10.1 |
| 10.5 | **Rekonsiliasi Otomatis** | 🔴 Kompleks | 10.4 |
| 10.6 | **UI: Pembayaran Online** | 🟡 Sedang | 10.1-10.5 |

**Deliverable:** Bayar tagihan via VA/QRIS dari portal orang tua.

---

### Phase 11: Bank Integration

> Target: Integrasi bank untuk payroll

| No | Task | Kompleksitas | Dependensi |
|----|------|--------------|------------|
| 11.1 | **Export File Transfer Bank** (BCA, Mandiri, BRI) | 🟡 Sedang | Phase 4 |
| 11.2 | **Rekonsiliasi Bank Statement** | 🔴 Kompleks | Phase 2 |
| 11.3 | **Bank API Integration** (opsional) | 🔴 Kompleks | 11.1 |

**Deliverable:** Transfer gaji massal via file/API bank.

---

## 6. Struktur Menu yang Disarankan

```
Keuangan
├── Dashboard                    # Ringkasan + grafik (Phase 5)
│
├── Pembayaran Siswa
│   ├── Jenis Biaya              # fee_types (✅ sudah ada)
│   ├── Struktur Biaya           # fee_structures (Phase 1)
│   ├── Metode Pembayaran        # payment_methods (Phase 1)
│   ├── Potongan/Beasiswa        # discounts (Phase 1)
│   ├── Tagihan                  # student_fees (Phase 2)
│   ├── Pembayaran               # payments (Phase 2)
│   └── Verifikasi Transfer      # (Phase 6)
│
├── Penggajian
│   ├── Master Data
│   │   ├── Golongan Gaji        # salary_grades (Phase 1)
│   │   ├── Komponen Gaji        # salary_components (Phase 1)
│   │   ├── Tarif BPJS           # bpjs_rates (Phase 1)
│   │   ├── Tarif PPh 21         # tax_brackets (Phase 1)
│   │   └── Pengaturan PTKP      # tax_settings (Phase 1)
│   ├── Setup Gaji Karyawan      # employee_salaries (Phase 3)
│   ├── Proses Gaji Bulanan      # payroll_periods (Phase 4)
│   ├── Slip Gaji                # payroll_slips (Phase 4)
│   └── Pinjaman Karyawan        # loans (Phase 7)
│
├── Laporan
│   ├── Tagihan & Pembayaran     # (Phase 5)
│   ├── Tunggakan                # (Phase 5)
│   ├── Rekap Gaji               # (Phase 5)
│   ├── PPh 21                   # (Phase 5)
│   └── BPJS                     # (Phase 5)
│
└── Pengaturan
    ├── Aturan Denda             # (Phase 6)
    └── Notifikasi               # (Phase 8)
```

---

## 7. Estimasi Tabel Database Baru

### Tabel Master Tarif BPJS & Pajak (WAJIB - Tidak Hardcode)

> **PENTING:** Semua tarif potongan wajib (BPJS, PPh 21) harus disimpan di database, bukan hardcode. Tarif bisa berubah sewaktu-waktu sesuai regulasi pemerintah.

```sql
-- Tarif BPJS (Kesehatan & Ketenagakerjaan)
bpjs_rates (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    type ENUM('kesehatan', 'jht', 'jkk', 'jkm', 'jp'),
    name VARCHAR(100),              -- 'BPJS Kesehatan', 'JHT', 'JKK', dll

    -- Tarif dalam persentase
    employee_rate DECIMAL(5,2),     -- Ditanggung karyawan (%)
    employer_rate DECIMAL(5,2),     -- Ditanggung perusahaan (%)

    -- Batas gaji untuk perhitungan
    min_salary DECIMAL(15,2) NULL,  -- Batas bawah (UMR)
    max_salary DECIMAL(15,2) NULL,  -- Batas atas (ceiling)

    -- Periode berlaku
    effective_from DATE,
    effective_until DATE NULL,

    is_active BOOLEAN DEFAULT true,
    notes TEXT NULL,
    created_at, updated_at
)

-- Contoh data BPJS Kesehatan 2024:
-- employee_rate = 1%, employer_rate = 4%, max_salary = 12.000.000

-- Bracket Tarif PPh 21 (Progresif)
tax_brackets (
    id UUID PRIMARY KEY,
    tenant_id UUID,

    -- Range penghasilan kena pajak (PKP)
    min_amount DECIMAL(15,2),       -- Batas bawah PKP
    max_amount DECIMAL(15,2) NULL,  -- Batas atas (NULL = tidak terbatas)

    -- Tarif pajak
    rate DECIMAL(5,2),              -- Persentase pajak

    -- Periode berlaku
    effective_year INT,             -- Tahun pajak
    effective_from DATE,
    effective_until DATE NULL,

    is_active BOOLEAN DEFAULT true,
    created_at, updated_at
)

-- Contoh bracket PPh 21 tahun 2024:
-- 0 - 60.000.000         : 5%
-- 60.000.000 - 250.000.000 : 15%
-- 250.000.000 - 500.000.000 : 25%
-- 500.000.000 - 5.000.000.000 : 30%
-- > 5.000.000.000        : 35%

-- Pengaturan Pajak (PTKP, TER, dll)
tax_settings (
    id UUID PRIMARY KEY,
    tenant_id UUID,

    setting_key VARCHAR(50),        -- 'ptkp_tk0', 'ptkp_k0', 'ptkp_k1', dll
    setting_name VARCHAR(100),      -- 'PTKP TK/0 (Tidak Kawin)', dll
    setting_value DECIMAL(15,2),    -- Nilai nominal

    category VARCHAR(50),           -- 'ptkp', 'ter', 'other'
    description TEXT NULL,

    effective_year INT,
    effective_from DATE,
    effective_until DATE NULL,

    is_active BOOLEAN DEFAULT true,
    created_at, updated_at
)

-- Contoh PTKP 2024:
-- TK/0 = 54.000.000/tahun
-- K/0  = 58.500.000/tahun
-- K/1  = 63.000.000/tahun
-- K/2  = 67.500.000/tahun
-- K/3  = 72.000.000/tahun
```

---

### Tabel untuk Payroll (Belum Ada)

```sql
-- Golongan/Grade Gaji
salary_grades (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    code VARCHAR(20),           -- 'I-A', 'II-B', 'III-C'
    name VARCHAR(100),          -- 'Golongan I-A'
    base_salary DECIMAL(15,2),  -- Gaji pokok
    is_active BOOLEAN,
    created_at, updated_at
)

-- Komponen Gaji (Tunjangan & Potongan)
salary_components (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    code VARCHAR(20),           -- 'TJ-JABATAN', 'POT-BPJS'
    name VARCHAR(100),          -- 'Tunjangan Jabatan'
    type ENUM('earning', 'deduction'),
    calculation_type ENUM('fixed', 'percentage', 'per_day', 'per_hour'),
    default_value DECIMAL(15,2),
    percentage_of VARCHAR(50),  -- 'base_salary', 'gross_salary'
    is_taxable BOOLEAN,
    is_mandatory BOOLEAN,
    is_active BOOLEAN,
    created_at, updated_at
)

-- Setup Gaji per Karyawan
employee_salaries (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    employee_type ENUM('teacher', 'staff'),
    employee_id UUID,           -- FK ke teachers/staff
    salary_grade_id UUID,
    base_salary DECIMAL(15,2),  -- Override dari grade
    effective_date DATE,
    end_date DATE NULL,
    is_current BOOLEAN,
    created_at, updated_at
)

-- Komponen Gaji per Karyawan
employee_salary_components (
    id UUID PRIMARY KEY,
    employee_salary_id UUID,
    salary_component_id UUID,
    value DECIMAL(15,2),        -- Override dari default
    is_active BOOLEAN,
    created_at, updated_at
)

-- Periode Penggajian
payroll_periods (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    name VARCHAR(100),          -- 'Januari 2025'
    year INT,
    month INT,
    start_date DATE,
    end_date DATE,
    status ENUM('draft', 'processing', 'pending_approval', 'approved', 'paid', 'closed'),
    approved_by UUID NULL,
    approved_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    total_earnings DECIMAL(15,2),
    total_deductions DECIMAL(15,2),
    total_net DECIMAL(15,2),
    employee_count INT,
    created_at, updated_at
)

-- Slip Gaji per Karyawan per Periode
payroll_slips (
    id UUID PRIMARY KEY,
    payroll_period_id UUID,
    employee_type ENUM('teacher', 'staff'),
    employee_id UUID,
    employee_salary_id UUID,

    -- Summary
    base_salary DECIMAL(15,2),
    total_earnings DECIMAL(15,2),
    total_deductions DECIMAL(15,2),
    net_salary DECIMAL(15,2),

    -- Attendance data
    working_days INT,
    present_days INT,
    absent_days INT,
    late_count INT,
    late_minutes INT,

    -- Status
    status ENUM('draft', 'approved', 'paid'),
    paid_at TIMESTAMP NULL,
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),

    notes TEXT NULL,
    created_at, updated_at
)

-- Detail Komponen per Slip
payroll_slip_items (
    id UUID PRIMARY KEY,
    payroll_slip_id UUID,
    salary_component_id UUID,
    type ENUM('earning', 'deduction'),
    name VARCHAR(100),
    calculation_type VARCHAR(50),
    base_value DECIMAL(15,2),
    calculated_value DECIMAL(15,2),
    notes VARCHAR(255) NULL,
    created_at, updated_at
)

-- Pinjaman Karyawan
employee_loans (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    employee_type ENUM('teacher', 'staff'),
    employee_id UUID,
    loan_number VARCHAR(50),
    amount DECIMAL(15,2),
    interest_rate DECIMAL(5,2),
    tenure_months INT,
    monthly_installment DECIMAL(15,2),
    start_date DATE,
    end_date DATE,
    status ENUM('pending', 'approved', 'rejected', 'active', 'completed', 'cancelled'),
    approved_by UUID NULL,
    approved_at TIMESTAMP NULL,
    reason TEXT NULL,
    notes TEXT NULL,
    created_at, updated_at
)

-- Cicilan Pinjaman
loan_installments (
    id UUID PRIMARY KEY,
    employee_loan_id UUID,
    installment_number INT,
    due_date DATE,
    amount DECIMAL(15,2),
    paid_amount DECIMAL(15,2),
    status ENUM('pending', 'paid', 'partial', 'overdue'),
    payroll_slip_id UUID NULL,  -- Jika dipotong dari gaji
    paid_at TIMESTAMP NULL,
    created_at, updated_at
)

-- Riwayat Perubahan Gaji
salary_histories (
    id UUID PRIMARY KEY,
    employee_salary_id UUID,
    changed_by UUID,
    change_type ENUM('initial', 'promotion', 'adjustment', 'demotion'),
    old_grade_id UUID NULL,
    new_grade_id UUID NULL,
    old_base_salary DECIMAL(15,2) NULL,
    new_base_salary DECIMAL(15,2),
    effective_date DATE,
    reason TEXT NULL,
    created_at
)
```

### Tabel Tambahan untuk Billing (Opsional)

```sql
-- Aturan Denda
late_fee_rules (
    id UUID PRIMARY KEY,
    tenant_id UUID,
    fee_type_id UUID NULL,      -- NULL = berlaku untuk semua
    calculation_type ENUM('fixed', 'percentage', 'daily_fixed', 'daily_percentage'),
    value DECIMAL(15,2),
    max_amount DECIMAL(15,2) NULL,
    grace_period_days INT DEFAULT 0,
    is_active BOOLEAN,
    created_at, updated_at
)

-- Cicilan Pembayaran Siswa
payment_installments (
    id UUID PRIMARY KEY,
    student_fee_id UUID,
    installment_number INT,
    amount DECIMAL(15,2),
    due_date DATE,
    paid_amount DECIMAL(15,2),
    status ENUM('pending', 'paid', 'partial', 'overdue'),
    payment_id UUID NULL,       -- Jika sudah dibayar
    paid_at TIMESTAMP NULL,
    created_at, updated_at
)
```

---

## Catatan Implementasi

### Role & Permission

Tambahkan permission baru:

```php
// Pembayaran Siswa
'finance.fee-types.manage',
'finance.fee-structures.manage',
'finance.student-fees.manage',
'finance.payments.manage',
'finance.payments.verify',
'finance.discounts.manage',

// Payroll
'payroll.grades.manage',
'payroll.components.manage',
'payroll.employee-salary.manage',
'payroll.process.manage',
'payroll.process.approve',
'payroll.loans.manage',
'payroll.loans.approve',

// Reports
'finance.reports.view',
'payroll.reports.view',
```

### Integrasi dengan Modul Existing

1. **Attendance Module** → Payroll: Ambil data kehadiran untuk hitung tunjangan kehadiran & potongan absensi.
2. **Teacher Module** → Payroll: Relasi ke employee_salaries.
3. **Student Module** → Billing: Relasi ke student_fees.
4. **Notification Module** → Finance: Kirim reminder & konfirmasi.

---

## Referensi

- [Spesifikasi BPJS Kesehatan](https://www.bpjs-kesehatan.go.id/)
- [Tarif PPh 21 Terbaru](https://www.pajak.go.id/)
- [Format File Transfer BCA](https://www.bca.co.id/id/bisnis/produk-dan-layanan/cash-management)
- [Midtrans API Documentation](https://docs.midtrans.com/)
- [Xendit API Documentation](https://developers.xendit.co/)
