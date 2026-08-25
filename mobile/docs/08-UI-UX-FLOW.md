# 08 — UI/UX Flow (Flutter MVP · Absensi Siswa)

> Alur antarmuka & pengalaman untuk MVP absensi (Admin/Guru/Staf). Melengkapi
> storyboard visual dan mengikat tiap layar ke fitur (`07-FEATURE-LIST.md`) dan
> endpoint (`03-API-CONTRACT.md`). Belum ada kode.

**Storyboard visual (wireframe interaktif):**
https://claude.ai/code/artifact/1d04d982-17d6-4cca-adfe-9521720f8830

**Rujukan desain yang dipakai sekarang (2026-08-23):**
https://claude.ai/code/artifact/0af1e5c5-d9bd-4a24-8dd5-350595b2e80e

---

## 0. Sistem Desain

Seluruh layar mengikuti rujukan di atas. Token ada di
`lib/core/theme/app_theme.dart`, komponen bersama di `lib/core/theme/ui_kit.dart`.

### Token

| Peran | Nilai | Catatan |
|---|---|---|
| Aksen (terang / gelap) | `#EC3013` / `#E15B47` | dipakai hemat: aksi utama & penanda aktif |
| Aksen lembut | `#FFF2EF` · `#FFE0D9` · `#7C1405` | latar pil peran, rel pesan |
| Latar layar | `#F3F2F2` (terang) · `#1A1817` (gelap) | **bukan putih** — kartu putih mengambang di atasnya |
| Permukaan kartu | `#FFFFFF` · `#242121` | |
| Teks | `#201E1D` · `#F8F4F4` | |
| Radius | 16 (kartu), 12 (tombol/field), 999 (pil) | |
| Tipografi | **Archivo** 400/600/700/800, di-bundle di `assets/fonts/` | judul berbobot 800 dengan `letterSpacing` negatif |

Tiga aturan yang tidak boleh dilanggar, karena itulah yang membedakan rujukan
ini dari tema Material bawaan:

1. **Tanpa elevation.** Semua permukaan datar; pemisah memakai garis 1px.
2. **Latar bukan putih.** Kedalaman datang dari kontras kartu vs latar hangat.
3. **Ikon dipakai seperlunya.** Grid aksi dan daftar memakai kata, bukan simbol
   — ikon hanya di bilah tab, lembar pintasan, dan kamera.
4. **Tombol memakai tinggi minimum, bukan `Size.fromHeight`.** `Size.fromHeight`
   berarti lebar tak hingga; begitu tombol dipakai di dalam `Row` (bilah simpan
   Absen Kelas, baris aksi dialog) tata letak gagal dengan
   *BoxConstraints forces an infinite width* dan **seluruh isi layar hilang** —
   daftar siswa sempat tampil kosong karenanya. Tombol tetap selebar layar di
   `ListView` dan bilah bawah karena induknya sudah memberi lebar penuh.

> `ColorScheme.fromSeed` memetakan seed ke palet tonal M3 dan mengubah
> merah-oranye ini jadi cokelat kusam, sehingga nilai `primary`,
> `primaryContainer`, `surface`, dan `onSurface` **dikunci** lewat `.copyWith`.

### Komponen bersama (`ui_kit.dart`)

| Komponen | Dipakai untuk |
|---|---|
| `Panel` | kartu putih datar (opsional `onTap`, `tinted` untuk keadaan aktif) |
| `InfoStrip` | pesan satu baris dengan rel warna di kiri — pengganti banner berikon |
| `PillTabs<T>` | pemilih 2–3 pilihan; terpilih = bidang gelap penuh |
| `FieldLabel` | label huruf kapital kecil di atas kelompok kendali |
| `EmptyNote` | keadaan kosong: judul tebal + satu kalimat penjelas |

Kartu gelap (`color: scheme.onSurface`) adalah satu-satunya bidang gelap per
layar dan menandai "pokok layar ini": ringkasan hari ini di Beranda, petugas di
Absensi, identitas di Profil.

### Peran

Peran **selalu berasal dari akun yang login** (`roles`/`permissions` di token),
tidak pernah dari pemilih di aplikasi:

- Beranda — judul kartu ringkasan berbunyi *Kelas Anda* untuk guru, *Seluruh
  Sekolah* untuk admin; grid aksi disaring `visibleActionsFor(user)`.
- Absen Kelas — daftar kelas dibatasi server (guru hanya kelas yang diampu).
- Profil — peran tampil sebagai fakta akun, tanpa kendali pengubah.

Ringkasan Beranda dikunci pada id akun (`dashboardStatsFamily`). Tanpa itu
`FutureProvider` mewarisi nilai lama saat dibangun ulang, sehingga beberapa
detik setelah ganti akun **admin melihat kartu "Kelas Anda" milik guru**
lengkap dengan peringatan "belum terhubung ke kelas".

---

## 1. Model Navigasi

**Bottom navigation** 4 tab — susunan dan namanya disalin dari `bottomTabs`
pada rujukan desain:

```
[ Beranda ] · [ Absensi ] · [ Keuangan ] · [ Profil ]
     F2          F5/F6           (belum)        F11
```

- **Auth gate** di depan: Splash → cek token → Login (F1) atau langsung Beranda.
- Tab default setelah login = **Beranda**.
- Tab **Absensi** disembunyikan bila akun tak punya izin `attendance.record`.
- **Keuangan** — lihat §1c.
- **Notifikasi kehilangan tabnya** (posisinya diambil Keuangan). Fiturnya tetap
  jalan dan dibuka dari **Profil → Notifikasi**, lengkap dengan jumlah belum
  dibaca. Ini konsekuensi menyamakan menu dengan rujukan, yang memang tidak
  punya layar notifikasi.
- Antrean offline muncul sebagai `InfoStrip` yang bisa diketuk di Beranda (F8)
  dan sebagai panel di tab Absensi.

### 1a. Grid aksi Beranda

Judul dan keterangannya **persis** `homeActionDefs` pada rujukan, termasuk
perbedaan set antar peran. Peran diambil dari `user_type` di token.

| Guru | Admin / Staf / Super Admin |
|---|---|
| **Absensi** — Pindai, Ref ID & checklist | **Absensi** — Pindai, Ref ID & checklist |
| **Jadwal** — Jadwal minggu ini | **Keuangan** — Verifikasi pembayaran |
| **Pengumuman** — Pengumuman terbaru | **Pengumuman** — Buat & publikasikan |
| **Nilai** — Input & finalisasi | **Jadwal** — Lihat jadwal |

Hanya **Absensi** yang sudah dibangun; sisanya tampil redup dan tidak bisa
diketuk. Sengaja tetap ditampilkan supaya peta menu terbaca utuh — sama seperti
rujukan yang menyediakan `act.disabled`.

> Satu keterangan tidak bisa disamakan persis: pada rujukan, Pengumuman untuk
> guru berbunyi `${ANNOUNCEMENTS.length} baru`. Tidak ada API pengumuman di
> sini, dan mengarang angka lebih buruk daripada mengganti kalimatnya, jadi
> dipakai "Pengumuman terbaru".

Lembar "Semua menu" beserta pengaturan pintasan **dihapus**: rujukan hanya
punya empat kartu per peran, jadi menyembunyikan sebagian di balik lembar
tambahan menambah langkah tanpa menghemat ruang.

### 1c. Keuangan

Mengikuti varian `financeIsAdmin` pada rujukan:

| Bagian | Sumber |
|---|---|
| Kartu gelap **SISA TAGIHAN** + Tertagih/Terbayar + hitungan jatuh tempo | `GET /finance/fees/summary` (nilai rupiah dipakai apa adanya dari `*_formatted`) |
| **Pembayaran untuk diverifikasi** | `GET /finance/payments?needs_verification=1` |
| Setujui / Tolak | `POST /finance/payments/{id}/verify` dengan `approved` |

Verifikasi punya **dua arah** karena endpoint mewajibkan `approved` — bukan
sekadar "tandai selesai". Selalu lewat dialog konfirmasi: menyangkut uang dan
tak ada tombol urung di aplikasi.

**Varian `financeIsTeacher` pada rujukan tidak dibuat.** Di sana guru
men-ceklis "sudah bayar" per siswa per pos per bulan; backend tak punya konsep
itu — yang ada tagihan (`student_fees`) dan pembayaran (`payments`). Menandai
lunas berarti membuat transaksi pembayaran sungguhan, dan mengarang alur uang di
klien jauh lebih berbahaya daripada belum menyediakannya.

### 1d. Profil & Pengaturan

Baris Profil mengikuti urutan rujukan (`tabIsProfile`): **Absensi Saya ·
Pengaturan & Sinkronisasi · Notifikasi · Tema · Masuk & Keamanan · Versi
Aplikasi · Keluar**.

Layar **Pengaturan** (`/settings`) memuat bagian **Sinkronisasi Server** dari
rujukan: "Terakhir tersinkron" (disimpan `SyncStatusStore` tiap antrean berhasil
terkirim) dan tombol "Sinkronkan dengan Server API" yang mengirim kedua antrean.

Waktu ditampilkan relatif ("3 menit lalu"): pertanyaan di lapangan adalah "data
saya sudah masuk belum?", bukan jam pastinya. Jumlah antrean saja tak cukup —
antrean kosong bisa berarti "sudah terkirim" atau "memang belum ada apa-apa".

Bagian **Master Data Keuangan** pada rujukan (periode laporan, tambah/hapus pos
biaya) **tidak** dibuat: proyek ini menaruh seluruh master data di web dan
menyisakan transaksi saja untuk mobile (§5 dokumen ini). Yang tampil hanya
penunjuk ke web. **Masuk & Keamanan** pun sama — menerbitkan kode akses dan
mencabut sesi adalah wewenang administratif; di sini hanya status koneksi.

### 1b. Metode absensi

Isi menu tab Absensi mengikuti `attMethodOptions` pada rujukan:

| Label | Tujuan |
|---|---|
| **Pindai QR** (admin) / **Tampilkan QR** (peran lain) | S3 kamera scan |
| **Ref ID** | S4 input manual |
| **Checklist** | absen kelas (ceklis per siswa) |
| **Absensi Saya** | QR & Ref ID milik akun yang login, plus riwayat bulan ini |

Seperti rujukan, pil metode **menukar isi layar di tempat** — bukan membuka
layar baru. `ManualInputView` dan `ClassAttendanceView` sengaja dipisahkan dari
Scaffold-nya supaya bisa dipakai dua-duanya: ditanam di tab, dan tetap berdiri
sendiri lewat rute `/manual` serta `/class-attendance`.

Pengecualian: **kamera tetap layar penuh** (`/scan`). Viewfinder butuh seluruh
layar dan punya siklus hidup sendiri (izin kamera, torch, pause saat app ke
latar), jadi menanamnya di dalam tab justru menyulitkan.

Isi tiap panel, mengikuti rujukan:

| Panel | Isi |
|---|---|
| Pindai QR / Tampilkan QR | pil `Masuk`/`Pulang`, bidang gelap berbingkai QR + "Arahkan kamera ke kode QR siswa", catatan "Lokasi wajib · dalam radius sekolah" bila diwajibkan, tombol utama, lalu panel antrean offline |
| Ref ID | pil `Masuk`/`Pulang`, field **Kode Referensi / RFID**, tombol **Periksa**, pratinjau identitas, tombol **Catat Absensi** |
| Checklist | pemilih kelas & tanggal, **Tandai Semua Hadir**, daftar siswa dengan tombol H/S/I/A, bilah simpan |
| Absensi Saya | kartu gelap **KODE PRESENSI ANDA** berisi QR asli + kode + Ref ID, pil status hari ini, lalu daftar **Riwayat** berpil status |

Bingkai QR pada panel Pindai digambar dengan `CustomPaint`, bukan aset:
bentuknya sederhana dan harus ikut warna aksen tema.

**Dua penyimpangan dari rujukan, keduanya disengaja:**

1. **QR di "Absensi Saya" sungguhan, bukan gambar hiasan.** Rujukan hanya
   menggambar tiga sudut penanda. Kode presensi yang tak bisa dipindai tak ada
   gunanya, jadi QR di sini berisi `unique_code` — kolom yang memang
   dicocokkan `ScannerController`. Datanya dari `GET /attendance/me/today`
   (`qr.unique_code`, `rfid_code`), riwayatnya dari `GET /attendance/me/history`.
2. **Kamera tetap layar penuh** (`/scan`). Viewfinder butuh seluruh layar dan
   punya siklus hidup sendiri (izin kamera, torch, jeda saat app ke latar).

### Filter tanggal pada Checklist

Mengganti kelas **atau tanggal** memuat ulang lewat
`GET /attendance/students/daily?classroom_id&date`, yang mengembalikan daftar
siswa **beserta status yang sudah tercatat** pada tanggal itu.

Sebelumnya daftar hanya dimuat saat kelas berganti dan selalu di-set "semua
hadir", sehingga memilih tanggal lain cuma mengganti label — dan menyimpan akan
**menimpa absensi tanggal itu** dengan tanda yang tak pernah dilihat gurunya.

Status `belum_scan` dari server dipetakan ke `hadir`: `AttendanceMark` hanya
memuat status yang boleh disimpan, dan roll call memang dimulai dari anggapan
semua hadir. Saat offline, daftar siswa masih diambil dari endpoint kelas dan
dimulai dari "semua hadir" — status tersimpan tak bisa dibaca tanpa server, tapi
guru tetap bisa bekerja.

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
- **Tampilan (rujukan 2026-08-23):** kotak aksen kecil + nama aplikasi sebagai kop, judul “Masuk” `headlineMedium`; tanpa blok ikon besar.
- **Tujuan:** autentikasi (F1).
- **Elemen:** field Email, Password (toggle lihat), checkbox "Ingat saya", tombol **Masuk**, tautan "Lupa password?".
- **State:** `idle` · `submitting` (tombol loading, field dikunci) · `error` (pesan inline) · `success` → S2.
- **Validasi klien:** email format; password ≥ 6.
- **Error:** 401/403/422/429/offline (lihat F1).

### S2 · Dashboard Scan (Home)
- **Tampilan (rujukan 2026-08-23):** dipecah dua — **Beranda** (sapaan editorial + pil peran + kartu ringkasan gelap + grid aksi tanpa ikon) dan tab **Absensi** (kartu petugas gelap, `PillTabs` Masuk/Pulang, tiga tombol bertingkat, panel antrean). Ringkasan hanya memuat kategori yang benar-benar dikembalikan `GET /dashboard` — tidak ada “Terlambat” karena API tak menyediakannya.
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
- **Tampilan (rujukan 2026-08-23):** `InfoStrip` menggantikan kotak pesan berikon; kartu pratinjau jadi `Panel` teks (nama tebal + meta), status nonaktif jadi pil.
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
- **Tampilan (rujukan 2026-08-23):** baris antrean jadi `Panel` polos tanpa avatar berikon; keadaan kosong memakai `EmptyNote`.
- **Tujuan:** kelola scan tertunda (F8).
- **Elemen:** indikator "Offline · N menunggu"; daftar item (kode, waktu, mode); tombol **Sinkron N data**; hasil per-item setelah sinkron (✓/✕ + alasan).
- **State:** `empty` · `queued` · `syncing` · `partial` (sebagian gagal, tetap tampil).

### S8 · Profil & Info Sekolah
- **Tampilan (rujukan 2026-08-23):** kartu identitas gelap (inisial dalam kotak aksen), peran sebagai pil `primaryContainer`, pengaturan dalam satu `Panel` berbaris label/nilai tanpa ikon pembuka.
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
