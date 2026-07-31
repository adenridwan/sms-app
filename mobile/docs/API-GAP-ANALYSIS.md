# API Gap Analysis — Kesiapan untuk Mobile

> Menilai endpoint API v1 yang ada terhadap kebutuhan **aplikasi mobile**
> (fokus persona Siswa, Orang Tua, Guru/Wali Kelas, dan Operator Scan).
> Endpoint dikelompokkan ke 4 kategori, ditutup dengan **rekomendasi sebelum
> implementasi**. Read-only — belum ada perubahan kode.

Skala penilaian mengacu pada `03-API-CONTRACT.md` dan `01-EXISTING-SYSTEM.md`.

---

## A. ✅ Ready for Mobile (pakai apa adanya)

Endpoint yang sudah cocok dikonsumsi langsung oleh mobile tanpa perubahan
berarti.

| Endpoint | Persona | Catatan |
|---|---|---|
| `POST /auth/login`, `POST /auth/logout` | semua | Bearer token siap pakai |
| `GET /auth/me` | semua | sumber roles+permissions utk gating menu |
| `PUT /auth/profile`, `PUT /auth/password` | semua | update profil & password |
| `POST /auth/forgot-password`, `/reset-password` | semua | reset via email |
| `GET /notifications`, `/unread-count`, `POST /{n}/read`, `/read-all`, `DELETE /{n}` | semua | pusat notifikasi |
| `GET /notifications/announcements` | semua | pengumuman |
| `GET /dashboard`, `/dashboard/stats` | staf/guru/admin | ringkasan |
| `GET /scan/bootstrap`, `POST /scan`, `/scan/sync-offline`, `/scan/lookup` | **Operator/Guru** | absensi QR/RFID + **offline sync** + GPS |
| `GET /attendance/qr/students/{s}`, `/teachers/{t}` | pemilik/staf | tampil kartu QR |
| `GET /attendance/permissions`, `POST /{p}/approve`,`/reject` | guru/admin | kelola izin |
| `GET /academic/schedules`, `/classrooms/{c}/schedule` | guru/siswa | jadwal |
| `POST /public/izin/*`, `/public/cek-kehadiran`, `/public/riwayat-kehadiran` | Orang Tua (tanpa akun) | portal publik |

> Fitur **scan + sinkron offline + GPS** adalah kekuatan utama yang sudah matang
> untuk aplikasi operator/guru.

---

## B. 🟡 Needs Modification (ada, tapi perlu penyesuaian)

Endpoint yang fungsional tapi belum pas untuk konsumsi mobile end-user.

| Endpoint / Area | Masalah | Penyesuaian yang disarankan |
|---|---|---|
| `GET /students`, `/students/{id}/*` | Ditujukan staf/guru; siswa/ortu punya permission `*-own` tapi **tak ada jalur "own"**. `visibleTo` belum tentu meng-handle role siswa/ortu | Tambah scope/endpoint self-service (lihat kategori C) atau pastikan `visibleTo` mengembalikan hanya diri/anak untuk role `siswa`/`orang_tua` |
| `GET /students/{id}/attendance` `/grades` `/fees` | Butuh tahu `student_id` lebih dulu; siswa/ortu belum tentu tahu id-nya | Sediakan varian berbasis user login (`/me/...`) |
| Bentuk **error** tidak konsisten | FormRequest → `{message,errors}`; manual → `{success,message,errors}` | Standarkan handler validasi ke envelope `success:false` |
| `avatar_url` / `photo_url` (`asset('storage/..')`) | URL absolut berbasis `APP_URL` bisa tak terjangkau perangkat | Pastikan `APP_URL` benar untuk mobile, atau kembalikan URL absolut valid/CDN |
| **Pagination** default `per_page=15` | OK, tapi tak semua list konsisten memakai pola yang sama | Standarkan query & envelope pagination lintas modul |
| **Token 7 hari tanpa refresh** | Sesi mobile umumnya lebih panjang | Tambah refresh-token atau perpanjang masa berlaku khusus device mobile |
| `GET /attendance/reports/pdf|excel`, `qr/export` | Output biner/berat, throttle `exports` 3/mnt | Untuk mobile beri ringkasan JSON; unduhan berat tetap web |

---

## C. ❌ Missing Endpoint (belum ada, dibutuhkan mobile)

Kebutuhan mobile yang **belum** terpenuhi endpoint mana pun.

| Kebutuhan | Persona | Usulan endpoint |
|---|---|---|
| **Profil "saya" lengkap** (siswa: data + kelas + no absen; ortu: daftar anak) | Siswa, Ortu | `GET /me/student`, `GET /me/children` |
| **Absensi saya / anak saya** | Siswa, Ortu | `GET /me/attendance?from&to`, `GET /me/children/{id}/attendance` |
| **Nilai saya / anak** | Siswa, Ortu | `GET /me/grades`, `GET /me/children/{id}/grades` |
| **Tagihan & pembayaran saya / anak** | Siswa, Ortu | `GET /me/fees`, `GET /me/children/{id}/fees` |
| **Jadwal saya** (siswa/guru) | Siswa, Guru | `GET /me/schedule` |
| **Self check-in siswa** (scan QR sendiri + GPS/foto sesuai `attendance_settings`) | Siswa | `POST /me/attendance/check-in` (hormati `require_location`/`require_photo`) |
| **Registrasi device push (FCM/APNs)** | semua | `POST /me/devices`, `DELETE /me/devices/{id}` + kirim push |
| **Info versi & konfigurasi app** (force-update, feature flag, alamat WA sekolah) | semua | `GET /mobile/config` |
| **Kartu QR "saya"** tanpa perlu id | Siswa, Guru | `GET /me/qr` |
| **Ringkasan dashboard per-persona** (siswa/ortu) | Siswa, Ortu | `GET /me/dashboard` |

> Catatan: portal publik `POST /public/cek-kehadiran` & `/riwayat-kehadiran`
> sebagian menutup kebutuhan orang tua **tanpa akun**, namun untuk pengalaman
> aplikasi ber-login tetap disarankan endpoint `/me/*` di atas.

---

## D. 🚫 Should NOT be exposed to Mobile (end-user app)

Tetap khusus back-office/web; jangan diekspos ke aplikasi siswa/ortu/guru.
(Boleh dipertimbangkan untuk **aplikasi admin terpisah** dengan role ketat.)

| Area | Alasan |
|---|---|
| `/admin/users`, `/admin/roles`, `/admin/permissions` | Manajemen akun & RBAC — sensitif |
| `/admin/schools`, `/super-admin/tenants` | Manajemen tenant tingkat sistem |
| `/admin/audit-logs`, `/admin/activity-logs`, `/super-admin/health|metrics` | Audit & observability |
| `/settings/menu`, `/settings/school`, `/attendance/settings` | Konfigurasi sekolah/sistem |
| `/attendance/qr/export`, `/qr/generate-rfid`, `/card-templates/*` | Produksi kartu (berat, operator desktop) |
| `*/export`, `*/import`, `*/template` (Excel/PDF) di Academic/Attendance/Finance | Operasi berkas massal |
| `POST /attendance/*/notify-daily`, broadcast pengumuman | Aksi administratif/berdampak luas |
| `DELETE`/`PUT` master data (academic, finance, library, students) | Mutasi data master — cukup di web |

---

## E. Ringkasan Kesiapan per Persona

| Persona | Kesiapan | Kekurangan utama |
|---|---|---|
| **Operator/Guru (scan)** | 🟢 Tinggi | Sudah bisa dibangun dari `/scan/*` + `/attendance/*` sekarang |
| **Guru/Wali Kelas** | 🟡 Sedang | Ambil absensi & lihat kelas OK; input nilai perlu `student_id`; butuh `/me/schedule` |
| **Siswa** | 🔴 Rendah | Hampir semua butuh endpoint `/me/*` (kategori C) |
| **Orang Tua** | 🔴 Rendah | Butuh `/me/children/*`; sebagian tertutup portal publik |

---

## F. Rekomendasi Sebelum Implementasi

1. **Tentukan cakupan MVP mobile.** Rekomendasi mulai dari yang paling siap:
   **(1) App Operator/Guru — Absensi Scan** (bisa jalan dengan API sekarang),
   lalu **(2) App Siswa/Orang Tua** (butuh endpoint `/me/*` baru).

2. **Bangun namespace API self-service `/me/*`** (atau `/api/v1/mobile/*`) yang
   memetakan user login → data miliknya/anaknya, memanfaatkan permission
   `*-own` yang sudah ada. Ini menutup mayoritas gap kategori C tanpa membocorkan
   endpoint staf.

3. **Standarkan kontrak respons & error** menjadi satu envelope
   (`{success,message,data,errors}`), termasuk override handler
   `ValidationException` agar FormRequest ikut format `success:false`.

4. **Perbaiki URL media** untuk konteks lintas-host (mobile): pastikan `APP_URL`
   produksi benar atau kembalikan URL absolut/CDN yang valid (pola root-relatif
   di `SchoolProfileController` bagus untuk web same-origin, tapi mobile butuh
   absolut).

5. **Strategi token untuk mobile:** perpanjang masa berlaku atau tambah
   refresh-token; simpan token di secure storage; tangani `401`→re-login dan
   `429`→`Retry-After`.

6. **Tambah push notification** (registrasi device FCM/APNs + pemetaan ke
   `NotificationDispatcher` yang sudah multi-channel).

7. **Self check-in siswa** hanya dibuka bila kebijakan sekolah mengizinkan;
   hormati `attendance_settings` (`require_location`, `require_photo`,
   `location_radius`, `working_days`, toleransi telat).

8. **Endpoint `GET /mobile/config`** untuk force-update, feature flag, dan
   info sekolah (nama/logo/WA) agar app bisa beradaptasi tanpa rilis ulang.

9. **Jangan** ekspos kategori D ke aplikasi end-user. Jika perlu admin mobile,
   buat aplikasi/role terpisah dengan guard ketat.

10. **Keamanan multi-tenant:** untuk user biasa jangan pernah percaya
    `X-Tenant-ID` dari klien; andalkan `tenant_id` akun. Uji bahwa data lintas
    tenant tidak bocor.

### Urutan kerja yang disarankan
```
Fase 0  Analisis (dokumen ini) ✅
Fase 1  Scaffold app mobile di folder mobile/ + integrasi Auth (/auth/login, /me)
Fase 2  MVP Operator/Guru: Scan (/scan/*), daftar & rekap absensi
Fase 3  (Backend) Tambah /me/* self-service + standardisasi error + media URL
Fase 4  App Siswa/Orang Tua: profil, absensi, nilai, tagihan, pengumuman
Fase 5  Push notification + /mobile/config + polish
```

> Perubahan backend pada Fase 3+ diajukan sebagai proposal terpisah; fase
> analisis ini **tidak** mengubah kode aplikasi.
