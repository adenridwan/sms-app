# Analisa: Cetak/Generate QR & RFID Otomatis + Export untuk Pembuatan Kartu

> Status: **ANALISA (belum ada eksekusi kode).** Fokus: kebutuhan meng-generate
> QR/RFID otomatis dan meng-**export** hasilnya agar kartu bisa dibuat/didesain
> ulang di tempat lain (vendor). Path relatif dari root `sms-app/`.

---

## 0. Ringkasan

Yang diminta:
1. **Cetak/generate QR code atau RFID otomatis.**
2. **Export hasil QR/RFID** ke file agar kartu bisa dibuat/didesain ulang di
   luar aplikasi (vendor percetakan / desainer).

Temuan: **generate QR sudah otomatis**, **RFID masih manual**, dan **belum ada
export file batch** — yang ada baru download per-item + cetak dari dalam aplikasi.
Export batch inilah gap utamanya.

---

## 1. Kondisi Saat Ini

### 1.1 QR Code — sudah otomatis
- `unique_code` (payload QR) di-generate otomatis: `STU-` / `TCH-` + 12 karakter
  acak, dibuat lazily saat QR diminta, unik.
  `QrCodeGeneratorService.php` + `QrCodeController.php`.
- Gambar QR = **SVG** (base64 data URI). PNG sengaja tidak dipakai karena butuh
  ekstensi `imagick` yang tidak tersedia (lihat komentar di service).
- Endpoint tersedia: QR per siswa/guru, **bulk per kelas**, bulk semua guru,
  download, regenerate. Halaman `/attendance/qr-codes`.

### 1.2 RFID — masih MANUAL
- `rfid_code` di-assign manual lewat `RfidController` (admin mengetik kode dari
  kartu fisik), unik via rule `UniqueRfidCode`. **Tidak ada** auto-generate.
- Ini wajar: kode RFID biasanya berasal dari chip kartu fisik. "RFID otomatis"
  perlu diklarifikasi (lihat §4).

### 1.3 Kartu — cetak dari dalam app (bukan export file)
- Editor template kartu drag-and-drop (`card_templates`, Fase 5) +
  `attendanceCardPrint.ts` → cetak via browser (popup print).
- Halaman QR: tombol **Download** per item & **Cetak Semua** (client-side).
- **Gap**: download per-item menamai file `.png` padahal isinya SVG
  (`qr-codes/Index.tsx:113`) — minor bug penamaan.

### 1.4 Yang BELUM ada
- **Export batch ke file** untuk vendor: tidak ada CSV/Excel daftar kode, tidak
  ada ZIP kumpulan gambar QR, tidak ada PDF lembar cetak siap kirim.

---

## 2. Kebutuhan Export (inti permintaan)

Agar kartu bisa dibuat/didesain ulang di tempat lain, sediakan **3 bentuk export**
(pilih sesuai kebutuhan vendor):

| Bentuk | Isi | Untuk |
|---|---|---|
| **A. Data (CSV/Excel)** | NIS/NIP, nama, kelas, `unique_code` (payload QR), `rfid_code`, status | Vendor generate/desain sendiri; mail-merge |
| **B. ZIP gambar QR** | File QR per orang (SVG dan/atau PNG), dinamai `NIS_nama` | Desainer tinggal drop ke template kartu |
| **C. PDF siap cetak** | Lembar kartu (QR + foto + identitas) pakai template | Cetak massal langsung |

> Rekomendasi mulai: **A + B** (paling fleksibel untuk vendor). C menyusul
> memakai template yang sudah ada.

---

## 3. Rencana Implementasi (garis besar)

### 3.1 Backend
- **QR generate otomatis massal**: aksi "generate untuk semua yang belum punya
  `unique_code`" (siswa per kelas / seluruh sekolah, guru). Sebagian sudah ada
  di bulk endpoint — tinggal disatukan jadi aksi eksplisit.
- **Export Data (A)**: `GET /attendance/qr/export` (scope: classroom_id / all,
  role: siswa/guru) → Excel/CSV via Laravel Excel (pola sudah dipakai:
  `app/Exports/...`). Kolom: identitas + `unique_code` + `rfid_code`.
- **Export ZIP (B)**: `GET /attendance/qr/export-zip` → buat QR (SVG; PNG jika
  `imagick`/driver GD tersedia) untuk tiap orang, bundel `ZipArchive`, stream
  sebagai download. Perlu penamaan file aman (NIS + slug nama).
- **Export PDF (C)**: render Blade + template kartu → PDF (paket PDF yang sudah
  dipakai untuk laporan absensi). Reuse layout `card_templates`.
- **Guard akses**: permission (mis. `attendance.manage` / `students.export`).
  Konsisten dgn export lain.

### 3.2 Frontend (`/attendance/qr-codes`)
- Tombol **Export** dengan menu: Excel/CSV, ZIP Gambar, PDF.
- Perbaiki bug penamaan download SVG-vs-PNG (§1.3).
- Opsi filter: per kelas / semua, siswa / guru, ukuran QR.

### 3.3 Catatan teknis
- **SVG vs PNG**: SVG jalan tanpa `imagick`. Untuk vendor yang butuh raster,
  aktifkan driver GD (`QrCode::format('png')` bisa pakai GD via `imagick`? →
  perlu cek; kalau tidak, sediakan SVG saja + catatan konversi). Ini keputusan
  dependency (lihat §4).
- **Ukuran & margin** QR agar ter-scan andal saat dicetak kecil.

---

## 4. Keputusan yang Perlu Dijawab
- [ ] **RFID "otomatis" maksudnya apa?**
      (a) tetap manual dari kartu fisik (hanya di-export), atau
      (b) sistem meng-generate kode untuk ditulis ke kartu RFID writable, atau
      (c) cukup QR saja (RFID belakangan)?
- [ ] **Bentuk export** yang diprioritaskan: A (data), B (ZIP gambar), C (PDF)?
- [ ] **Format gambar**: cukup **SVG**, atau wajib **PNG** (butuh keputusan
      dependency image di server)?
- [ ] **Scope export**: per kelas, per angkatan, atau seluruh sekolah sekaligus?

---

## 5. Terkait — Status Tombol Import/Export (hasil pengecekan)

| Menu | Export | Import | Keterangan |
|---|---|---|---|
| **Jurusan** (Akademik) | ✅ | ✅ | Route + `MajorsExport/Import` + handler FE lengkap |
| **Kelas** (Akademik) | ✅ | ✅ | Route + `ClassroomsExport/Import` + handler FE lengkap |
| **Siswa** | ❌ | ❌ | Tombol **ADA tapi MATI** — `<Button>` tanpa `onClick`, tanpa route/Export class backend (`students/Index.tsx:165-172`) |
| **Guru** | — | — | Tidak ada tombol export/import sama sekali |
| **Absensi (laporan)** | ✅ | — | Export Excel/PDF laporan bulanan berfungsi |

> Kesimpulan: hanya **Jurusan & Kelas** yang berfungsi. Tombol **Export/Import
> Siswa palsu** (placeholder) dan perlu diimplementasikan (backend
> `StudentsExport`/`StudentsImport` + route + wiring tombol) bila memang
> dibutuhkan. Ini bisa disatukan dengan kebutuhan export QR/RFID di atas.
