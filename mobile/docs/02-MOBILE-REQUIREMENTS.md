# 02 — Mobile Requirements (Flutter)

> Definisi kebutuhan aplikasi **mobile Flutter** untuk SMS Enterprise,
> diturunkan dari aplikasi Laravel & API v1 yang sudah ada
> (lihat `00-PROJECT-CONTEXT.md`, `01-EXISTING-SYSTEM.md`, `03-API-CONTRACT.md`).
> Fase analisis — **belum ada kode**.

Tanggal: 2026-07-29 · Target rilis: MVP-1 (Absensi Siswa)

---

## 1. Tujuan & Ruang Lingkup

Membangun aplikasi mobile **Flutter** (Android prioritas, iOS menyusul) untuk
petugas sekolah melakukan **absensi siswa** secara cepat via **QR Code** dan
**Student Ref ID**, dengan umpan balik langsung dan notifikasi WhatsApp ke orang
tua (dipicu backend).

**Dalam lingkup MVP-1:**
- Login & sesi (Sanctum Bearer token).
- Dashboard ringkas (status hari ini, jumlah tercatat).
- Absensi siswa: **scan QR** + **input Ref ID manual**, masuk & pulang.
- Validasi siswa (pratinjau) + submit + hasil sukses/gagal.
- Antrean & sinkron **offline**.
- Rekap absensi hari ini (read-only).

**Di luar lingkup MVP-1** (dipertimbangkan fase lanjut): input nilai, jadwal,
keuangan, perpustakaan, self-service siswa/orang tua (`/me/*`), manajemen data
master, push notification (FCM).

---

## 2. Pengguna Utama & Peran

| Persona | Role backend | Hak pada MVP |
|---|---|---|
| **Administrator** | `admin` | Semua fitur absensi + rekap penuh 1 sekolah |
| **Guru / Wali Kelas** | `guru`, `wali_kelas` | Scan absensi, rekap kelas terkait |
| **Staf Sekolah** | `staff`, `tata_usaha` | Scan absensi (petugas gerbang/piket) |

- Semua persona terikat **satu tenant** (`tenant_id` pada akun) → **tidak**
  mengirim `X-Tenant-ID`.
- Permission relevan yang sudah ada: `attendance.record`, `attendance.check-in`,
  `attendance.check-out`, `attendance.view`, `attendance.report`.
  > ⚠️ Catatan: endpoint `/scan/*` saat ini **hanya** di-guard `auth:sanctum`
  > (belum ada gate permission). Semua user terautentikasi dalam tenant bisa
  > memakainya. Rekomendasi: tambahkan `permission:attendance.record` di backend
  > sebelum rilis (lihat §9 Risiko).

---

## 3. Definisi Metode Absensi

| Metode | Sumber kode | Field API |
|---|---|---|
| **QR Code** | Kamera memindai QR berisi `unique_code` siswa (format `STU-XXXXXXXXXXXX`) | `unique_code` |
| **Student Ref ID** | Input manual: `unique_code` **atau** `rfid_code` (`RF-XXXXXXXXXX`) diketik/tempel RFID | `unique_code` |

- Backend mencocokkan lewat `Student::findByCode()` = `unique_code` **OR**
  `rfid_code`. **NIS tidak dicocokkan** oleh alur scan.
  > Bila diperlukan input berbasis **NIS**, itu adalah penambahan backend
  > (endpoint lookup by NIS) — dicatat sebagai enhancement, bukan bagian MVP.
- `waktu` menentukan aksi: `masuk` (check-in) / `pulang` (check-out).

---

## 4. Alur Prioritas (Absensi Siswa)

```
Login → Dashboard → Scan QR / Input Ref ID → Validasi siswa
      → Submit absensi → Backend menyimpan → Tampilkan hasil sukses/gagal
      → Notifikasi WhatsApp dipicu backend (bila berlaku)
```

- **Validasi siswa** memakai `POST /scan/lookup` (pratinjau nama/kelas) —
  opsional pada mode scan cepat, wajib pada mode input manual.
- **Submit** memakai `POST /scan` (online) atau antre lokal → `POST /scan/sync-offline` (offline).
- **WhatsApp**: backend memancarkan event `StudentCheckedIn/Out` →
  listener `SendCheckInNotification` → `WhatsAppService` (async, best-effort).
  Mobile **tidak** memanggil WA langsung. Notifikasi terkirim **bila berlaku**:
  tenant punya WA aktif (Fonnte/Wablas) **dan** wali punya nomor telepon.

---

## 5. Kebutuhan Fungsional (FR)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-1 | Pengguna login dengan email+password dan menerima Bearer token | Wajib |
| FR-2 | Token disimpan aman; auto-login selama token valid; logout mencabut token | Wajib |
| FR-3 | Dashboard menampilkan tanggal, jam, status hari libur, ringkasan tercatat, & pemilih Masuk/Pulang | Wajib |
| FR-4 | Aplikasi memuat konfigurasi absensi via `GET /scan/bootstrap` | Wajib |
| FR-5 | Scan QR dari kamera mengekstrak `unique_code` | Wajib |
| FR-6 | Input manual Ref ID (unique_code/rfid) | Wajib |
| FR-7 | Pratinjau/validasi siswa sebelum submit (`/scan/lookup`) | Wajib |
| FR-8 | Submit absensi masuk/pulang (`/scan`) dengan GPS opsional | Wajib |
| FR-9 | Tampilkan kartu hasil: sukses (nama, kelas, jam, telat) / gagal (alasan) | Wajib |
| FR-10 | Mode offline: antre scan lokal & sinkron (`/scan/sync-offline`) | Wajib |
| FR-11 | Rekap absensi hari ini (daftar tercatat) | Sebaiknya |
| FR-12 | Tangkap lokasi GPS bila `require_location` aktif (geofence) | Wajib bila diaktifkan |
| FR-13 | Umpan balik cepat (suara/getar) tiap scan | Sebaiknya |
| FR-14 | Lihat profil pengguna & sekolah aktif | Sebaiknya |

Rincian per-fitur (Actor, Preconditions, Flow, API, Request/Response, Validation,
Error, Acceptance) ada di `07-FEATURE-LIST.md`.

---

## 6. Kebutuhan Non-Fungsional (NFR)

| ID | Kategori | Kebutuhan |
|---|---|---|
| NFR-1 | Performa | Waktu dari scan sukses ke kartu hasil ≤ **1,5 dtk** pada jaringan normal; loop scan berkelanjutan tanpa restart kamera |
| NFR-2 | Offline | Semua scan dapat diantre lokal (penyimpanan persisten) & disinkron; tidak ada kehilangan data saat aplikasi ditutup |
| NFR-3 | Keamanan | Token di **secure storage** (Keystore/Keychain); tak pernah di log; TLS wajib; auto-logout saat `401` |
| NFR-4 | Keandalan | Kegagalan notifikasi/GPS **tidak** menggagalkan pencatatan absensi |
| NFR-5 | Lokalisasi | Bahasa Indonesia; format tanggal `d MMM yyyy`, jam `HH:mm` |
| NFR-6 | Kompatibilitas | Android 8+ (API 26+); kamera & (opsional) lokasi |
| NFR-7 | Rate limit | Hormati batas API (`auth` 5/mnt, `api` 60/mnt) & tangani `429` dengan `Retry-After` |
| NFR-8 | Aksesibilitas | Target sentuh ≥ 44px, kontras memadai, dukungan mode terang/gelap |
| NFR-9 | Observability | Log error lokal + opsi kirim diagnostik; tidak menyertakan PII sensitif |

---

## 7. Integrasi Teknis

| Aspek | Nilai |
|---|---|
| Framework | **Flutter** (Dart) |
| Base URL | `https://<host>/api/v1` (dev `http://localhost:8080/api/v1`) |
| Auth | Sanctum **Bearer token**, `Authorization: Bearer <token>`; berlaku ±7 hari, **tanpa refresh** |
| Header wajib | `Accept: application/json`; `Content-Type: application/json` |
| Tenant | Otomatis dari akun (admin/guru/staf) — tanpa `X-Tenant-ID` |
| Envelope | `{ success, message, data (+ meta, links) }` |
| Error | Dua bentuk: envelope `{success:false,message,errors?}` **dan** validasi FormRequest 422 `{message,errors}` — klien harus toleran keduanya |
| Paket usulan | `dio` (HTTP), `flutter_secure_storage`, `mobile_scanner` (QR), `geolocator` (GPS), `drift`/`sqflite` (antrean offline), `riverpod`/`bloc` (state) |

---

## 8. Asumsi & Batasan

1. Setiap siswa sudah memiliki `unique_code` (QR) — dibuat dari web
   (`QrCodeController`). Bila kosong, siswa tak bisa discan (harus generate dulu di web).
2. Konfigurasi jam & geofence berasal dari `attendance_settings` per tenant;
   mobile hanya membaca via `/scan/bootstrap`.
3. WhatsApp bergantung konfigurasi tenant (provider + API key) dan nomor wali —
   di luar kendali aplikasi mobile.
4. Kalender libur (`holidays`) dikelola di web; scan pada hari libur ditolak backend.
5. Zona waktu server = `Asia/Jakarta`; mobile menampilkan apa adanya dari server.

---

## 9. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| `/scan/*` belum ber-gate permission | User non-petugas bisa mencatat absensi | Usul backend tambah `permission:attendance.record`; sementara batasi via UI per-role |
| Bentuk error tidak konsisten | Parsing klien rapuh | Layer HTTP menormalkan kedua bentuk ke satu model error |
| URL foto (`photo_url`) berbasis `APP_URL` | Avatar gagal termuat di device | Pastikan `APP_URL` produksi benar; fallback inisial nama |
| Token 7 hari tanpa refresh | Sesi putus mendadak | Deteksi `401` → alihkan ke Login; simpan draft antrean offline |
| Clock device tidak akurat (offline) | `scanned_at` melenceng | Gunakan waktu perangkat + tandai; server tetap sumber kebenaran final |

---

## 10. Kriteria Keberhasilan MVP

- Petugas dapat login, memilih Masuk/Pulang, dan mencatat ≥ 100 siswa berturut
  tanpa gangguan.
- Setiap scan menghasilkan kartu hasil yang jelas (sukses/gagal beserta alasan).
- Scan saat offline tersimpan dan tersinkron 100% saat online kembali.
- Absensi tersimpan benar di backend (terlihat di web) dan, bila berlaku,
  notifikasi WhatsApp terkirim ke wali.
- Tidak ada kebocoran data lintas sekolah (tenant isolation terjaga).

---

## 11. Ketertelusuran ke API

| FR | Endpoint |
|---|---|
| FR-1, FR-2 | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| FR-3, FR-4 | `GET /scan/bootstrap`, `GET /dashboard` |
| FR-5..FR-9, FR-12 | `POST /scan`, `POST /scan/lookup` |
| FR-10 | `POST /scan/sync-offline` |
| FR-11 | `GET /attendance/students/daily`, `GET /attendance/students/summary` |
| WhatsApp | (backend event `StudentCheckedIn` → `WhatsAppService`; tak ada endpoint mobile) |
