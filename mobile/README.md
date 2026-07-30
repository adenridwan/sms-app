# SMS Absensi — Aplikasi Mobile (Flutter)

Aplikasi mobile petugas (Admin / Guru / Staf) untuk **absensi siswa** via QR Code
& Ref ID. Mengonsumsi REST API v1 dari `../backend`.

> **Fase 1 (selesai): Autentikasi.** Login (Sanctum Bearer), auth-gate, sesi
> tersimpan aman, logout. Modul scan menyusul di **Fase 2**.
> Analisis & spesifikasi lengkap ada di [`docs/`](docs/).

## Menjalankan

Prasyarat: Flutter 3.24+, backend berjalan (lihat `../backend`, dev di `:8080`).

```bash
flutter pub get

# Emulator Android (default base URL http://10.0.2.2:8080/api/v1):
flutter run

# Perangkat fisik / host lain — override base URL ke IP LAN backend:
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8080/api/v1
```

Akun uji (dari seeder backend): mis. `admin@demo.sms.local` / `password`.

> Catatan: `usesCleartextTraffic` diaktifkan untuk dev (HTTP). Untuk produksi,
> gunakan HTTPS dan nonaktifkan cleartext.

## Struktur

```
lib/
├── main.dart                 # entry → ProviderScope + SmsApp
├── app.dart                  # MaterialApp.router + tema
├── core/
│   ├── config/app_config.dart      # base URL (--dart-define), timeout
│   ├── theme/app_theme.dart        # tema teal, light & dark
│   ├── storage/token_storage.dart  # token di secure storage
│   ├── network/
│   │   ├── api_client.dart         # Dio + interceptor Bearer
│   │   └── api_exception.dart      # normalisasi error (envelope & 422)
│   ├── routing/app_router.dart     # go_router + auth gate (redirect)
│   └── providers.dart              # tokenStorage / dio / authRepository
└── features/
    ├── auth/
    │   ├── models/user.dart
    │   ├── data/auth_repository.dart          # /auth/login, /auth/me, /auth/logout
    │   └── presentation/
    │       ├── auth_controller.dart           # AuthState (Riverpod)
    │       ├── splash_screen.dart
    │       └── login_screen.dart
    └── dashboard/
        └── presentation/dashboard_screen.dart # beranda pasca-login (placeholder scan)
```

## Peta ke API (Fase 1)

| Layar | Endpoint |
|---|---|
| Splash / auth-gate | `GET /auth/me` |
| Login | `POST /auth/login` |
| Dashboard / Keluar | `POST /auth/logout` |

Kontrak lengkap: [`docs/03-API-CONTRACT.md`](docs/03-API-CONTRACT.md) ·
Kebutuhan: [`docs/02-MOBILE-REQUIREMENTS.md`](docs/02-MOBILE-REQUIREMENTS.md) ·
Fitur: [`docs/07-FEATURE-LIST.md`](docs/07-FEATURE-LIST.md) ·
Alur UI/UX: [`docs/08-UI-UX-FLOW.md`](docs/08-UI-UX-FLOW.md).

## Berikutnya (Fase 2)

Modul Scan: `mobile_scanner` (QR) + input Ref ID manual, `geolocator` (GPS
geofence), antrean offline (`drift`/`sqflite`) → `POST /scan`, `/scan/lookup`,
`/scan/sync-offline`, `GET /scan/bootstrap`.
