# Menjalankan App via USB (Perangkat Fisik)

1. **Aktifkan USB debugging di HP**
   Pengaturan → Tentang Ponsel → tap "Nomor Build" 7x (aktifkan Opsi
   Pengembang) → Opsi Pengembang → aktifkan **USB Debugging**.

2. **Sambungkan HP ke komputer via kabel USB**, lalu terima prompt
   "Allow USB debugging?" di layar HP (centang "Always allow").

3. **Cek perangkat terdeteksi:**
   ```bash
   flutter devices
   # atau
   adb devices
   ```
   Pastikan device muncul dengan status `device` (bukan `unauthorized`).

4. **Tunnel port backend ke HP** (backend jalan di komputer, port 8080):
   ```bash
   adb reverse tcp:8080 tcp:8080
   ```

5. **Jalankan app ke device tsb:**
   ```bash
   cd mobile
   flutter pub get
   flutter run -d <device-id-dari-langkah-3> --dart-define=API_BASE_URL=http://127.0.0.1:8080/api/v1
   ```

6. Login pakai akun seeder, mis. `admin@demo.sms.local` / `password`.

Catatan: kalau `adb reverse` tidak jalan (device tidak ke-detect adb meski
sudah USB), pastikan backend jalan di `0.0.0.0:8080` (bukan hanya
`127.0.0.1`) sebagai alternatif — lalu pakai IP LAN komputer:
`--dart-define=API_BASE_URL=http://<ip-lan-komputer>:8080/api/v1` (HP & komputer
harus satu jaringan WiFi).
