# Menjalankan Aplikasi Mobile

Rujukan perintah yang **sudah diuji** di repo ini. Untuk detail khusus kabel
USB, lihat juga [RUN-VIA-USB.md](RUN-VIA-USB.md).

---

## 0. Backend harus jalan lebih dulu

Aplikasi tidak berguna tanpa API. Perhatikan **database mana** yang ditunjuk —
`.env` menentukan itu, dan pernah tertukar antara `db_production` dan
`sms_testing`.

```bash
cd backend

# Database dari .env (cek dulu: grep DB_DATABASE .env)
php artisan serve --port=8000

# Database testing (sms_testing, lewat .env.testing) — untuk uji coba
php artisan serve --env=testing --port=8001

# Tambahkan --host=0.0.0.0 HANYA bila perlu diakses HP lewat WiFi
php artisan serve --host=0.0.0.0 --port=8000
```

## Pasang ke HP dalam satu perintah

```bash
cd backend && php artisan serve --env=demo --port=8001 --host=0.0.0.0 &
cd mobile  && ./scripts/install-apk.sh
```

Skrip itu mendeteksi IP LAN komputer, membangun APK release dengan
`--dart-define=API_BASE_URL=http://<ip>:8001/api/v1`, memasangnya, lalu
membuka aplikasinya. Variasinya:

```bash
./scripts/install-apk.sh 192.168.1.14   # tentukan IP sendiri
SKIP_BUILD=1 ./scripts/install-apk.sh   # pasang ulang APK yang sudah ada
PORT=8080 ./scripts/install-apk.sh      # backend di port lain
```

Tiga hal yang membuatnya ada:

- **Alamat backend di-*bake* ke APK.** Pindah WiFi → IP berubah → APK **harus**
  dibangun ulang. Karena itu IP-nya dicetak sebelum build, dan `…/ping` diketuk
  lebih dulu supaya ketahuan kalau server mati — lebih baik daripada memasang
  APK yang lalu gagal login tanpa sebab yang jelas.
- **Emulator dilewati.** `adb install` gagal dengan *more than one
  device/emulator* kalau emulator ikut menyala, jadi skrip memilih HP fisik.
- **`unauthorized` dijelaskan.** Kalau dialog "Allow USB debugging?" belum
  disetujui, skrip menyebutkan langkahnya alih-alih gagal diam-diam.

---

### Pakai `sms_demo` untuk uji di perangkat

`sms_testing` adalah basis milik phpunit. Siapa pun yang menjalankan
`php artisan test` — dari terminal, dari IDE, dari sesi lain — mengosongkannya
lewat `RefreshDatabase`, dan aplikasi di HP mendadak menjawab
"Email atau password salah". Jejaknya terlihat di log PostgreSQL sebagai
`FATAL: database "database_yang_tidak_ada_ini" does not exist`
(dari `tests/Feature/DatabaseConnectionTest.php`).

Untuk demo/uji perangkat, pakai basis terpisah yang tak pernah disentuh test:

```bash
cd backend

# sekali saja
psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE sms_demo"
sed -e 's/^APP_ENV=testing/APP_ENV=demo/'     -e 's/^DB_DATABASE=sms_testing/DB_DATABASE=sms_demo/'     .env.testing > .env.demo

php artisan migrate:fresh --seed --env=demo --force
php artisan serve --env=demo --port=8001 --host=0.0.0.0
```

`DemoAttendanceFixtureSeeder` (sudah masuk `DatabaseSeeder`) mengisi apa yang
tak diisi seeder demo lain: `unique_code`/`rfid_code` siswa & guru, dan wali
kelas. Tanpa itu **pindai QR, Ref ID, "Absensi Saya", dan Checklist per kelas
semuanya tak bisa dipakai** — tak ada kode untuk dipindai dan tak ada guru yang
mengampu kelas. Idempoten, jadi bisa dijalankan sendiri di basis yang sudah ada:

```bash
php artisan db:seed --class=DemoAttendanceFixtureSeeder --env=demo --force
```

Akun guru yang **tertaut ke data kepegawaian** (punya QR & kelas) memakai pola
`<nama><n>@teacher.sms.local`, mis. `drbambangsud10@teacher.sms.local`.
`guru1@demo.sms.local` hanyalah akun login — tidak punya baris `teachers`, jadi
"Absensi Saya" dan Checklist tidak akan berisi apa pun untuknya.

### Bila `sms_testing` kosong

`php artisan test` memakai basis ini dengan `RefreshDatabase`, jadi **setiap kali
suite dijalankan isinya terhapus**. Gejalanya: login menjawab 401 padahal
kredensial benar, atau Beranda kosong melompong. Isi ulang:

```bash
cd backend
php artisan migrate:fresh --seed --env=testing --force

# Verifikasi sebelum lanjut — jangan percaya "DONE" dari seeder saja
php artisan tinker --env=testing   --execute="echo \DB::table('users')->count();"
```

Dua hal yang pernah menggigit:

- **Jangan jalankan dua `migrate:fresh --seed` bersamaan.** Yang satu menghapus
  saat yang lain sedang mengisi, dan seeder tetap melapor sukses.
- **`DemoFinanceSeeder` tidak idempoten** — hanya aman di atas basis yang baru
  di-`fresh`; di basis terisi ia gagal pada foreign key `payment_items`.

Akun demo (kata sandi semuanya `password`):

| Peran | Email |
|---|---|
| Super admin | `superadmin@sms.local` |
| Admin sekolah | `admin@demo.sms.local` |
| Guru | `guru1@demo.sms.local`, `guru2@demo.sms.local` |

---

## 1. Emulator Android (cara termudah)

Emulator memakai alamat khusus **`10.0.2.2`** untuk menunjuk `localhost`
komputer. Ini yang paling praktis karena tidak tersentuh firewall.

```bash
# Lihat emulator yang ada
flutter emulators

# Nyalakan (Phone_API_33 = ponsel, Tablet_API_33 = tablet)
flutter emulators --launch Phone_API_33

# Tunggu sampai muncul sebagai `device`
adb devices

# Jalankan aplikasi
cd mobile
flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

> **Jangan pakai `Pixel_5_API_24` / `Tablet_API_24`.** Keduanya image x86 32-bit
> Android 7 dan ditolak Flutter versi sekarang ("unsupported"). Pakai yang
> `_API_33`.

Membuat emulator baru bila perlu:

```bash
# Ponsel
avdmanager create avd -n Phone_API_33 \
  -k "system-images;android-33;google_apis;x86_64" -d "pixel_5"

# Tablet
avdmanager create avd -n Tablet_API_33 \
  -k "system-images;android-33;google_apis;x86_64" -d "10.1in WXGA (Tablet)"
```

---

## 2. HP fisik via kabel USB

`adb reverse` membuat `127.0.0.1` di HP menembus ke komputer. **Hanya hidup
selama kabel tersambung** — begitu dicabut, koneksi mati.

```bash
# Aktifkan USB debugging di HP, lalu:
adb devices                          # pastikan statusnya `device`
adb reverse tcp:8000 tcp:8000

cd mobile
flutter run -d <device-id> --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

---

## 3. HP fisik via WiFi (tanpa kabel)

Butuh tiga hal sekaligus, dan yang paling sering terlewat adalah firewall.

```bash
# a. Backend harus mendengarkan semua antarmuka, bukan cuma 127.0.0.1
php artisan serve --host=0.0.0.0 --port=8000

# b. Cari IP LAN komputer
ipconfig                             # cari IPv4 pada adapter Wi-Fi

# c. Izinkan port 8000 di firewall — PowerShell sebagai ADMINISTRATOR.
#    Dibatasi LocalSubnet supaya hanya perangkat di WiFi yang sama.
#    New-NetFirewallRule -DisplayName "SMS Dev Server 8000 (LAN only)" `
#      -Direction Inbound -Protocol TCP -LocalPort 8000 `
#      -RemoteAddress LocalSubnet -Action Allow -Profile Any

cd mobile
flutter run -d <device-id> --dart-define=API_BASE_URL=http://192.168.1.19:8000/api/v1
```

Menguji apakah sudah tembus: buka `http://<ip-komputer>:8000` di browser HP.
Kalau muncul halaman Laravel, jaringannya beres.

> IP LAN diberikan DHCP dan **bisa berubah** setelah komputer reconnect WiFi.
> Kalau tiba-tiba "tidak terhubung ke server", cek `ipconfig` lagi.

---

## 4. Chrome / web

```bash
flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

**Belum pernah diuji berhasil di repo ini**, dan ada dua ganjalan yang perlu
diselesaikan lebih dulu:

1. **CORS.** Browser memanggil API dari origin berbeda, jadi Laravel harus
   mengizinkan origin `localhost:<port-flutter>`. Lewat emulator/HP hal ini
   tidak muncul karena bukan konteks browser.
2. **Scan QR tidak berjalan penuh.** `mobile_scanner` bergantung pada kamera
   native; di web dukungannya terbatas. Untuk menguji tampilan/alur non-scan
   masih berguna, tapi jangan dipakai memverifikasi fitur absensi.

---

## 5. Perintah saat aplikasi sudah jalan

Ditekan di terminal tempat `flutter run` berjalan:

| Tombol | Fungsi |
|---|---|
| `r` | Hot reload — perubahan UI langsung terlihat |
| `R` | Hot restart — bila mengubah state/provider |
| `q` | Keluar (aplikasi ikut berhenti di device) |
| `d` | Detach — `flutter run` berhenti, aplikasi tetap jalan |

---

## 6. Pemeriksaan & build

```bash
cd mobile
flutter analyze                      # wajib nol error sebelum commit
flutter pub get                      # setelah pubspec berubah

# APK debug (bisa dipasang manual tanpa kabel)
flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.1.19:8000/api/v1

# PRODUKSI — pakai domain + HTTPS, jangan IP
flutter build apk --release --dart-define=API_BASE_URL=https://api.sekolah.sch.id/api/v1
```

`API_BASE_URL` **selalu** lewat `--dart-define`, tidak pernah di-hardcode —
lihat catatan lengkapnya di [lib/core/config/app_config.dart](../lib/core/config/app_config.dart).
Build rilis yang lupa menyertakannya akan jatuh ke alamat emulator
(`10.0.2.2`) yang tak berarti apa-apa di perangkat pengguna.

---

## 7. Menguji mode offline

Aplikasi dirancang tetap berguna tanpa server: sesi bertahan, transaksi masuk
antrean, lalu dikirim otomatis saat koneksi pulih. Cara mengujinya:

```bash
# 1. Login dulu selagi backend hidup (sesi & profil tersimpan di perangkat)

# 2. Matikan backend — cukup hentikan `php artisan serve`

# 3. Tutup paksa lalu buka lagi aplikasinya
adb shell am force-stop id.sch.sms.sms_mobile
adb shell monkey -p id.sch.sms.sms_mobile -c android.intent.category.LAUNCHER 1
```

Yang seharusnya terjadi:

- **Tetap masuk** — bukan dilempar ke layar login. Muncul pemberitahuan
  "memakai sesi tersimpan".
- **Input manual & scan tetap bisa disimpan** → masuk antrean. Pada input
  manual, pratinjau identitas memang gagal (butuh server) tapi tombol simpan
  tetap hidup.
- **Absen kelas tetap bisa disimpan** → seisi kelas masuk antrean.
- Beranda menampilkan **"N data menunggu sinkron"**.

Nyalakan backend lagi: antrean terkirim **otomatis** tanpa perlu membuka layar
Antrean. Tombol "Sinkron" di layar Antrean tetap ada untuk memaksa manual.

> Hanya `401` (token dicabut/kedaluwarsa) yang mengakhiri sesi. Jaringan mati
> **tidak** membuat pengguna keluar — itu justru bug yang pernah diperbaiki.

---

## Akun uji

Semua berpassword `password`:

| Email | Peran | Berguna untuk |
|---|---|---|
| `admin@demo.sms.local` | Administrator | Semua aksi, semua kelas |
| `guru1@demo.sms.local` | Guru | Beranda guru + absen kelas (butuh penugasan kelas) |
| `bendahara@demo.sms.local` | Bendahara | Membuktikan penyaringan izin — tab Absensi hilang |
| `superadmin@sms.local` | Super Admin | Akses penuh lintas tenant |

Akun siswa/orang tua **ditolak masuk** — aplikasi ini untuk petugas.

> Seeder tidak membuat penugasan guru–kelas, jadi akun guru akan tampil
> "belum terhubung ke kelas" sampai wali kelas / `teacher_classrooms` diisi.
