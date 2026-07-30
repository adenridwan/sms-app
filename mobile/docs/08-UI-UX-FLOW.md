# 08 — UI/UX Flow (Flutter MVP · Absensi Siswa)

> Alur antarmuka & pengalaman untuk MVP absensi (Admin/Guru/Staf). Melengkapi
> storyboard visual dan mengikat tiap layar ke fitur (`07-FEATURE-LIST.md`) dan
> endpoint (`03-API-CONTRACT.md`). Belum ada kode.

**Storyboard visual (wireframe interaktif):**
https://claude.ai/code/artifact/1d04d982-17d6-4cca-adfe-9521720f8830

---

## 1. Model Navigasi

Aplikasi absensi memakai **bottom navigation** 4 tab (persona Operator):

```
[ Scan ] · [ Rekap ] · [ Antrean ] · [ Profil ]
   F5/F6      F10         F8           F11/F2
```

- **Auth gate** di depan: Splash → cek token → Login (F1) atau langsung Dashboard/Scan.
- Tab default setelah login = **Scan**.
- Badge angka pada **Antrean** menampilkan jumlah scan offline menunggu (F8).

---

## 2. Inventaris Layar

| Kode | Layar | Fitur | Endpoint |
|---|---|---|---|
| S0 | Splash / Auth gate | F2 | `GET /auth/me` |
| S1 | Login | F1 | `POST /auth/login` |
| S2 | Dashboard Scan (home) | F3 | `GET /scan/bootstrap` |
| S3 | Kamera Scan QR | F5 | `POST /scan` (+`/scan/lookup`) |
| S4 | Input Ref ID (manual) | F6 | `POST /scan` (+`/scan/lookup`) |
| S5 | Kartu Hasil (overlay) | F7 | — |
| S6 | Rekap Harian | F10 | `GET /attendance/students/daily` |
| S7 | Antrean Offline | F8 | `POST /scan/sync-offline` |
| S8 | Profil & Info Sekolah | F11 | `GET /auth/me` |

---

## 3. Alur Prioritas End-to-End

```
S0 Splash
  └─(token valid?)─ tidak → S1 Login ─(POST /auth/login OK)─┐
                     ya ───────────────────────────────────┤
                                                            ▼
S2 Dashboard Scan  ──(GET /scan/bootstrap)
  │  pilih mode: [Masuk] / [Pulang]
  ├─ "Mulai Scan" ─────────────► S3 Kamera QR
  └─ "Input Ref ID" ───────────► S4 Input Manual

S3/S4  → dapat kode
       → (opsional) POST /scan/lookup  → tampilkan identitas
       → tangkap GPS bila require_location
       → online?  ── ya ─► POST /scan ─────────────► S5 Kartu Hasil
                   └ tidak ─► simpan ke Antrean (S7) ► S5 Kartu Hasil (mode antre)

S5 Kartu Hasil (sukses/gagal + suara/haptik)
   → auto-dismiss / tap → kembali ke S3/S4 (loop scan)
   → backend memancarkan event → WhatsApp ke wali (F9, latar belakang)

S7 Antrean → online → POST /scan/sync-offline → ringkasan per-item
```

---

## 4. Spesifikasi Per-Layar

### S0 · Splash / Auth Gate
- **Tujuan:** menentukan masuk ke Login atau Dashboard.
- **Elemen:** logo, indikator loading, teks "Memeriksa sesi…".
- **State:** `loading` → (`/auth/me` 200) `authed` → S2; (`401`/tak ada token) → S1.
- **Transisi keluar:** otomatis (tanpa aksi pengguna).

### S1 · Login
- **Tujuan:** autentikasi (F1).
- **Elemen:** field Email, Password (toggle lihat), checkbox "Ingat saya", tombol **Masuk**, tautan "Lupa password?".
- **State:** `idle` · `submitting` (tombol loading, field dikunci) · `error` (pesan inline) · `success` → S2.
- **Validasi klien:** email format; password ≥ 6.
- **Error:** 401/403/422/429/offline (lihat F1).

### S2 · Dashboard Scan (Home)
- **Tujuan:** titik awal & konteks (F3).
- **Elemen:** tanggal & jam server; **segmented [Masuk | Pulang]**; kartu ringkasan ("128 masuk hari ini"); tombol utama **Mulai Scan**; tombol sekunder **Input Ref ID**; badge offline bila ada antrean; banner libur bila `is_holiday`.
- **State:** `loading` (skeleton) · `ready` · `holiday` (scan dinonaktifkan) · `offline` (pakai setting cache, banner).
- **Aksi:** pilih mode → S3/S4; buka tab lain.
- **Catatan UX:** mode Masuk/Pulang **persist** selama sesi scan agar tidak salah pilih berulang.

### S3 · Kamera Scan QR
- **Tujuan:** menangkap `unique_code` dari QR & submit (F5).
- **Elemen:** viewfinder + bingkai target; label mode aktif (Masuk/Pulang); indikator GPS ("Lokasi terekam / di luar radius"); tombol beralih ke Input Manual; senter (opsional).
- **State:** `scanning` · `code_detected` (freeze sesaat) · `submitting` · `result` (→ S5) · `permission_denied` (ajakan beri izin kamera).
- **Interaksi:** deteksi otomatis; setelah hasil, kamera **lanjut** ke siswa berikut (loop) tanpa aksi tambahan.
- **Edge:** QR bukan milik sistem → S5 gagal "Kode tidak ditemukan"; duplikat kode beruntun < 3 dtk → diabaikan (debounce) untuk cegah dobel.

### S4 · Input Ref ID (Manual)
- **Tujuan:** fallback ketik/tempel kode (F6).
- **Elemen:** field kode (autofocus, mendukung pembaca RFID sebagai keyboard), tombol **Cek** (lookup), kartu pratinjau identitas, tombol **Catat**.
- **State:** `idle` · `looking_up` · `preview` (identitas tampil) · `submitting` · `result` (→ S5) · `not_found`.
- **Validasi:** trim spasi; non-kosong; tampilkan identitas sebelum submit (wajib pada mode manual).

### S5 · Kartu Hasil (Overlay)
- **Tujuan:** umpan balik sukses/gagal (F7).
- **Elemen:** ikon status besar (✓ hijau / ✕ merah); nama, kelas, jam; pill status (**Hadir/Telat +menit**, atau **Perlu verifikasi** saat pulang di luar jam); alasan bila gagal; tombol **Scan Berikutnya**.
- **State:** `success` · `late` (kuning) · `failure` (merah) · `queued` (biru, "Disimpan offline").
- **Feedback:** suara + getar berbeda untuk sukses vs gagal; auto-dismiss ±2 dtk atau tap.

### S6 · Rekap Harian
- **Tujuan:** melihat hasil hari ini (F10).
- **Elemen:** ringkasan angka per status (Hadir/Telat/Izin/Sakit/Alfa); daftar siswa (nama, kelas, jam, status pill); filter tanggal/kelas; pull-to-refresh.
- **State:** `loading` · `list` · `empty` · `error` (coba lagi).

### S7 · Antrean Offline
- **Tujuan:** kelola scan tertunda (F8).
- **Elemen:** indikator "Offline · N menunggu"; daftar item (kode, waktu, mode); tombol **Sinkron N data**; hasil per-item setelah sinkron (✓/✕ + alasan).
- **State:** `empty` · `queued` · `syncing` · `partial` (sebagian gagal, tetap tampil).

### S8 · Profil & Info Sekolah
- **Tujuan:** identitas & keluar (F11/F2).
- **Elemen:** nama, peran, sekolah aktif, versi app; toggle tema; tombol **Keluar** (konfirmasi).

---

## 5. Konvensi Interaksi & Umpan Balik

| Kejadian | Umpan balik |
|---|---|
| Scan sukses | getar pendek + nada naik + kartu hijau |
| Terlambat | getar pendek + kartu kuning ("Telat N menit") |
| Gagal / kode tak dikenal | getar ganda + nada turun + kartu merah |
| Tersimpan offline | toast biru "Disimpan, akan disinkron" |
| Kehilangan koneksi | banner atas persisten "Mode offline" |
| Sesi berakhir (401) | dialog "Sesi berakhir" → ke Login |

- **Loop scan:** setelah hasil, kembali otomatis ke kamera (tanpa tap) agar
  antrean gerbang cepat. Debounce kode sama 3 dtk.
- **Mode Masuk/Pulang** ditampilkan menonjol di layar scan agar tak tertukar.

---

## 6. Matriks Penanganan Error (ringkas)

| Kondisi | Layar | Perilaku UI |
|---|---|---|
| Kredensial salah (401) | S1 | Pesan inline, tetap di Login |
| Akun non-aktif (403) | S1 | Dialog "Hubungi admin" |
| Hari libur (`is_holiday`) | S2/S3 | Scan dinonaktifkan + banner |
| Kode tak ditemukan (404/422) | S3/S4/S5 | Kartu merah "Kartu tidak dikenal" |
| Sudah absen (422) | S5 | Kartu merah + jam sebelumnya |
| Di luar radius (422 geofence) | S5 | Kartu merah + jarak; sarankan mendekat |
| Jaringan putus | S3/S4 | Simpan ke Antrean (S7), kartu "Disimpan offline" |
| Rate limit (429) | mana pun | Toast "Coba lagi dalam N dtk" (`Retry-After`) |
| Sesi berakhir (401) | mana pun | Auto-logout → S1, antrean tetap tersimpan |

---

## 7. Aksesibilitas & Lokalisasi

- Bahasa Indonesia; jam `HH:mm`, tanggal `d MMM yyyy`.
- Target sentuh ≥ 44px; tombol utama besar & mudah dijangkau satu tangan.
- Kontras memadai; mendukung mode terang & gelap (lihat storyboard).
- Umpan balik tidak hanya warna (ikon + teks + audio/haptik) untuk pengguna
  dengan keterbatasan warna.
- `prefers-reduced-motion`: kurangi animasi transisi kartu hasil.

---

## 8. Ketertelusuran Layar → Fitur → Endpoint

| Layar | Fitur | Endpoint |
|---|---|---|
| S0 | F2 | `GET /auth/me` |
| S1 | F1 | `POST /auth/login` |
| S2 | F3 | `GET /scan/bootstrap` |
| S3 | F4, F5 | `POST /scan/lookup`, `POST /scan` |
| S4 | F4, F6 | `POST /scan/lookup`, `POST /scan` |
| S5 | F7 | — |
| S6 | F10 | `GET /attendance/students/daily` |
| S7 | F8 | `POST /scan/sync-offline` |
| S8 | F11, F2 | `GET /auth/me`, `POST /auth/logout` |

> Catatan: WhatsApp (F9) berjalan di latar belakang backend setelah absensi
> tersimpan; tidak ada layar/aksi mobile khusus untuknya.
