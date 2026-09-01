# Implementasi Heartbeat & Device Monitoring

## Status: DIIMPLEMENTASIKAN ✅

Fitur heartbeat sudah diimplementasikan dan aktif. Dokumen ini menjelaskan cara kerjanya.

---

## 1. Cara Kerja

### Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ Login berhasil (online/offline yang kemudian terverifikasi)    │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ HeartbeatService.start()                                        │
│   • Kirim heartbeat pertama segera                              │
│   • Ulangi setiap 30 detik                                      │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ POST /api/v1/devices/heartbeat                                  │
│   • app_version: "1.0.0"                                        │
│   • os_version: "Android 12" / "iOS 16.0"                       │
│   • device_model: "Samsung A12" / "iPhone"                      │
│   • network_type: "wifi" / "mobile" / "none"                    │
│   • network_name: "SchoolWiFi" (untuk WiFi)                     │
│   • latency_ms: 45 (ping ke server)                             │
│   • pending_sync_count: 3 (scan offline belum terkirim)         │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ Backend: ScannerDevice->recordHeartbeat()                       │
│   • Update last_heartbeat_at = now()                            │
│   • Simpan semua metrics                                        │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ Dashboard Admin: Pengaturan → Monitor Device                    │
│   • Device muncul dengan status "Online" (hijau)                │
│   • Tampil info: baterai, jaringan, latency, pending sync       │
│   • Auto-refresh setiap 30 detik                                │
└─────────────────────────────────────────────────────────────────┘
```

### Status Device

| Status | Kondisi | Indikator |
|--------|---------|-----------|
| **Online** | heartbeat < 2 menit | Badge hijau |
| **Idle** | heartbeat 2-15 menit | Badge kuning |
| **Offline** | heartbeat > 15 menit atau null | Badge merah |

---

## 2. File yang Terlibat

### Mobile (Flutter)

| File | Deskripsi |
|------|-----------|
| `lib/core/services/heartbeat_service.dart` | Service utama - kumpulkan info device & kirim ke server |
| `lib/core/providers.dart` | Provider untuk HeartbeatService |
| `lib/features/auth/presentation/auth_controller.dart` | Start/stop heartbeat saat login/logout |
| `lib/main.dart` | Inisialisasi device info sebelum runApp |

### Backend (Laravel)

| File | Deskripsi |
|------|-----------|
| `app/Http/Controllers/Api/V1/Attendance/DeviceMonitorController.php` | Endpoint heartbeat & CRUD device |
| `app/Infrastructure/Persistence/Eloquent/Attendance/ScannerDevice.php` | Model dengan recordHeartbeat() |
| `resources/js/pages/settings/Devices.tsx` | Dashboard monitoring |

---

## 3. Dependencies

```yaml
# pubspec.yaml
device_info_plus: ^10.1.0    # Model perangkat, versi OS
package_info_plus: ^8.0.0    # Versi aplikasi
connectivity_plus: ^6.0.3    # Tipe koneksi (WiFi/mobile)
network_info_plus: ^5.0.3    # Nama WiFi
```

---

## 4. Permissions

### Android (`android/app/src/main/AndroidManifest.xml`)

```xml
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE"/>
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>  <!-- Untuk nama WiFi di Android 12+ -->
```

### iOS (`ios/Runner/Info.plist`)

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>Lokasi digunakan untuk memverifikasi absensi berada di area sekolah.</string>
<!-- Nama WiFi di iOS butuh location permission -->
```

---

## 5. Catatan Penting

1. **Heartbeat gagal = silent fail**
   - Jika server tidak terjangkau, heartbeat gagal tanpa notifikasi ke user
   - Tidak mempengaruhi fungsi scan absensi

2. **Login offline**
   - Heartbeat tidak dikirim sampai sesi diverifikasi online (tidak ada token)
   - Setelah `revalidateSession()` berhasil, heartbeat mulai jalan

3. **Battery level tidak dikirim**
   - Flutter tidak punya API baterai bawaan
   - battery_plus terlalu berat untuk fitur monitoring
   - Server tetap bisa menampilkan status online tanpa info baterai

4. **Nama WiFi di Android 12+**
   - Butuh location permission (runtime request)
   - Sudah ter-cover oleh permission geofence yang sudah ada

5. **Interval 30 detik**
   - Bisa diubah di `HeartbeatService._intervalSeconds`
   - Trade-off: lebih sering = lebih real-time tapi lebih boros baterai

---

## 6. Testing

1. Login di app mobile
2. Buka dashboard admin → Pengaturan → Monitor Device
3. Device harus muncul dengan status "Online" (hijau)
4. Cek info yang tampil: model device, versi app, tipe koneksi
5. Matikan WiFi → tunggu 2+ menit → status berubah ke "Idle" (kuning)
6. Tunggu 15+ menit → status berubah ke "Offline" (merah)
7. Nyalakan WiFi → dalam 30 detik status kembali ke "Online"
