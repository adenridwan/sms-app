# 04 — Navigation & Menu (Aplikasi Mobile, berbasis izin)

> Arsitektur navigasi aplikasi mobile "selayaknya app utuh": splash → login →
> **app shell** (bottom navigation + Beranda + Profil) dengan **menu dinamis
> berbasis izin** yang meniru RBAC backend. Prinsip: **hanya transaksi, tanpa
> master**.

> **Status: Tahap 3a SUDAH DIIMPLEMENTASIKAN** (2026-08-02). Tahap 3b–3f masih
> rencana. Peta kode:
>
> | Bagian | Berkas |
> |---|---|
> | App shell (bottom nav 4 tab) | [lib/core/routing/app_shell.dart](../lib/core/routing/app_shell.dart) |
> | Router (`StatefulShellRoute.indexedStack`) | [lib/core/routing/app_router.dart](../lib/core/routing/app_router.dart) |
> | Beranda + grid aksi | [lib/features/home/presentation/home_screen.dart](../lib/features/home/presentation/home_screen.dart) |
> | Katalog aksi → izin (§5) | [lib/features/home/models/action_item.dart](../lib/features/home/models/action_item.dart) |
> | Notifikasi | [lib/features/notifications/](../lib/features/notifications/) |
> | Profil | [lib/features/profile/presentation/profile_screen.dart](../lib/features/profile/presentation/profile_screen.dart) |
>
> Beda dari rancangan di bawah: rute tab Absensi memakai path `/attendance`
> (bukan `/dashboard`), dan `/scan` · `/manual` · `/queue` dipasang di root
> navigator sehingga tampil **penuh tanpa bottom nav** — alur scan tak boleh
> terpotong navigasi. Kartu modul yang belum dibangun (Nilai, Pembayaran,
> Perpustakaan, dll) tetap tampil di grid dalam keadaan **nonaktif berlabel
> "Segera"**, supaya peta fitur terbaca tanpa berpura-pura sudah jadi.

Referensi: `01-EXISTING-SYSTEM.md` (RBAC), `03-API-CONTRACT.md` (endpoint),
`API-GAP-ANALYSIS.md` (kesiapan). Pengguna utama: **Administrator, Guru, Staf**.

---

## 1. Masalah yang diperbaiki

MVP saat ini hanya menampilkan **Absensi** (beranda = ScannerHome), tanpa
navigasi, tanpa Profil, splash hanya "gerbang auth" kilat. Dokumen ini
mendefinisikan struktur aplikasi utuh: banyak modul transaksi, menu menyesuaikan
peran/izin, master tetap di web.

---

## 2. Prinsip Navigasi

1. **App shell dengan Bottom Navigation tetap (4 tab)** — stabil di semua peran:
   `Beranda · Absensi · Notifikasi · Profil`.
2. **Menu transaksi lain tampil sebagai grid "Aksi" di Beranda**, difilter izin
   (mirip "Aksi Cepat" web). Ini menghindari bottom-nav yang berubah-ubah per
   peran (jarring) sekaligus tetap adaptif terhadap RBAC.
3. **Otorisasi = izin dari `GET /auth/me`** (`permissions[]`). Item hanya muncul
   bila user memiliki izinnya; **super admin** melihat semua. Sama persis dengan
   aturan backend.
4. **Hanya transaksi**. Semua menu master (kelola data pokok) **tidak** ada.
5. **Guard persona** tetap: hanya `admin`, `staff`, `teacher`, `super_admin`
   yang boleh masuk aplikasi (lihat `canUseAttendanceApp`). Siswa/orang tua di
   luar cakupan aplikasi petugas ini.

---

## 3. Peta Alur (shell)

```
Splash (branded, cek token via /auth/me)
  ├─ tidak ada sesi → Login (password ATAU kode akses)  [sudah ada]
  ├─ backend tak terjangkau + ada cache → masuk app (sesi "belum terverifikasi")
  └─ ada sesi ────────────────────────────────┐
                                               ▼
App Shell  ── Bottom Navigation (tetap) ───────────────────────
  1) Beranda   : sapaan + ringkasan + GRID AKSI (per izin)
  2) Absensi   : scan / rekap / izin  (tab utama karena paling sering)
  3) Notifikasi: daftar notifikasi + tandai baca
  4) Profil    : user, sekolah, peran, tema, versi, keluar

Grid Aksi di Beranda (muncul sesuai izin):
  [Absensi] [Nilai] [Pembayaran] [Perpustakaan] [Pengumuman] [Izin] [Jadwal] ...
        └── tap → masuk ke alur modul terkait
```

> Alternatif dipertimbangkan & ditolak: bottom-nav dinamis per-peran (membingungkan
> saat berpindah akun) dan drawer penuh (kurang khas mobile transaksi cepat).
> Keputusan: **4 tab tetap + grid aksi berbasis izin**.

---

## 4. Aturan Transaksi vs Master

| Modul | Master (❌ web saja) | Transaksi (✅ mobile) |
|---|---|---|
| Akademik | Tahun ajaran, semester, kelas, mapel, jurusan, kurikulum, jadwal (kelola) | Lihat jadwal (read-only) |
| Siswa/Guru/Staf | CRUD identitas | — |
| Absensi | Setelan absensi | Scan masuk/pulang, absen kelas (bulk), rekap, izin (ajukan/approve) |
| Nilai | Jenis ujian (kelola) | Input nilai, finalisasi |
| Keuangan | Jenis biaya, struktur, diskon, metode | Terima & verifikasi pembayaran, generate tagihan, laporan (lihat) |
| Perpustakaan | Katalog buku, anggota (kelola) | Peminjaman, pengembalian, perpanjang, reservasi |
| Notifikasi | — | Lihat notifikasi, pengumuman (buat/publish) |
| Pengguna/Role/Tenant/Setelan | Semua manajemen | — |

---

## 5. Katalog Menu Transaksi → Izin → Endpoint

Legenda aktor: A=Admin, G=Guru/Wali Kelas, S=Staf/TU, B=Bendahara, P=Pustakawan,
KS=Kepala/Wakil (view).

| Menu (transaksi) | Izin pemicu tampil | Endpoint inti | Aktor |
|---|---|---|---|
| **Absensi — Scan** | `attendance.record` / `attendance.check-in` | `GET /scan/bootstrap`, `POST /scan`, `/scan/lookup`, `/scan/sync-offline` | A,G,S |
| **Absensi — Kelas (bulk)** | `attendance.record` | `GET /academic/classrooms/{id}/students`, `POST /attendance/students/bulk` | G |
| **Absensi — Rekap** | `attendance.view` / `attendance.report` | `GET /attendance/students/daily`, `/students/summary` | A,G,S,KS |
| **Izin — Ajukan** | `attendance.view-own` / (staf) | `POST /public/izin/submit` atau `POST /attendance/permissions` | G,S |
| **Izin — Approve** | `attendance.manage` | `GET /attendance/permissions`, `POST /{p}/approve`,`/reject` | A |
| **Nilai — Input** | `grades.input` | `POST /exams/{exam}/scores/bulk`, `GET /grades` | G |
| **Nilai — Finalisasi** | `grades.finalize` | `POST /grades/finalize` | G,KS |
| **Pembayaran — Terima** | `payments.create` | `GET /finance/fees`, `POST /finance/payments`, `/{p}/receipt` | B,A |
| **Pembayaran — Verifikasi** | `payments.verify` | `POST /finance/payments/{p}/verify` | B,A |
| **Tagihan — Generate** | `fees.generate` | `POST /finance/fees/generate` | B |
| **Laporan Keuangan (lihat)** | `finance.report` | `GET /finance/reports/summary|monthly|outstanding` | B,A,KS |
| **Perpustakaan — Peminjaman** | `loans.create` | `GET /library/books/{b}/copies`, `POST /library/loans` | P |
| **Perpustakaan — Pengembalian** | `loans.return` | `POST /library/loans/{l}/return`,`/extend` | P |
| **Perpustakaan — Reservasi** | `loans.manage` | `GET/POST /library/reservations` | P |
| **Pengumuman — Buat/Publish** | `announcements.create` / `.publish` | `POST /notifications/announcements`, `/{a}/publish` | A |
| **Pengumuman/Notifikasi — Lihat** | `notifications.view` | `GET /notifications`, `/announcements` | semua |
| **Jadwal (lihat)** | `schedules.view` | `GET /academic/schedules`, `/classrooms/{id}/schedule`, *(usul `GET /me/schedule`)* | G,S |

> Catatan: nama izin mengikuti `PermissionSeeder`. Beberapa aksi izin/leave pada
> backend memakai controller absensi; pemetaan izin di atas mengikuti yang paling
> dekat dan perlu dikonfirmasi saat implementasi.

---

## 6. Komposisi Menu per Peran

Beranda menampilkan grid aksi = irisan (izin peran × katalog di §5).

| Peran | Grid aksi yang muncul di Beranda |
|---|---|
| **Administrator** | Absensi (scan/rekap) · Izin (approve) · Pengumuman (buat) · Pembayaran (verifikasi) · Laporan · Notifikasi |
| **Guru** | Absensi kelas & scan · Nilai (input/finalisasi) · Jadwal · Izin (ajukan) · Notifikasi |
| **Wali Kelas** | = Guru + Rekap absensi kelasnya |
| **Staf / Tata Usaha** | Absensi (scan) · Izin · Pengumuman (lihat) · Notifikasi |
| **Bendahara** | Pembayaran (terima/verifikasi) · Tagihan (generate) · Laporan keuangan · Notifikasi |
| **Pustakawan** | Peminjaman · Pengembalian · Reservasi · Notifikasi |
| **Kepala/Wakil** | Rekap absensi (lihat) · Laporan · Nilai (finalisasi) · Notifikasi |
| **Super Admin** | Semua di atas |

Bottom-nav tetap 4 tab untuk semua; perbedaan peran ada di **isi grid Beranda**
dan tab **Absensi** (jika tak punya izin absensi, tab Absensi disembunyikan/diganti).

---

## 7. Layar Baru yang Dibutuhkan (spesifikasi ringkas)

| Layar | Isi |
|---|---|
| **Splash** (branded) | Logo + nama app, cek token; transisi ke Login/Shell |
| **App Shell** | Scaffold + `BottomNavigationBar` (Beranda/Absensi/Notifikasi/Profil) |
| **Beranda** | Sapaan (nama, sekolah, peran), ringkasan hari ini, **grid aksi berbasis izin** |
| **Profil** | Avatar/inisial, nama, email, peran, sekolah aktif, pilihan tema, versi app, tombol Keluar |
| **Notifikasi** | Daftar + tandai baca (`/notifications`) |
| *(modul)* | Nilai, Pembayaran, Perpustakaan, Pengumuman — masing-masing alur sendiri |

Layar Absensi (Scan, Ref ID, Rekap, Antrean) **sudah ada** — tinggal dipindah
menjadi salah satu tab, bukan beranda tunggal.

---

## 8. Ketergantungan Backend

- **Sudah ada sejak dokumen ini ditulis** (2026-08-02):
  - `POST /auth/login-otp` — masuk dengan kode akses sekali-pakai dari admin.
  - Sesi offline-tolerant + indikator koneksi (dot hijau/kuning/merah) di app bar,
    lihat [../../docs/06-AUTH-FLOW.md](../../docs/06-AUTH-FLOW.md) §3 & §4a.
  - Menu web **Pengaturan → Keamanan Login** (riwayat login, buat/cabut kode
    akses, cabut sesi perangkat).
  - **Push notification masih belum ada sama sekali** (tak ada FCM di mobile
    maupun backend). Ini prasyarat kalau nanti OTP mau dikirim otomatis ke app
    (Opsi B) — ditunda, karena butuh device sudah pernah login lebih dulu.
- ✅ **`GET /notifications` kini sudah jadi** (2026-08-02) —
  `NotificationController` diisi (list/unread-count/read/read-all/delete),
  model `Notification` dibuat di atas tabel yang memang sudah ada. Query
  di-scope per `user_id`, bukan hanya per tenant; sudah diuji bahwa user tak
  bisa membaca/menghapus notifikasi user lain di tenant yang sama (404).
- ✅ **Beranda memakai `GET /dashboard`** — endpoint ini ternyata sudah lama
  ada dan berfungsi (stats per paket peran). Usulan `GET /me/dashboard` di §8
  versi lama **tidak diperlukan**.
- ⚠️ **Modul besar masih stub di backend** (diperiksa 2026-08-02): Exam/Nilai
  **4 dari 4** controller stub, Library **6 dari 6** stub, Finance **5 dari 7**
  stub (hanya `PaymentController` & `FeeTypeController` yang nyata). Jadi
  Tahap 3c–3e bukan pekerjaan mobile — masing-masing perlu modul backend dulu.
  Kartu aksinya sengaja tampil nonaktif berlabel "Segera".
- **Siap dipakai**: absensi, izin, notifikasi, jadwal (via `/academic/*`),
  pembayaran (sebagian). Nama izin di §5 sudah diverifikasi cocok dengan
  `/auth/me` (8 dari 8 izin yang dipakai grid aksi).
- **Perlu ditambah (usul)**:
  - `GET /me/dashboard` — ringkasan Beranda per-peran (angka absensi hari ini,
    tugas verifikasi, dll) agar Beranda tidak menambal banyak panggilan.
  - `GET /me/schedule` — jadwal "saya" untuk guru (kini harus lewat
    `/academic/schedules` + filter).
  - *(opsional)* endpoint "menu" yang mengembalikan daftar menu boleh-tampil
    (server-driven), agar konsisten dengan `MenuVisibilityService` web. Bila tak
    ada, cukup pakai `permissions[]` dari `/auth/me`.
- **Tetap tidak diekspos**: seluruh master (akademik, siswa/guru/staf CRUD,
  jenis biaya, katalog buku, pengguna, role, tenant, setelan).

---

## 9. Model Otorisasi Klien (mirror backend)

```
hasPermission(p):
  if user.user_type == 'super_admin' → true
  else → user.permissions.contains(p)

menuVisible(item):
  hasPermission(item.requiredPermission)   // §5
```

- Sumber kebenaran tetap **server**: setiap aksi tetap divalidasi backend
  (permission middleware). Menu klien hanya lapisan tampilan.
- Untuk `/scan/*` yang saat ini belum ber-gate permission (lihat
  `02-MOBILE-REQUIREMENTS.md §9`), tab Absensi tetap dibatasi di klien ke
  peran yang wajar sampai backend menambah `permission:attendance.record`.

---

## 10. Rencana Implementasi Bertahap (usulan, belum dikerjakan)

| Tahap | Isi |
|---|---|
| ~~3a~~ ✅ | App shell (bottom nav) + Beranda + Profil; Absensi jadi tab — **selesai** |
| 3b | Menu dinamis berbasis izin (grid aksi) + Notifikasi/Pengumuman |
| 3c | Modul Nilai (input/finalisasi) |
| 3d | Modul Pembayaran (terima/verifikasi/tagihan/laporan) |
| 3e | Modul Perpustakaan (pinjam/kembali/reservasi) |
| 3f | (Backend) `/me/dashboard` & `/me/schedule`, lalu Beranda/Jadwal dirapikan |

> Dokumen ini akan disinkronkan dengan `08-UI-UX-FLOW.md` saat storyboard tiap
> modul transaksi dirinci. Tidak ada kode yang ditulis pada tahap analisis ini.
