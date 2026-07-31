# 07 — Feature List (Flutter MVP · Absensi Siswa)

> Katalog fitur MVP-1. Setiap fitur didefinisikan lengkap: **Actor, Preconditions,
> User flow, API endpoint, Request, Response, Validation, Error scenarios,
> Acceptance criteria**. Berdasarkan API v1 yang ada (`ScannerController`,
> `AttendanceScanService`, `AuthController`). Belum ada kode.

Base URL: `/api/v1` · Auth: `Authorization: Bearer <token>` · Header: `Accept: application/json`.

Ringkasan:

| ID | Fitur | Actor | Endpoint inti |
|---|---|---|---|
| F1 | Login | Admin/Guru/Staf | `POST /auth/login` |
| F2 | Sesi & Logout | Admin/Guru/Staf | `GET /auth/me`, `POST /auth/logout` |
| F3 | Dashboard Scanner | Admin/Guru/Staf | `GET /scan/bootstrap` |
| F4 | Validasi Siswa (Lookup) | Admin/Guru/Staf | `POST /scan/lookup` |
| F5 | Absensi — QR Code | Admin/Guru/Staf | `POST /scan` |
| F6 | Absensi — Ref ID manual | Admin/Guru/Staf | `POST /scan` |
| F7 | Umpan Balik Hasil | Admin/Guru/Staf | (klien) |
| F8 | Antrean & Sinkron Offline | Admin/Guru/Staf | `POST /scan/sync-offline` |
| F9 | Notifikasi WhatsApp | Sistem (backend) | (event, tanpa endpoint mobile) |
| F10 | Rekap Absensi Harian | Admin/Guru/Staf | `GET /attendance/students/daily` |
| F11 | Profil & Info Sekolah | Admin/Guru/Staf | `GET /auth/me` |

---

## F1 — Login

- **Actor:** Administrator, Guru, Staf.
- **Preconditions:** Akun aktif (`status = active`) sudah dibuat di web; perangkat terhubung internet.
- **User flow:**
  1. Buka aplikasi → layar Login.
  2. Isi email & password → tekan **Masuk**.
  3. Aplikasi kirim kredensial, simpan token, arahkan ke Dashboard.
- **API endpoint:** `POST /api/v1/auth/login` (rate limit `auth` 5/mnt/IP).
- **Request:**
  ```json
  { "email": "operator@sekolah.sch.id", "password": "rahasia", "remember": true }
  ```
- **Response (200):**
  ```json
  { "success": true, "message": "Login berhasil.",
    "data": { "user": { "id":"...", "full_name":"...", "user_type":"staff", "roles":["tata_usaha"], "tenant_id":"..." },
              "token": "12|abcdef...", "token_type": "Bearer" } }
  ```
- **Validation:**
  - Klien: email non-kosong & format valid; password ≥ 6 karakter.
  - Server: `email` required|email; `password` required|min:6; `remember` boolean.
- **Error scenarios:**
  - `401` `{success:false,message:"Email atau password salah."}` → tampilkan inline.
  - `403` `{...message:"Akun Anda tidak aktif..."}` → dialog hubungi admin.
  - `422` `{message, errors:{email:[...]}}` → tandai field.
  - `429` → "Terlalu banyak percobaan, coba lagi dalam N detik" (`Retry-After`).
  - Tanpa jaringan → banner "Tidak ada koneksi".
- **Acceptance criteria:**
  - Diberi kredensial benar & akun aktif → token tersimpan di secure storage, diarahkan ke Dashboard.
  - Kredensial salah → pesan error jelas, token tidak tersimpan.
  - Akun non-aktif → ditolak dengan pesan spesifik.
  - Token tidak pernah muncul di log.

---

## F2 — Sesi & Logout

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Sudah login (token tersimpan).
- **User flow:**
  1. Saat start, aplikasi baca token → panggil `/auth/me` untuk memuat profil/izin.
  2. Bila valid → langsung Dashboard; bila `401` → hapus token → Login.
  3. **Keluar**: menu profil → **Keluar** → token dicabut → kembali ke Login.
- **API endpoint:** `GET /api/v1/auth/me`; `POST /api/v1/auth/logout`.
- **Request:** header `Authorization: Bearer <token>` (tanpa body).
- **Response (`/auth/me` 200):**
  ```json
  { "success": true, "data": { "user": {...}, "permissions": ["attendance.record", ...], "roles": ["admin"] } }
  ```
  `/auth/logout` → `{ "success": true, "message": "Logout berhasil." }`.
- **Validation:** token wajib; `user_type` ∈ {admin, staff, teacher} untuk masuk app absensi (persona lain diblok di klien dengan pesan).
- **Error scenarios:**
  - `401` di `/auth/me` → sesi berakhir → auto-logout ke Login.
  - Logout gagal jaringan → hapus token lokal tetap dilakukan; token sisa kadaluarsa sendiri (7 hari).
- **Acceptance criteria:**
  - Token valid → auto-login tanpa ketik ulang.
  - `401` di endpoint mana pun → sesi dibersihkan & diarahkan ke Login.
  - Setelah logout, token lokal terhapus dan tidak bisa dipakai lagi.

---

## F3 — Dashboard Scanner

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Login sukses; tenant aktif.
- **User flow:**
  1. Masuk Dashboard → muat `bootstrap` (tanggal, jam, libur, setting).
  2. Tampilkan ringkasan hari ini & pemilih **Masuk/Pulang**.
  3. Tekan **Mulai Scan** (F5) atau **Input Ref ID** (F6).
- **API endpoint:** `GET /api/v1/scan/bootstrap` (opsional `GET /dashboard` untuk statistik).
- **Request:** header auth, tanpa body.
- **Response (200):**
  ```json
  { "success": true, "data": {
      "today": "2026-07-29", "current_time": "07:05:00",
      "is_holiday": false, "holiday_info": null,
      "settings": { "check_in_start":"06:00:00","check_in_end":"07:30:00",
                    "check_out_start":"14:00:00","check_out_end":"17:00:00",
                    "late_tolerance_minutes":15, "require_location":false },
      "check_in_deadline": "07:30:00" } }
  ```
- **Validation:** —(read-only). Klien menyimpan `settings.require_location` untuk menentukan wajib-GPS.
- **Error scenarios:**
  - `is_holiday = true` → tampilkan banner libur; tombol scan dinonaktifkan (server juga menolak).
  - `401` → auto-logout.
  - Gagal muat → tampilkan tombol "Coba lagi"; izinkan mode offline dengan setting terakhir yang di-cache.
- **Acceptance criteria:**
  - Dashboard menampilkan tanggal/jam server, mode Masuk/Pulang, dan status libur dengan benar.
  - Bila `require_location=true`, aplikasi meminta izin lokasi sebelum scan.
  - Bila hari libur, pengguna tak bisa memulai scan.

---

## F4 — Validasi Siswa (Lookup)

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Sudah dapat sebuah kode (dari QR/manual).
- **User flow:**
  1. Setelah kode terbaca (F5) atau diketik (F6), aplikasi memanggil lookup.
  2. Tampilkan pratinjau: nama, NIS, kelas, status → pengguna konfirmasi lalu submit.
  - Mode "scan cepat" boleh melewati konfirmasi manual dan langsung submit; lookup dipakai untuk menampilkan identitas di kartu hasil.
- **API endpoint:** `POST /api/v1/scan/lookup`.
- **Request:**
  ```json
  { "unique_code": "STU-AB12CD34EF56" }
  ```
- **Response (200):**
  ```json
  { "success": true, "message": "Siswa ditemukan",
    "data": { "type":"student", "id":"...", "nis":"202301", "name":"Andi Pratama", "classroom":"X-A", "status":"active" } }
  ```
  (Guru → `{ type:"teacher", nip, name, status }`.)
- **Validation:** `unique_code` required|string|max:100.
- **Error scenarios:**
  - `404` `{success:false,message:"Kode tidak ditemukan"}` → tampilkan "Kartu tidak dikenal".
  - Siswa `status != active` → tampilkan peringatan (tetap boleh, keputusan operator).
  - `401`/`429`/offline → tangani sesuai standar.
- **Acceptance criteria:**
  - Kode valid → identitas tampil < 1 dtk.
  - Kode tidak ada → pesan jelas, tidak melanjutkan submit.

---

## F5 — Absensi via QR Code

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Login; mode (Masuk/Pulang) terpilih; izin kamera diberikan; (bila `require_location`) izin lokasi.
- **User flow:**
  1. Buka kamera scanner → arahkan ke QR siswa.
  2. Kode terbaca → (opsional lookup F4) → ambil GPS bila diperlukan → submit.
  3. Tampilkan kartu hasil (F7) → kamera siap scan berikutnya.
- **API endpoint:** `POST /api/v1/scan`.
- **Request:**
  ```json
  { "unique_code": "STU-AB12CD34EF56", "waktu": "masuk", "latitude": -6.200000, "longitude": 106.816666 }
  ```
- **Response (200 — check-in):**
  ```json
  { "success": true, "message": "Absen masuk berhasil",
    "data": { "type":"student", "action":"check_in",
      "student": { "id":"...", "nis":"202301", "name":"Andi Pratama", "classroom":"X-A" },
      "time":"07:13:20", "late":false, "late_minutes":0, "late_info":{...}, "total_violation_points":0 } }
  ```
  Terlambat → `message:"Terlambat 8 menit"`, `late:true, late_minutes:8`.
  Check-out → `data.action:"check_out"`, `check_in_time`, `needs_verification`.
- **Validation (server):** `unique_code` required|string|max:100; `waktu` required|in:masuk,pulang; `latitude` nullable|numeric|between:-90,90; `longitude` nullable|numeric|between:-180,180.
- **Error scenarios (HTTP 422, `{success:false,message}`):**
  - "Hari ini adalah hari libur".
  - "Kode tidak ditemukan".
  - "Siswa tidak terdaftar di kelas manapun".
  - "Siswa sudah melakukan absen masuk pada HH:MM".
  - "Lokasi wajib diaktifkan untuk melakukan absen." / "Anda berada X m dari sekolah, di luar radius maksimal Y m." (geofence).
  - (Pulang) "Siswa belum melakukan absen masuk" / "Siswa sudah melakukan absen pulang pada HH:MM".
  - Non-2xx lain (`401`,`429`,`5xx`) → tangani standar; bila jaringan putus → alihkan ke antrean offline (F8).
- **Acceptance criteria:**
  - QR valid + belum absen → tercatat, kartu sukses tampil, terlambat dihitung benar.
  - Duplikat (sudah absen) → ditolak dengan jam sebelumnya, tidak membuat data ganda.
  - Geofence aktif & di luar radius → ditolak dengan jarak.
  - Scanner dapat lanjut ke siswa berikutnya tanpa restart manual.

---

## F6 — Absensi via Ref ID (Input Manual)

- **Actor:** Admin, Guru, Staf (fallback saat QR rusak / kartu RFID).
- **Preconditions:** Sama seperti F5, tanpa kamera; keyboard/pembaca RFID tersedia.
- **User flow:**
  1. Pilih **Input Ref ID** → ketik/tempel `unique_code` atau `rfid_code`.
  2. Aplikasi lookup (F4) untuk konfirmasi identitas → submit.
  3. Tampilkan hasil (F7).
- **API endpoint:** `POST /api/v1/scan` (identik F5; kode dari input manual).
- **Request:**
  ```json
  { "unique_code": "RF-9K2M4P7Q1Z", "waktu": "pulang" }
  ```
- **Response:** sama seperti F5.
- **Validation:**
  - Klien: kode non-kosong; panjang wajar; trim spasi; tolak karakter ilegal.
  - Server: sama seperti F5.
- **Error scenarios:** sama seperti F5, ditambah "Kode tidak ditemukan" untuk salah ketik → beri tombol koreksi cepat.
- **Acceptance criteria:**
  - Kode benar (unique_code atau rfid_code) → tercatat sama seperti QR.
  - Konfirmasi identitas ditampilkan sebelum submit pada mode manual.
  - Salah ketik → pesan jelas & mudah mengoreksi tanpa kehilangan mode.

---

## F7 — Umpan Balik Hasil (Success / Failure)

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Ada respons submit (F5/F6) atau hasil antrean offline.
- **User flow:**
  1. Tampilkan **kartu hasil**: hijau (sukses) / merah (gagal).
  2. Sukses: nama, kelas, jam, status (Hadir/Telat + menit), poin pelanggaran bila ada.
  3. Gagal: alasan dari `message`.
  4. Beri isyarat suara/getar berbeda untuk sukses vs gagal; auto-dismiss (mis. 2 dtk) atau tap untuk lanjut.
- **API endpoint:** — (murni klien, menampilkan respons F5/F6/F8).
- **Request/Response:** —.
- **Validation:** Petakan `data.action` & `data.late`/`needs_verification` ke label status yang benar (Hadir/Telat/Perlu verifikasi).
- **Error scenarios:**
  - Respons tanpa `data` tapi `success=false` → tampilkan `message` sebagai kegagalan.
  - Bentuk error tak terduga → tampilkan pesan generik "Gagal mencatat, coba lagi".
- **Acceptance criteria:**
  - Sukses & gagal dapat dibedakan secara visual **dan** audio/haptik dalam < 1 dtk.
  - Semua field penting (nama, jam, status telat) tampil akurat sesuai respons.

---

## F8 — Antrean & Sinkron Offline

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Fitur scan aktif; koneksi tidak stabil/putus.
- **User flow:**
  1. Saat submit gagal karena jaringan → simpan scan ke antrean lokal (persisten) dengan `scanned_at` waktu perangkat.
  2. Indikator "Offline · N menunggu" tampil.
  3. Saat online kembali (otomatis/tap "Sinkron") → kirim batch; tampilkan ringkasan berhasil/gagal; buang yang sukses, tandai yang gagal untuk ditinjau.
- **API endpoint:** `POST /api/v1/scan/sync-offline`.
- **Request:**
  ```json
  { "scans": [
    { "unique_code":"STU-AB12CD34EF56", "waktu":"masuk", "scanned_at":"2026-07-29 07:13:20", "latitude":-6.2, "longitude":106.81 },
    { "unique_code":"RF-9K2M4P7Q1Z", "waktu":"masuk", "scanned_at":"2026-07-29 07:14:02" }
  ] }
  ```
- **Response (200):**
  ```json
  { "success": true, "message": "Sync completed: 1 berhasil, 1 gagal",
    "data": { "total":2, "success":1, "failed":1,
      "results": [ { "unique_code":"STU-...","scanned_at":"...","success":true,"message":"Absen masuk berhasil" },
                   { "unique_code":"RF-...","scanned_at":"...","success":false,"message":"Kode tidak ditemukan" } ] } }
  ```
- **Validation (server):** `scans` required|array|min:1; tiap item: `unique_code` required|string; `waktu` required|in:masuk,pulang; `scanned_at` required|date; `latitude/longitude` nullable|numeric.
- **Error scenarios:**
  - Sebagian item gagal → tetap `200`; klien menandai per-item dari `results`.
  - `401` saat sinkron → simpan antrean, arahkan Login, lanjutkan setelah login.
  - Batch terlalu besar → pecah menjadi beberapa permintaan (mis. 50/permintaan).
- **Acceptance criteria:**
  - Scan offline tidak hilang meski aplikasi ditutup/perangkat mati.
  - Setelah online, semua item terkirim; hasil per-item tercermin akurat.
  - Item sukses dihapus dari antrean; item gagal tetap terlihat untuk tindak lanjut.

---

## F9 — Notifikasi WhatsApp (Dipicu Sistem)

- **Actor:** **Sistem (backend)** — bukan aksi mobile.
- **Preconditions:** Absensi tersimpan (F5/F6/F8); tenant punya WA aktif (Fonnte/Wablas) & wali punya nomor.
- **User flow (latar belakang):**
  1. Backend menyimpan absensi → memancarkan event `StudentCheckedIn`/`StudentCheckedOut`.
  2. Listener `SendCheckInNotification` memanggil `WhatsAppService` (async, best-effort).
  3. Bila terkonfigurasi, pesan terkirim ke wali; kegagalan **tidak** membatalkan absensi.
- **API endpoint:** — (tidak ada endpoint yang dipanggil mobile).
- **Request/Response:** —.
- **Validation:** Ditangani backend (nomor wali valid, provider aktif).
- **Error scenarios:** Provider WA down / nomor kosong / tenant belum setel → notifikasi dilewati diam-diam; absensi tetap sukses.
- **Acceptance criteria:**
  - Aplikasi mobile **tidak** menampilkan status pengiriman WA sebagai bagian dari sukses/gagal absensi.
  - Absensi tercatat sukses meskipun WA gagal terkirim.
  - (Opsional, jika backend menyediakan) tampilkan indikator informatif "Notifikasi wali dikirim" hanya bila API mengonfirmasi — **tidak ada** di kontrak saat ini, jadi tidak diandalkan pada MVP.

---

## F10 — Rekap Absensi Harian

- **Actor:** Admin (penuh), Guru/Staf (sesuai cakupan `visibleTo`).
- **Preconditions:** Login; ada data absensi hari berjalan.
- **User flow:**
  1. Buka tab **Rekap** → tampilkan daftar siswa tercatat hari ini + ringkasan (hadir/telat/izin/sakit/alfa).
  2. Filter per kelas/tanggal (opsional).
- **API endpoint:** `GET /api/v1/attendance/students/daily` (+ `GET /attendance/students/summary`).
- **Request:** query mis. `?attendance_date=2026-07-29&classroom_id=...&per_page=20`.
- **Response (200):** koleksi paginated `{ success, data:[ {student, status, check_in_time, check_out_time, ...} ], meta, links }`.
- **Validation:** parameter tanggal `Y-m-d`; pagination `per_page` wajar (≤ 50).
- **Error scenarios:**
  - Data kosong → tampilkan empty state.
  - `403` (di luar cakupan) → sembunyikan/kelabuan; jangan bocorkan data.
  - `401` → auto-logout.
- **Acceptance criteria:**
  - Daftar mencerminkan absensi yang baru saja discan (konsisten dengan backend).
  - Ringkasan angka per status akurat.
  - Guru/staf hanya melihat data yang menjadi haknya.

---

## F11 — Profil & Info Sekolah

- **Actor:** Admin, Guru, Staf.
- **Preconditions:** Login.
- **User flow:**
  1. Buka menu profil → tampilkan nama, peran, sekolah aktif, versi aplikasi.
  2. Aksi: Keluar (F2).
- **API endpoint:** `GET /api/v1/auth/me` (data sudah dimuat saat start).
- **Request:** header auth.
- **Response:** `{ data: { user:{full_name,user_type,roles[],tenant_id}, roles[], permissions[] } }`.
- **Validation:** —.
- **Error scenarios:** `401` → auto-logout; foto profil gagal muat → fallback inisial nama.
- **Acceptance criteria:**
  - Menampilkan identitas & peran pengguna serta nama sekolah dengan benar.
  - Tombol Keluar berfungsi (F2) dan membersihkan sesi.

---

## Ringkasan Matriks Endpoint

| Fitur | Method | Path |
|---|---|---|
| F1 | POST | `/auth/login` |
| F2 | GET / POST | `/auth/me` · `/auth/logout` |
| F3 | GET | `/scan/bootstrap` |
| F4 | POST | `/scan/lookup` |
| F5, F6 | POST | `/scan` |
| F8 | POST | `/scan/sync-offline` |
| F10 | GET | `/attendance/students/daily` · `/attendance/students/summary` |
| F11 | GET | `/auth/me` |

> F9 (WhatsApp) tidak memanggil endpoint dari mobile — dipicu event backend.
