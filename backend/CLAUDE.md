# Keselamatan Database (WAJIB DIBACA sebelum menjalankan perintah `artisan` apa pun)

Proyek ini punya **dua database Postgres terpisah**:

| Database | Env file | Isi | Boleh di-wipe? |
|---|---|---|---|
| `sms_enterprise` | `.env` (default) | Data dev utama — user asli, data yang dimasukkan lewat UI, dianggap setara data produksi oleh pemilik proyek | **TIDAK, tanpa konfirmasi eksplisit dari user di sesi ini** |
| `sms_testing` | `.env.testing` / `phpunit.xml` | Khusus dipakai `php artisan test` (RefreshDatabase) | Ya, bebas — memang untuk itu |

## Insiden 2026-08-01

`scripts/reset-prod-data.sh` pernah dijalankan tanpa backup dan tanpa konfirmasi terhadap `sms_enterprise`. `migrate:fresh` berhasil mengosongkan seluruh tabel, tapi seeding lanjutannya crash di tengah jalan (cache permission basi), sehingga database tertinggal kosong dan data yang tidak berasal dari seeder (dimasukkan manual lewat UI) hilang permanen — tidak ada backup untuk restore. Jangan ulangi ini.

## Aturan wajib

1. **Sebelum menjalankan perintah apa pun yang bisa mengubah/menghapus data** (`migrate`, `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `db:seed`, `tinker` yang melakukan write, raw `DROP`/`TRUNCATE`/`DELETE`), **sebutkan database mana yang akan terkena dampak** dan **minta konfirmasi eksplisit** kecuali user sudah jelas-jelas meminta perintah itu secara spesifik di pesan yang sama.
2. **Untuk eksplorasi/testing/debugging bebas** (coba migrasi baru, uji seeder, uji query destruktif), **selalu arahkan ke `sms_testing`**, bukan default `.env`. Cara paling aman:
   ```bash
   php artisan migrate:fresh --env=testing --force
   php artisan db:seed --env=testing --force
   # atau override manual:
   DB_DATABASE=sms_testing php artisan <perintah>
   ```
3. **`php artisan test` sudah aman secara default** — `phpunit.xml` men-set `DB_DATABASE=sms_testing`, jadi `RefreshDatabase` di test tidak pernah menyentuh `sms_enterprise`. Tidak perlu override manual untuk menjalankan test.
4. **Jangan pernah menjalankan `scripts/reset-prod-data.sh`** tanpa (a) memastikan user sudah backup (`pg_dump`), dan (b) mengonfirmasi ke user persis database mana yang dituju. Skrip ini sekarang punya prompt konfirmasi manual (`Ketik persis 'HAPUS <nama_db>'`) — jangan pernah menjalankannya lewat `bash scripts/reset-prod-data.sh < /dev/null` atau cara lain yang melewati prompt itu.
5. Kalau ragu database mana yang aktif, cek dulu: `grep DB_DATABASE .env` (default/dev) vs `grep DB_DATABASE .env.testing` (test).
6. Setelah operasi apa pun yang menyentuh cache permission Spatie (migrate:fresh, ganti role/permission secara massal), jalankan `php artisan permission:cache-reset` sebelum seeding user/role berikutnya — cache Spatie tidak ikut ter-reset oleh migrate:fresh karena store defaultnya (`redis`) independen dari Postgres.

## Backup rutin (dibuat 2026-08-02, buntut insiden di atas)

- `php artisan backup:run` — pg_dump database aktif ke `storage/app/private/backups` (path binary & retensi di `config/backup.php`, env `PG_DUMP_PATH`/`BACKUP_RETENTION_DAYS`/`BACKUP_DIRECTORY`).
- Dijadwalkan harian jam 01:00 lewat Windows Task Scheduler (`SMS-App-DailyBackup`, action `scripts/run-backup.bat`) — **bukan** lewat `routes/console.php`'s `Schedule::`, karena itu butuh `schedule:run` looping tiap menit yang belum di-setup di mesin lokal ini. Entry di `routes/console.php` tetap ada untuk deployment Linux nanti (cron + `schedule:run`).
- Menu UI: **Pengaturan > Backup Database** (`/settings/backups`), khusus role `super_admin` (`role:super_admin` middleware, bukan lewat sistem permission biasa — akses ke dump database setara akses penuh ke seluruh data tenant).
- Cek/atur ulang task: `Get-ScheduledTask -TaskName SMS-App-DailyBackup`, `Unregister-ScheduledTask -TaskName SMS-App-DailyBackup`.

## Ubah Koneksi Database dari UI (dibuat 2026-08-02)

Menu Backup Database punya section "Koneksi Database Aplikasi" untuk lihat/ubah `.env` DB_* langsung dari browser — super_admin saja, digerbangi **password akses terpisah dari password login** (hash disimpan di tabel `settings`, group=`security`, key=`db_config_access_password`; lihat `App\Infrastructure\Persistence\Eloquent\System\Setting`).

- Backend: `App\Http\Controllers\Api\V1\System\DatabaseConnectionController` — `reveal`/`test`/`update` semua WAJIB kirim `access_password` yang valid di tiap request (tidak ada sesi "unlock" di server). `update()` SELALU mencoba koneksi PDO ke kredensial baru dulu — kalau gagal, `.env` tidak disentuh sama sekali.
- Penulisan `.env` lewat `App\Support\EnvFileWriter` — otomatis bikin `.env.bak` sebelum overwrite, dan path filenya dari `config('backup.env_file')` (bukan hardcode `base_path('.env')`) supaya test bisa diarahkan ke file scratch (`ENV_FILE_PATH`) dan **tidak pernah menulis ke `.env` asli**.
- Endpoint yang menerima `access_password` (`access-password`, `reveal`, `test`, update) dibatasi `throttle:sensitive` (5x/menit) supaya tidak bisa di-brute-force.
- **Kalau menambah command/test baru yang menyentuh `config('backup.env_file')` atau memanggil `DatabaseConnectionController::update()`**, pastikan override `ENV_FILE_PATH` tetap ada — jangan sampai konfigurasi ini bocor balik ke default `base_path('.env')` saat testing.

### Insiden nyaris: override test hanya ada di `.env.testing` (ditemukan 2026-08-06)

`ENV_FILE_PATH` dan `BACKUP_DIRECTORY` dulu **hanya** di-override lewat `.env.testing`, file yang tidak ikut ter-commit. Di mesin yang tidak punya file itu (termasuk mesin ini), `config('backup.env_file')` jatuh ke default `base_path('.env')` — dan `DatabaseConnectionTest` menimpa lalu **menghapus** file itu di `afterEach`, sementara `BackupTest` menghapus seluruh isi `config('backup.directory')`. Artinya `php artisan test` polos bisa melenyapkan `.env` asli dan folder backup asli.

Sekarang kedua override dipindah ke **`phpunit.xml`** (ikut ter-commit, tidak bisa hilang), dan `config/backup.php` me-resolve path relatif terhadap root proyek karena `phpunit.xml` hanya bisa memuat string literal. **Jangan hapus dua baris `<env>` itu**, dan jangan kembalikan ketergantungan ke `.env.testing` saja. Cara cepat memastikan masih aman:

```bash
php artisan tinker --execute="echo config('backup.env_file');"                       # → .env asli (runtime normal)
docker exec -e ENV_FILE_PATH=./storage/framework/testing/scratch.env sms_php \
  php artisan tinker --execute="echo config('backup.env_file');"                     # → file scratch (testing)
```

### Lupa password akses

Password akses **disimpan sebagai hash bcrypt** (tabel `settings`) — satu arah, tidak ada cara "membongkarnya" balik ke teks asli, termasuk lewat akses database langsung. Satu-satunya jalan kalau lupa: **reset** (hapus setting-nya), lalu buat password baru dari UI.

```bash
php artisan db-connection:reset-access-password          # ada prompt konfirmasi
php artisan db-connection:reset-access-password --force  # tanpa prompt
```

Setelah dijalankan, section "Koneksi Database Aplikasi" di menu Backup Database otomatis kembali ke kondisi "belum dikonfigurasi" (tombol "Buat Password Akses" muncul lagi) — tidak butuh password lama. Command ini menghapus baris `settings` (`group=security`, `key=db_config_access_password`) di database yang SEDANG aktif (`.env` default kalau dijalankan tanpa `--env=testing`) — pastikan itu memang database yang dimaksud user sebelum menjalankan.

### Insiden 2026-08-02: klik Simpan langsung logout

`DatabaseConnectionController::update()` sempat memanggil `Artisan::call('config:clear')` di tengah request yang sesinya masih dipakai. Menjalankan command yang me-reload config (bootstrapper `LoadConfiguration`) di tengah request yang sedang berjalan bisa merusak state container untuk sisa request itu, termasuk penyimpanan sesi yang terjadi di `terminate()` — efeknya super admin langsung ter-logout begitu klik Simpan. Diperparah frontend yang langsung memanggil `reveal()` (request kedua yang butuh auth) tepat setelah `update()`.

**Perbaikan yang sudah diterapkan** (jangan diulangi kesalahannya):
- `update()` **tidak lagi** memanggil `Artisan::call()` apa pun. Kalau perlu bust config cache, hapus langsung `bootstrap/cache/config.php` (`File::delete`) — jangan lewat command Artisan mid-request, di dev pun sebenarnya tidak perlu karena `.env` selalu dibaca fresh tiap request baru (tidak ada config cache aktif).
- Frontend (`Backups.tsx`) tidak lagi auto-`reveal()` setelah Simpan berhasil — langsung "kunci lagi" (`lockConnection()`) dan minta buka manual, supaya tidak mengirim request kedua yang butuh auth tepat setelah operasi sensitif.
- Kalau nama database yang dituju memang benar-benar beda, sesi tetap akan berakhir (session/token ada di database lama) — itu **perilaku yang diharapkan**, sudah diberi peringatan eksplisit di UI, bukan lagi bug.

### Pindah ke database baru yang kosong (via fitur Ubah Koneksi)

Kalau "Koneksi Database Aplikasi" diarahkan ke database yang **benar-benar baru/kosong** (bukan sekadar ganti host/password ke database yang sama), urutan yang perlu dilakukan:

1. Simpan koneksi baru dari UI (atau edit `.env` manual) — begitu berhasil, sesi login saat ini **akan berakhir** (lihat insiden di atas) karena tabel `sessions`/`personal_access_tokens` di database baru masih kosong. Ini normal, bukan berarti prosesnya gagal.
2. Karena database baru belum ada tabel maupun user sama sekali, **tidak bisa login dulu lewat UI** — jalankan lewat CLI di server (`.env` sudah menunjuk ke database baru sejak langkah 1):
   ```bash
   php artisan migrate --force
   # Minimal supaya bisa login lagi (role, tenant, user login & otorisasi saja):
   php artisan db:seed --class=PermissionSeeder --force
   php artisan db:seed --class=RoleSeeder --force
   php artisan permission:cache-reset
   php artisan db:seed --class=TenantSeeder --force
   php artisan db:seed --class=UserSeeder --force
   # Atau isi penuh (akademik + master data + data demo), sekali jalan:
   php artisan db:seed --force
   ```
3. Login lagi dengan `superadmin@sms.local` / `password` (dari `UserSeeder`) — segera ganti password setelah masuk kalau database ini bukan sekadar untuk uji coba.
4. Password akses "Koneksi Database Aplikasi" **tidak ikut pindah** (tersimpan di tabel `settings` database lama) — di database baru otomatis dianggap belum dikonfigurasi, tinggal buat baru dari UI.

## Pendaftaran mandiri `/register` — pending + aktivasi OTP (dibuat 2026-08-03)

Halaman `/register` tetap terbuka untuk publik, tapi akunnya **tidak lagi langsung aktif**:

1. `AuthService::register()` membuat user berstatus **`pending`**, tanpa role, dan `AuthController::register()` **tidak menerbitkan token** — jadi pendaftar tidak bisa masuk. `AuthController::login()` sudah menolak status non-`active`, jadi ini otomatis terkunci.
2. Admin membuka **Pengaturan → Keamanan Login**, cari pendaftar (ditandai badge "Menunggu aktivasi"), klik **Buat Kode** — kode 6 digit, berlaku 15 menit, sekali pakai (`OtpService`). Kode polos hanya muncul sekali di layar admin, disampaikan ke pendaftar lewat kanal luar.
3. Pendaftar memasukkan kode di layar aktivasi (`Register.tsx` berpindah tahap setelah daftar berhasil) → `POST /api/v1/auth/activate` → status jadi `active`, lalu login biasa memakai password yang ia buat sendiri.

Aturan yang jangan diubah tanpa alasan:

- **Hanya `pending` yang boleh mengaktifkan dirinya sendiri.** Akun `inactive`/`suspended` dimatikan oleh keputusan admin — `activate()` menolaknya (403) supaya kode akses tidak jadi jalan pintas menghidupkan akun yang sengaja diblokir.
- **Kode diverifikasi dulu, status akun dicek belakangan.** Kalau dibalik, endpoint ini jadi alat menebak email mana yang terdaftar (alasan yang sama sudah dipakai di `loginWithOtp()`).
- **Jangan set `is_active` saat menulis status.** `is_active` bukan kolom — mutator `User::setIsActiveAttribute()` menimpa kolom `status` jadi `active`/`inactive`, sehingga `'status' => 'pending'` akan hilang diam-diam kalau keduanya dikirim bersamaan.
- Aktivasi tercatat di riwayat login dengan `method = 'activation'` (sukses maupun gagal), terbaca di tabel menu Keamanan Login.

Test: `RegisterActivationTest` (11 kasus, termasuk kode salah/kedaluwarsa/sekali-pakai dan akun suspended).

Bug lama yang ikut diperbaiki: `LoginLogService::paginate()` memakai `with('user:id,full_name,email')`, padahal `full_name` accessor (dirakit dari `user_profiles`) — bukan kolom. Akibatnya tabel riwayat login 500 begitu ada log dengan `user_id` terisi. Sekarang eager-load-nya mengambil `id,username,email` + relasi `profile` utuh (semua isi `User::$appends` membacanya saat serialisasi).

## Otorisasi menulis: tiga lapis, jangan cuma satu (2026-08-09)

Guru sempat bisa membuat/mengubah/menghapus **kelas** dan **jurusan** lewat API meski daftar izinnya benar — `ClassroomController` & `MajorController` punya `store/update/destroy/import` **tanpa penjagaan apa pun** (hanya `syncTeachers()` yang dijaga), padahal izin `classrooms.manage` & `majors.manage` sudah lama ada dan hanya dipegang admin.

Setiap aksi tulis butuh **tiga** lapis, dan dua yang pertama wajib:

1. **Endpoint API** — `abort_unless($request->user()->can('...'), 403)` atau `authorize()`/FormRequest. Ini satu-satunya penjagaan yang sungguhan.
2. **Rute web halaman form** — `->middleware('permission:...')`. Tanpa ini pengguna bisa membuka form dan baru ditolak saat menekan Simpan, yang terbaca seolah ia berwenang.
3. **Tombol di UI** — `usePermissions()` (`resources/js/hooks/usePermissions.ts`). Hanya kerapian; jangan pernah dijadikan satu-satunya penjagaan.

Cek cepat controller mana yang masih bolong:

```bash
for f in app/Http/Controllers/Api/V1/**/*.php; do
  for m in store update destroy import; do
    grep -q "public function $m(" "$f" || continue
    awk "/public function $m\(/,/^    }/" "$f" | grep -qE "abort_unless|authorize\(|->can\(" \
      || echo "TANPA IZIN: $(basename $f) $m"
  done
done
```

Test: `AcademicWriteAuthorizationTest` (9 kasus, termasuk rute web `/students/create`).

## Email otomatis akun siswa & guru (dibuat 2026-08-09)

Kolom email tidak lagi wajib diisi saat menambah/mengimpor siswa & guru — rancangan lengkap dan alasan tiap keputusan ada di [docs/EMAIL-OTOMATIS-AKUN.md](docs/EMAIL-OTOMATIS-AKUN.md). Tiga aturan yang jangan dilanggar:

1. **`users.email` = identitas login, `users.contact_email` = alamat surat.** Jangan pernah mengirim surat ke `users.email` — sejak fitur ini ia bisa berisi alamat sintetis (`ahmad.2024001@domain-sekolah`). Semua pengiriman email ke akun memakai `contact_email` (lihat `NotificationDispatcher`).
2. **`contact_email` SENGAJA tidak unik** — satu orang tua memakai satu alamat untuk beberapa anak. Keamanannya dijaga aturan **"jangan pernah mencari user hanya dengan `contact_email`"**, bukan oleh index. Untuk alur OTP nanti, penunjuk akun harus berpasangan (NIS/email login **+** contact_email) dengan respons identik antara cocok & tidak cocok.
3. **Import tidak pernah menetapkan/menimpa email login siswa.** Kolom `email` sudah dihapus dari template siswa dan diabaikan kalau muncul di file (kolom itu ada di file hasil *export* sebagai informasi). Menimpanya dari file = mengunci siswa dari akunnya.

Domain per sekolah disimpan di tabel `settings` (`group=account`, `key=email_domain`), diatur lewat **Pengaturan → Umum**. Tanpa domain, penambahan akun tanpa email ditolak 422 dengan pesan yang mengarahkan ke sana (bukan 500).

Test: `GeneratedEmailTest` (16 kasus) & `GeneratedEmailImportTest` (8 kasus).

## Ringkasan implementasi terkini (update 2026-08-02)

Panduan cara pakai (bukan aturan wajib) ada di [docs/TUTORIAL-APLIKASI.md](docs/TUTORIAL-APLIKASI.md) — termasuk analisis kelayakan "pisah database per tenant".


- **Menu Akademik** — Kurikulum, Mata Pelajaran, Jadwal: model/controller/route/halaman React lengkap (sebelumnya cuma skema tanpa implementasi atau malah controller rusak). Detail & keputusan desain: [docs/academic/00-ANALISA-KURIKULUM-MAPEL-JADWAL.md](docs/academic/00-ANALISA-KURIKULUM-MAPEL-JADWAL.md), [01-UIUX-KURIKULUM.md](docs/academic/01-UIUX-KURIKULUM.md), [02-ANALISA-UIUX-JADWAL.md](docs/academic/02-ANALISA-UIUX-JADWAL.md).
- **Backup rutin** — `backup:run` + Windows Task Scheduler harian + menu UI, lihat § Backup rutin di atas.
- **Ubah Koneksi Database dari UI** — menyatu di menu Backup Database, password akses terpisah, lihat § di atas (termasuk cara reset & cara pindah ke DB baru).
- Semua fitur di atas: super_admin saja, sudah ada test Feature (`AcademicCurriculumSubjectTest`, `AcademicScheduleTest`, `BackupTest`, `DatabaseConnectionTest`) dan lulus penuh bersama suite yang sudah ada.
