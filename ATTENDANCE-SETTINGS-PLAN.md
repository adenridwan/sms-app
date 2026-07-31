# Rencana: Pengaturan Absensi — Discoverability, Channel Email, & Perbaikan Celah

> Status: **PERENCANAAN** (belum ada eksekusi kode). Dokumen ini memetakan
> temuan hasil analisa live (Chrome) + audit kode, lalu menyusun langkah
> perbaikan. Semua path relatif dari root repo `sms-app/`.

---

## 0. Ringkasan Eksekutif

Tiga hal yang dibahas:

1. **Menu "Pengaturan Absensi" sebenarnya SUDAH ADA** dan berfungsi
   (`/attendance/settings`), tapi **tidak muncul di sidebar** — hanya bisa
   diakses lewat card di bagian bawah halaman `/attendance` atau URL langsung.
   Itulah kenapa terasa "tidak ada menunya".
2. **Perlu tambah channel Email** (toggle aktif/nonaktif + konfigurasi) di
   samping WhatsApp & Telegram yang toggle-nya sudah ada.
3. **Ada beberapa celah/bug** yang ditemukan saat audit — didokumentasikan di
   §4 untuk diperbaiki bareng.

---

## 1. Temuan: Kenapa menu "tidak ketemu"

### 1.1 Halaman-nya ada & jalan
- Route web: `routes/web.php:101` → `/attendance/settings`
- Halaman React: `backend/resources/js/pages/attendance/settings/Index.tsx`
- Sudah diverifikasi live: form Jam Masuk (06:00), Batas Masuk (07:30),
  Toleransi 15 menit, Jam Pulang (14:00–17:00), Hari Kerja, tab Lokasi &
  Notifikasi — semua ter-render dan editable.

### 1.2 Akar masalah discoverability
Sidebar (`backend/resources/js/layouts/MainLayout.tsx:115-124`), submenu
**Absensi** hanya berisi:
- Absensi Siswa (`/attendance/students`)
- Absensi Pegawai (`/attendance/teachers`)
- Rekap Absensi (`/attendance/reports`)

**Tidak ada** entri "Pengaturan" di submenu Absensi. Satu-satunya jalan ke
`/attendance/settings`:
- Card "Pengaturan" di baris **paling bawah** grid `/attendance`
  (`pages/attendance/Index.tsx:85-92`) — mudah terlewat.
- Ketik URL langsung.

Membingungkan lagi: ada menu sidebar **"Pengaturan"** (line 165) tapi itu
menuju `/settings` (pengaturan umum) & `/settings/users`, **bukan** pengaturan
absensi.

---

## 2. Rencana A — Munculkan menu di sidebar (KEPUTUSAN FINAL)

**Keputusan user:**
- Menu ditaruh di **group "Pengaturan"** (BUKAN di submenu Absensi) — karena
  di group menu lain memang tidak ditemukan.
- Rule akses: **hanya admin / tata usaha (TU) / super admin**.

### 2.1 Permission yang dipakai: `settings.attendance` (sudah ada)
- `settings.attendance` **sudah terdaftar** & sudah dimiliki role `admin`
  (`RoleSeeder.php:69`).
- `super_admin` otomatis lolos (punya semua permission `*` + bypass di sidebar
  `MainLayout.tsx:306`).
- `tata_usaha` **belum** punya → perlu ditambahkan (lihat §2.3).
- `kepala_sekolah` sengaja **tidak** diberi → sesuai permintaan (hanya
  admin/TU/super admin).

### 2.2 Perubahan frontend — `MainLayout.tsx` group "Pengaturan"

Struktur sekarang (`MainLayout.tsx:164-172`):
```ts
{
    title: 'Pengaturan',
    icon: Settings,
    permission: 'settings.view',          // ← gating di level GROUP
    children: [
        { title: 'Umum', href: '/settings', permission: 'settings.view' },
        { title: 'Pengguna', href: '/settings/users', superAdminOnly: true },
    ],
},
```

Menjadi:
```ts
{
    title: 'Pengaturan',
    icon: Settings,
    // HAPUS permission di level group — biarkan filter jalan di level anak.
    // Group otomatis tersembunyi kalau tak ada anak yang lolos
    // (MainLayout.tsx:357: visibleChildren.length === 0 → return null).
    children: [
        { title: 'Umum', href: '/settings', permission: 'settings.view' },
        // TAMBAH:
        { title: 'Pengaturan Absensi', href: '/attendance/settings',
          permission: 'settings.attendance' },
        { title: 'Pengguna', href: '/settings/users', superAdminOnly: true },
    ],
},
```

**Kenapa hapus `permission` di level group?** Sidebar mengecek permission GROUP
lebih dulu (`MainLayout.tsx:349`) sebelum anak. Kalau group tetap dikunci
`settings.view`, TU (yang tak punya `settings.view`) tak akan pernah melihat
group-nya sama sekali — anak "Pengaturan Absensi" ikut hilang. Dengan memindah
gating ke level anak, hasilnya presisi:

| Role | settings.view | settings.attendance | Terlihat di group "Pengaturan" |
|---|---|---|---|
| super_admin | ✓ (bypass) | ✓ (bypass) | Umum + **Pengaturan Absensi** + Pengguna |
| admin | ✓ | ✓ | Umum + **Pengaturan Absensi** |
| tata_usaha | ✗ | ✓ *(setelah §2.3)* | **Pengaturan Absensi** saja |
| kepala_sekolah | ✓ | ✗ | Umum saja (tanpa Pengaturan Absensi) ✓ |
| guru / siswa / ortu | ✗ | ✗ | Group tersembunyi total ✓ |

> Efek samping positif: TU hanya melihat "Pengaturan Absensi", tidak melihat
> "Umum" (karena tak punya `settings.view`) — persis yang diinginkan.

### 2.3 Perubahan seeder — `RoleSeeder.php`
Tambahkan `settings.attendance` ke daftar permission role `tata_usaha`
(`RoleSeeder.php:167-185`), lalu jalankan ulang seeder / sinkronisasi permission.
`admin` & `super_admin` tidak perlu diubah.

> Catatan: gating sidebar hanya menyembunyikan menu (UX). Penegakan sebenarnya
> ada di server — lihat **GAP-1**, yang memakai permission **sama**
> (`permission:settings.attendance`) agar konsisten.

---

## 3. Rencana B — Tambah Channel Email (toggle aktif/nonaktif)

### 3.1 Kondisi saat ini
- WhatsApp & Telegram: toggle `wa_enabled` / `telegram_enabled` **sudah ada**
  (UI `settings/Index.tsx:405,486`; DB `notification_settings`; dicek di
  `NotificationDispatcher`).
- Email: **belum ada sama sekali** — tidak ada `EmailService`, kolom
  `email_enabled`, atau pemanggilan `Mail::` di jalur notifikasi.
- Yang sudah tersedia sebagai modal: `StudentGuardian.email` sudah ada,
  `users.email` ada, dan Laravel Mail sudah dikonfigurasi global di `.env`
  (`MAIL_MAILER=smtp`, `MAIL_FROM_ADDRESS`).

### 3.2 KEPUTUSAN yang harus diambil dulu
**SMTP global vs SMTP per-tenant?**

| Opsi | Plus | Minus |
|---|---|---|
| **Global** (pakai `.env` SMTP) | Simpel, cukup toggle `email_enabled` | Pengirim sama untuk semua sekolah; tak konsisten dg pola WA/Telegram |
| **Per-tenant** (SMTP per sekolah) | Konsisten; tiap sekolah pakai email sendiri | Perlu banyak field + `smtp_password` WAJIB di-encrypt |

> Rekomendasi awal: mulai dari **Global + toggle** (cepat, aman), naikkan ke
> per-tenant bila memang dibutuhkan multi-sekolah dengan email berbeda.

### 3.3 Langkah implementasi (channel Email)

| Layer | File | Aksi |
|---|---|---|
| Migration | `database/migrations/xxxx_add_email_to_notification_settings.php` | Tambah `email_enabled` (bool, default false). Jika per-tenant: `smtp_host/port/username/password/encryption`, `email_from_address`, `email_from_name` |
| Model | `app/Infrastructure/Persistence/Eloquent/Attendance/NotificationSetting.php` | Tambah ke `$fillable` + `casts` (`email_enabled=>bool`). Tambah `notify_*` via email jika perlu. Tambah `isEmailConfigured()`. Encrypt `smtp_password` via cast `encrypted` (jika per-tenant) + masukkan ke `$hidden` |
| Service | `app/Domain/Notification/Services/EmailService.php` (BARU) | Pola sama `WhatsAppService`: `initializeForTenant()`, `send($to,$subject,$body)`, `isAvailable()` |
| Dispatcher | `app/Domain/Notification/Services/NotificationDispatcher.php` | Tambah `sendEmail()` privat; panggil paralel dg `sendWhatsApp()`/`sendToTelegramDefault()` di tiap `dispatch*`. Best-effort (try/catch + Log::warning), tidak boleh menggagalkan scan |
| Controller | `app/Http/Controllers/Api/V1/Attendance/AttendanceSettingController.php` | Tambah field email di `$request->validate()`; `testEmail()` analog `testWhatsApp()` |
| Route | `routes/api_v1.php` | `POST attendance/settings/test-email` |
| Frontend | `backend/resources/js/pages/attendance/settings/Index.tsx` | Card "Email" baru di tab Notifikasi, pola identik card WhatsApp/Telegram (Switch `email_enabled` + field konfigurasi + tombol Test) |
| Types | `backend/resources/js/types/attendance.ts` | Tambah field email di `NotificationSettings` |
| Service FE | `backend/resources/js/services/attendance.ts` | Tambah `testEmail()` |

---

## 4. Celah / Bug untuk Diperbaiki

> Diurutkan dari paling kritikal. GAP-1 & GAP-2 adalah inti dari kasus
> "absen keluar jam 23:00" + "siapa saja bisa ubah setting".

### GAP-1 — 🔴 KRITIS: Pengaturan absensi bukan admin-only
**Bukti live:** login sebagai `guru1` (guru, "BS" Budi Santoso), halaman
`/attendance/settings` **ter-load penuh & bisa diedit**.

- API `GET/PUT /api/v1/attendance/settings` (`api_v1.php:206-210`) hanya
  dibungkus `auth:sanctum` — **tanpa** middleware `role:`/`permission:`.
- Route web `/attendance/settings` (`web.php:40`) hanya `auth` biasa.
- Card "Pengaturan" di `/attendance` tidak digating role.

**Dampak:** guru/siswa/ortu bisa ubah jam masuk-pulang, geofence, bahkan
WA API key & Telegram bot token (dan nanti SMTP password) — cukup tahu URL.

**Perbaikan (selaras dengan §2 — permission `settings.attendance`):**
1. Bungkus grup route API settings dengan `middleware(['permission:settings.attendance'])`.
   Alias `permission` sudah terdaftar (`bootstrap/app.php:31`). super_admin (wildcard),
   admin, dan TU (setelah §2.3) lolos; guru/siswa/ortu → 403.
2. Gating route web `/attendance/settings` + card "Pengaturan" di `/attendance`
   dengan permission yang sama, sehingga guru tak bisa membukanya via URL
   langsung (yang saat ini masih bisa — terbukti live).
3. Tidak perlu bikin permission baru — `settings.attendance` sudah ada; cukup
   tambahkan ke role `tata_usaha` di `RoleSeeder` (§2.3).

### GAP-2 — 🔴 KRITIS: Window scan pulang tidak divalidasi (bug jam 23:00)
`AttendanceScanService::processStudentCheckOut` (`AttendanceScanService.php:183-234`)
hanya cek "belum checkout hari ini" — **tidak ada validasi jam** terhadap
`check_out_start`/`check_out_end`. Selama sudah check-in & belum check-out,
scan pulang diterima jam berapa pun, termasuk 23:00.

**Perbaikan:** tolak (atau tandai `perlu_verifikasi`) scan pulang di luar rentang
`check_out_start`–`check_out_end`. Sediakan flag setting `allow_checkout_outside_window`
untuk override manual (mis. pulang cepat karena sakit).

### GAP-3 — 🟠 Scan masuk tidak ada batas jam maksimal
Check-in hanya dihitung telat, tak pernah ditolak. Datang jam 13:00 tetap
tercatat "Hadir" (telat sekian menit). Perlu keputusan: apakah lewat jam X jadi
"Alfa"/ditolak, atau tetap diterima sebagai telat berat.

### GAP-4 — 🟠 Kredensial tersimpan plaintext
`wa_api_key` & `telegram_bot_token` disimpan **plaintext** di DB (tak ada cast
`encrypted` di `NotificationSetting.php:38-50`; hanya `$hidden` dari response).
Kalau channel Email per-tenant ditambah, **jangan** ulangi — `smtp_password`
WAJIB `encrypted`. Sekalian pertimbangkan migrasi enkripsi untuk 2 field lama.

### GAP-5 — 🟡 Perubahan setting tidak di-audit
`AttendanceSettingController::update()` tidak memanggil `AttendanceAuditLog::log()`,
padahal service audit sudah dipakai untuk scan. Jam masuk/pulang bisa diubah
tanpa jejak siapa & kapan. **Perbaikan:** log perubahan setting (old vs new).

### GAP-6 — 🟡 Mode scanner ditentukan client-side (bisa dimanipulasi)
`scanner/Index.tsx:112-125` memilih mode "masuk"/"pulang" dari `new Date()` di
browser vs `check_out_start`. Jam device yang salah → perilaku tak terduga.
**Perbaikan:** jadikan server sebagai sumber kebenaran waktu (bootstrap sudah
mengirim `current_time`; gunakan itu, bukan jam client).

### GAP-7 — 🟡 Tidak ada auto-checkout untuk yang lupa scan pulang
Tidak ada scheduled job (`routes/console.php` tak punya `auto-checkout`).
Record `check_out_time` tetap `null` sampai ada scan manual (bisa jam 23:00 atau
besok pagi). **Perbaikan:** command terjadwal di `check_out_end` untuk menutup
absensi yang belum checkout (tandai `auto_checkout=true`), atau biarkan null tapi
tolak checkout telat (lihat GAP-2).

### GAP-8 — 🟢 Poin keterlambatan tanpa batas atas
`LateCalculationService` + `Student::addViolationPoints()`: 1 poin/menit tanpa
cap. Telat 3 jam = 180 poin. **Perbaikan:** tambah setting `max_late_points`
atau cap per kategori.

### GAP-9 — 🟢 Setting per-tenant, bukan per-jenjang
`attendance_settings` unik per `tenant_id`. SD & SMA dalam satu tenant pakai jam
yang sama. **Catatan desain** (bukan bug) — pertimbangkan skoping per jenjang/kelas
bila dibutuhkan.

---

## 5. Urutan Eksekusi yang Disarankan

1. **GAP-1** (admin-only) — fondasi keamanan; wajib sebelum menambah field
   kredensial baru (Email).
2. **Rencana A** (menu sidebar, sudah digating permission dari GAP-1).
3. **GAP-2 + GAP-7** (window checkout + lupa scan) — inti kasus jam 23:00.
4. **Rencana B** (channel Email) — dengan `smtp_password` encrypted (GAP-4).
5. **GAP-5, GAP-6** (audit + server-time).
6. **GAP-3, GAP-8, GAP-9** (kebijakan, butuh keputusan bisnis dulu).

---

## 7. Status Implementasi (SUDAH DIKERJAKAN)

- ✅ **GAP-1**: route `attendance/settings` di-guard `permission:settings.attendance`;
  TU diberi permission; menu "Pengaturan Absensi" pindah ke group Pengaturan
  (via fitur Pengaturan Menu — lihat `MENU-VISIBILITY-PLAN.md`).
- ✅ **Rencana B — Email per-tenant**: kolom SMTP di `notification_settings`
  (`smtp_password` **encrypted**), `EmailService`, wiring di `NotificationDispatcher`,
  validasi + `testEmail` di controller, route `test-email`, card Email di UI.
- ✅ **GAP-2**: scan pulang di luar `check_out_start`–`check_out_end` tetap
  diterima tapi ditandai `perlu_verifikasi` (kolom baru di student & employee
  attendances; disurface di scan response + daily siswa).
- ⏭️ **GAP-3** (scan masuk): keputusan = tetap terima sebagai telat (tanpa perubahan).
- ⏭️ **GAP-7** (lupa pulang): keputusan = biarkan null (tanpa job auto-checkout).

## 6. Keputusan (SUDAH DIPUTUSKAN)

- [x] **Penempatan menu:** group "Pengaturan", rule admin/TU/super admin
      (permission `settings.attendance`). ✅ FINAL — lihat §2.
- [x] Email: **SMTP per-tenant** (password encrypted). ✅
- [x] Scan pulang di luar window: **terima + tandai `perlu_verifikasi`**. ✅
- [x] Scan masuk lewat jam: **tetap terima sebagai telat**. ✅
- [x] Lupa scan pulang: **biarkan null**. ✅
- [ ] Scan pulang di luar window: **ditolak** atau **diterima + ditandai**? (GAP-2)
- [ ] Scan masuk lewat jam tertentu: **ditolak/Alfa** atau **tetap diterima**? (GAP-3)
- [ ] Lupa scan pulang: **auto-checkout** atau **biarkan null**? (GAP-7)
- [ ] Perlu setting per-jenjang sekarang, atau nanti? (GAP-9)
