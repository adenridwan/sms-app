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
