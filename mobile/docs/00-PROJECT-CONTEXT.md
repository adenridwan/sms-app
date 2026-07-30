# 00 — Project Context

> Analisis konteks proyek untuk pengembangan **aplikasi mobile** yang mengonsumsi
> REST API dari aplikasi web Laravel (`backend/`) yang sudah ada.
> Dokumen ini **tidak mengubah** kode aplikasi — murni analisis.

Tanggal analisis: 2026-07-29 · Penulis: Software Architect (analisis read-only)

---

## 1. Ringkasan Produk

**SMS Enterprise** — School Management System multi-sekolah (multi-tenant) untuk
sekolah Indonesia (TK/SD/SMP/SMA/SMK/Universitas). Mencakup modul: Akademik,
Data Siswa, Guru, Staff, **Absensi (QR/RFID)**, Ujian & Nilai, Keuangan,
Perpustakaan, Rapor/Laporan, Notifikasi/Pengumuman, dan administrasi
multi-tenant.

Aplikasi web saat ini adalah **SPA (React + Inertia)** yang dilayani Laravel,
sekaligus mengekspos **REST API v1** (`/api/v1/*`) yang sama untuk konsumsi
klien lain — inilah yang akan dipakai aplikasi mobile.

---

## 2. Stack Teknologi (backend)

| Area | Teknologi | Versi |
|---|---|---|
| Framework | Laravel | `^12.0` |
| Bahasa | PHP | `^8.4` |
| Auth API | Laravel Sanctum (token + SPA stateful) | `^4.0` |
| RBAC | spatie/laravel-permission | `^6.10` |
| Database | PostgreSQL | 16 |
| Cache / Queue | Redis + Laravel Horizon | `^5.0` |
| Web SPA | Inertia + React (TypeScript, Vite) | Inertia `^2.0` |
| QR Code | simplesoftwareio/simple-qrcode, chillerlan/php-qrcode | `^4.2` / `^6.0` |
| PDF / Excel | barryvdh/laravel-dompdf, maatwebsite/excel | `^3.1` |
| Media | spatie/laravel-medialibrary | `^11.11` |
| Audit | spatie/laravel-activitylog | `^4.9` |
| Multi-tenant | stancl/tenancy (terpasang) + `TenantService` custom | `^3.9` |
| Storage | Local `public` disk + flysystem S3 (MinIO tersedia) | — |
| WhatsApp | Provider Fonnte / Wablas (per-tenant) | — |

> Catatan multi-tenancy: walau `stancl/tenancy` terpasang, resolusi tenant yang
> aktual memakai **kolom `tenant_id` + header `X-Tenant-ID`** (single database,
> row-level tenant scoping), bukan database-per-tenant. Lihat `01-EXISTING-SYSTEM.md §Multi-Tenancy`.

---

## 3. Struktur Proyek (monorepo)

```
sms-app/
├── backend/            # Aplikasi Laravel (web SPA + REST API)  ← sumber kebenaran
│   ├── app/
│   │   ├── Application/     # DTOs, UseCases, Contracts, Traits (DDD)
│   │   ├── Domain/          # Services, Enums, Events, Listeners, Repositories, Rules per-modul
│   │   ├── Infrastructure/  # Persistence/Eloquent (Model), External/Messaging (Fonnte/Wablas)
│   │   ├── Http/            # Controllers/Api/V1, Requests, Resources, Middleware
│   │   └── Models/          # Sebagian Model (User, Tenant)
│   ├── routes/
│   │   ├── api_v1.php       # ★ Seluruh REST API v1 (kontrak mobile)
│   │   ├── web.php          # Halaman Inertia
│   │   └── api.php          # (tipis)
│   ├── database/migrations/ # Skema tabel
│   └── resources/js/        # Web SPA React
└── mobile/             # ★ Workspace aplikasi mobile (BARU)
    └── docs/           # Dokumen analisis ini
```

Aplikasi mobile dibuat **terpisah** di folder `mobile/` agar tidak mencampur
build tooling dengan backend. Backend tetap menjadi satu-satunya sumber data/API.

---

## 4. Persona Pengguna (target mobile)

Sistem punya **11 role** dan **6 `user_type`** (`super_admin`, `admin`, `staff`,
`teacher`, `student`, `parent`). Untuk mobile, persona paling relevan:

| Persona | Role terkait | Kebutuhan mobile utama |
|---|---|---|
| **Siswa** | `siswa` | Lihat jadwal, absensi sendiri, nilai, tagihan, pengumuman, kartu QR |
| **Orang Tua** | `orang_tua` | Pantau kehadiran & nilai & tagihan anak, notifikasi, izin |
| **Guru / Wali Kelas** | `guru`, `wali_kelas` | Ambil absensi, input nilai, jadwal, lihat kelas |
| **Operator/Petugas Scan** | `admin`/`staff` | Scan QR/RFID masuk-pulang (sudah didukung `/scan`) |
| **Admin sekolah** | `admin` | (Opsional) manajemen ringan; sebagian besar tetap di web |

> Rekomendasi persona final ada di `API-GAP-ANALYSIS.md §Recommendations`.

---

## 5. Titik Integrasi Mobile ↔ API

- **Base URL:** `https://<host>/api/v1` (dev: `http://localhost:8080/api/v1`).
- **Auth:** `POST /auth/login` → **Bearer token** (Sanctum personal access token,
  masa berlaku default **7 hari**). Kirim `Authorization: Bearer <token>` di
  setiap request terproteksi.
- **Tenant:** user biasa sudah terikat `tenant_id` (tidak perlu header). Hanya
  super admin yang perlu `X-Tenant-ID`.
- **Format respons seragam:** `{ "success": bool, "message": string, "data": ... }`.
- **Fitur khas untuk mobile:** absensi berbasis **QR/RFID** + **sinkronisasi
  offline** (`/scan/sync-offline`) + **GPS** (lat/long) — sangat cocok untuk app.

---

## 6. Batasan & Prinsip

1. **Tidak mengubah kode backend** pada fase analisis ini.
2. Mobile hanya berkomunikasi lewat REST API v1 (bukan akses DB langsung).
3. Endpoint back-office (manajemen user/role/tenant, audit, export berat)
   **tidak** diekspos ke aplikasi end-user — lihat kategori di
   `API-GAP-ANALYSIS.md`.
4. Perubahan backend yang dibutuhkan mobile diusulkan sebagai **rekomendasi**
   dulu, bukan langsung diimplementasi.

---

## 7. Peta Dokumen

| File | Isi |
|---|---|
| `00-PROJECT-CONTEXT.md` | (dokumen ini) konteks & stack |
| `01-EXISTING-SYSTEM.md` | Analisis mendalam sistem: arsitektur, auth, RBAC, tabel, QR, WhatsApp |
| `03-API-CONTRACT.md` | Kontrak API: auth flow, header, envelope, error, katalog endpoint |
| `API-GAP-ANALYSIS.md` | Klasifikasi endpoint utk mobile + rekomendasi sebelum implementasi |

> Catatan: penomoran mengikuti permintaan (00, 01, 03). Slot `02` sengaja
> dilewati sesuai spesifikasi.
