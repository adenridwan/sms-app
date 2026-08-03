# Tutorial Aplikasi

Ringkasan cara pakai fitur-fitur yang dibangun/diperbaiki di sesi-sesi terakhir (per 2026-08-02), plus catatan kelayakan untuk pertanyaan "bisakah tenant dipisah ke database masing-masing nanti". Untuk aturan keselamatan database & catatan insiden, lihat [`CLAUDE.md`](../CLAUDE.md) — dokumen ini fokus ke **cara pakai**, bukan aturan wajib.

## Daftar isi

1. [Login](#1-login)
2. [Menu Akademik: Kurikulum, Mata Pelajaran, Jadwal](#2-menu-akademik-kurikulum-mata-pelajaran-jadwal)
3. [Backup Database](#3-backup-database)
4. [Koneksi Database Aplikasi](#4-koneksi-database-aplikasi)
5. [Kalau Lupa Password Akses](#5-kalau-lupa-password-akses)
6. [Pindah ke Database Baru yang Kosong](#6-pindah-ke-database-baru-yang-kosong)
7. [Multi-Tenant: Bisakah Dipisah ke Database Masing-Masing?](#7-multi-tenant-bisakah-dipisah-ke-database-masing-masing)

---

## 1. Login

- URL: `/login`
- Akun default (dari `UserSeeder`): `superadmin@sms.local` / `password` — segera ganti password setelah login pertama kali.
- Kalau tidak bisa login padahal akunnya seharusnya ada, cek dulu: apakah database yang dituju `.env` (`DB_DATABASE`) memang berisi data (lihat § 6 kalau baru pindah database), dan apakah akun berstatus `active`.

## 2. Menu Akademik: Kurikulum, Mata Pelajaran, Jadwal

Tiga sub-menu di grup **Akademik** yang sebelumnya cuma skema database tanpa halaman (atau halamannya rusak), sekarang lengkap:

| Sub-menu | URL | Fungsi |
|---|---|---|
| Kurikulum | `/academic/curricula` | Kelola daftar kurikulum (Kurikulum Merdeka, K13, dst). CRUD sederhana. |
| Mata Pelajaran | `/academic/subjects` | Kelola mata pelajaran, tiap mapel terhubung ke satu Kurikulum (opsional) + kategori (Wajib/Peminatan IPA/Peminatan IPS/Muatan Lokal). |
| Jadwal | `/academic/schedules` | Jadwal pelajaran mingguan per Kelas + Semester, ditampilkan sebagai grid Hari × Jam. Klik sel kosong untuk tambah, klik sel terisi untuk edit/hapus. Tombol "Kelola Jam Pelajaran" untuk atur jam ke berapa saja yang tersedia (termasuk jam istirahat). |

**Alur pemakaian yang disarankan** (karena saling bergantung): buat Kurikulum dulu → buat Mata Pelajaran (pilih kurikulumnya) → baru susun Jadwal (pilih Tahun Ajaran, Semester, Kelas, lalu isi grid).

Detail teknis & keputusan desain ada di [`docs/academic/`](academic/).

## 3. Backup Database

Menu: **Pengaturan > Backup Database** (`/settings/backups`) — khusus role **Super Admin**.

- Tombol **"Backup Sekarang"** — jalankan `pg_dump` terhadap database yang sedang aktif, hasilnya muncul di tabel daftar backup (bisa di-download atau dihapus).
- **Backup otomatis harian** jam 01:00 lewat Windows Task Scheduler (task `SMS-App-DailyBackup`) — tidak perlu diapa-apakan, cek statusnya dengan:
  ```powershell
  Get-ScheduledTask -TaskName SMS-App-DailyBackup
  ```
- Backup lebih tua dari 14 hari (`BACKUP_RETENTION_DAYS` di `.env`) otomatis terhapus tiap kali backup jalan.

## 4. Koneksi Database Aplikasi

Section di bagian bawah halaman Backup Database — untuk **melihat & mengubah** ke database mana aplikasi ini terhubung (`.env` → `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`), tanpa perlu buka file `.env` manual.

**Cara pakai:**
1. Kalau baru pertama kali dipakai, klik **"Buat Password Akses"** — password ini **terpisah dari password login Anda**, khusus untuk membuka bagian ini.
2. Masukkan password akses untuk **membuka kunci** dan melihat host/port/nama database/username saat ini (password database sendiri tidak pernah ditampilkan).
3. Klik **"Ubah Koneksi"** untuk edit field yang mau diubah. Klik **"Cek Koneksi"** dulu untuk memastikan kredensial baru bisa nyambung sebelum **"Simpan"**.
4. Simpan **selalu** menguji koneksi baru terlebih dulu — kalau gagal connect, `.env` **tidak** ikut berubah.

⚠️ **Penting**: kalau nama database yang dituju benar-benar berbeda (bukan cuma ganti host/password ke database yang sama), sesi login Anda saat ini akan otomatis berakhir setelah Simpan (karena data sesi ada di database lama) — ini **perilaku normal**, bukan bug, siapkan diri untuk login ulang. Lihat langkah lengkapnya di § 6.

## 5. Kalau Lupa Password Akses

Password akses di § 4 disimpan sebagai **hash** (satu arah, tidak bisa "dibongkar" balik ke teks asli oleh siapa pun). Kalau lupa, satu-satunya jalan adalah reset lewat terminal server:

```bash
php artisan db-connection:reset-access-password
# atau tanpa prompt konfirmasi:
php artisan db-connection:reset-access-password --force
```

Setelah itu, section "Koneksi Database Aplikasi" kembali ke kondisi awal ("belum dikonfigurasi") dan Anda bisa buat password akses baru dari UI tanpa perlu tahu password lama.

## 6. Pindah ke Database Baru yang Kosong

Kalau lewat § 4 Anda mengarahkan aplikasi ke database yang **benar-benar baru/kosong**:

1. Simpan koneksi baru → sesi login berakhir (normal, lihat peringatan di § 4).
2. Database baru belum punya tabel/user sama sekali, jadi **tidak bisa login dulu** — jalankan ini dulu di terminal server (`.env` sudah menunjuk ke database baru):
   ```bash
   php artisan migrate --force
   php artisan db:seed --force   # isi penuh: permission, role, tenant, user, akademik, master data, demo data
   ```
   (Kalau cuma butuh bisa login tanpa data demo, lihat opsi seeder satu-per-satu di `CLAUDE.md` § "Pindah ke database baru yang kosong".)
3. Login lagi dengan `superadmin@sms.local` / `password`, lalu ganti passwordnya.
4. Password akses § 4 tidak ikut pindah — buat baru lagi di database ini.

## 7. Multi-Tenant: Bisakah Dipisah ke Database Masing-Masing?

**Pertanyaan**: sekarang ada 2 tenant (sekolah) berbagi satu database (`sms_enterprise`) — kalau nanti mau dipisah supaya masing-masing tenant punya database sendiri, apakah bisa?

**Jawaban singkat: bisa, tapi bukan sekadar ganti konfigurasi — perlu kerja pengembangan yang nyata.** Berikut kondisi arsitektur saat ini dan apa saja yang perlu dilakukan.

### Kondisi arsitektur sekarang

Aplikasi ini memakai pola **"satu database, kolom `tenant_id`"** (shared database, row-level multi-tenancy) — **bukan** "satu database per tenant":

- Trait `App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant` dipakai oleh **±30 model** (Academic, Student, Teacher, Attendance, Notification, Setting, dll). Trait ini otomatis mengisi `tenant_id` saat data dibuat, dan otomatis memfilter query berdasarkan `tenant_id` tenant yang sedang login (global scope) — semua model itu tetap hidup dalam **satu koneksi database yang sama**.
- Tabel `roles` (Spatie permission, mode "teams") memang sudah tenant-scoped (`tenant_id`), tapi tabel `permissions` **global** (dipakai bersama semua tenant).
- **Tidak ada mekanisme switch koneksi database per tenant di manapun di kode saat ini** — tidak ada `DB::purge()`/`DB::reconnect()`/`Config::set('database...')` per-request.
- Tabel `tenants` **tidak punya kolom** seperti `database`/`db_connection` — secara skema pun belum disiapkan untuk routing ke database berbeda.
- Menariknya, package **`stancl/tenancy` (v3.10) sudah ter-install** di `composer.json` — package ini justru didesain persis untuk skenario ini (mendukung mode "multi-database": tiap tenant dapat database sendiri, auto-provision + auto-migrate saat tenant baru dibuat, otomatis switch koneksi berdasarkan domain/header). **Tapi belum pernah dikonfigurasi** — tidak ada `config/tenancy.php`, tidak ada middleware/service provider dari package ini yang aktif. Ada `App\Providers\TenancyServiceProvider` buatan sendiri, tapi isinya cuma stub kosong (belum diimplementasikan).
- `TENANCY_ENABLED` di `.env` saat ini **tidak dibaca kode manapun** — flag basi, tidak menggerbangi apa-apa.

### Yang perlu dikerjakan kalau memang mau dipisah

1. **Konfigurasi `stancl/tenancy` yang sudah ter-install** untuk mode multi-database (jalan lebih pasti daripada bikin mekanisme switching sendiri dari nol) — publish config, aktifkan middleware identifikasi tenant (lewat domain/subdomain atau header), definisikan event auto-provisioning database baru per tenant.
2. **Tambah kolom** di tabel `tenants` untuk menyimpan info koneksi/nama database per tenant.
3. **Migrasi data satu kali**: untuk tiap tenant yang sudah ada, buat database Postgres baru, jalankan migration di sana, lalu pindahkan HANYA baris-baris milik tenant itu dari ±30 tabel yang tenant-scoped (perlu script export/import sendiri — `pg_dump` biasa men-dump seluruh tabel, tidak bisa filter `WHERE tenant_id=...` langsung).
4. **Restrukturisasi alur login/session**: saat ini resolusi tenant terjadi *setelah* user berhasil query (tenant_id dari user yang login, lihat `EnsureTenantMiddleware`/`TenantService`). Dengan database terpisah, koneksi database yang tepat harus sudah dipilih **sebelum** query user pun bisa jalan (karena tabel `users` sendiri ada di database per-tenant) — butuh identifikasi tenant dari domain/subdomain di awal request, bukan dari data user yang baru bisa diketahui setelah query.
5. Setelah database benar-benar terpisah per tenant, global scope `tenant_id` (trait `BelongsToTenant`) jadi tidak wajib lagi (tiap DB cuma berisi satu tenant) — tapi aman dibiarkan tetap ada, tidak mengganggu.
6. **Uji ulang** seluruh alur yang menyentuh salah satu dari ±30 model tenant-scoped, plus login/permission (mode "teams" Spatie yang sudah pakai `tenant_id` sebagai team key perlu dicek ulang perilakunya).

**Kesimpulan praktis**: dengan baru 2 tenant, migrasi datanya sendiri kecil dan realistis dikerjakan. Bagian yang makan waktu adalah **poin 1 dan 4** (mengaktifkan `stancl/tenancy` dengan benar + restrukturisasi identifikasi tenant di awal request) — ini pekerjaan arsitektur, bukan config toggle. Kalau proyek ini diperkirakan akan tumbuh ke banyak tenant/sekolah dengan kebutuhan isolasi data yang ketat, lebih baik dikerjakan lebih awal (makin banyak data & fitur baru yang bergantung pola shared-DB, makin mahal migrasinya nanti).



php artisan migrate --force

php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan permission:cache-reset
php artisan db:seed --class=TenantSeeder --force
php artisan db:seed --class=UserSeeder --force

login mobile:
guru1@demo.sms.local