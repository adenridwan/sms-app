# 03 — API Contract (v1)

> Kontrak REST API v1 yang dikonsumsi aplikasi mobile. Diturunkan dari
> `backend/routes/api_v1.php` dan `App\Http\Controllers\Api\ApiController`.
> Semua path di bawah relatif terhadap **base URL** `/api/v1`.

Base URL dev: `http://localhost:8080/api/v1` · Prefix nama route: `api.v1.*`

---

## 1. Autentikasi & Header

### Alur token (mobile)
1. `POST /auth/login` dengan `{ email, password, remember? }` — atau
   `POST /auth/login-otp` dengan `{ email, code }` bila user memakai kode akses
   sekali-pakai dari administrator (lupa password / perangkat baru). Keduanya
   mengembalikan envelope yang sama.
2. Simpan `data.token` (Sanctum personal access token, **Bearer**, berlaku ~**7 hari**).
   Simpan juga profil user ke cache lokal — dipakai memulihkan sesi saat backend
   tak terjangkau (lihat [06-AUTH-FLOW.md §3](../../docs/06-AUTH-FLOW.md)).
3. Sertakan header pada semua request terproteksi:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json        # atau multipart/form-data untuk upload
X-Tenant-ID: <uuid>                    # HANYA untuk super_admin (opsional)
```

- User biasa: `tenant_id` melekat pada akun → **tidak perlu** `X-Tenant-ID`.
- `401` pada request terproteksi = token invalid/kadaluarsa → arahkan ke login.
- Logout: `POST /auth/logout` (revoke token aktif).

### Rate limit (kirim header hormati `Retry-After` saat `429`)
`auth` 5/mnt · `api` 60/mnt · `uploads` 10/mnt · `exports` 3/mnt · `public` 20/mnt.

---

## 2. Envelope Respons

```jsonc
// Objek tunggal
{ "success": true, "message": "Success", "data": { ... } }

// Koleksi paginated
{ "success": true, "message": "Success",
  "data": [ ... ],
  "meta":  { "current_page":1, "from":1, "last_page":3, "per_page":15, "to":15, "total":42 },
  "links": { "first":"...", "last":"...", "prev":null, "next":"..." } }

// Error (envelope aplikasi)
{ "success": false, "message": "Pesan", "errors": { "field": ["..."] }? }

// Error validasi FormRequest (Laravel default, TANPA "success")  ⚠️
{ "message": "The email field is required.", "errors": { "email": ["..."] } }
```

> Klien wajib toleran terhadap **dua** bentuk error (lihat `01-EXISTING-SYSTEM.md §11`).

**Kode status:** 200, 201, 401, 403, 404, 422, 429.

### Query umum (list/index)
`?search=`, `?status=`, `?per_page=` (default 15), `?sort=`, `?direction=asc|desc`,
`?page=` — bergantung controller (pola dominan pada `StudentController::index`).

---

## 3. Auth Flow — contoh

**POST `/auth/login`**
```jsonc
// req
{ "email": "guru1@demo.sms.local", "password": "password", "remember": false }
// res 200
{ "success": true, "message": "Login berhasil.",
  "data": { "user": { /* UserResource */ }, "token": "12|abc...", "token_type": "Bearer" } }
// res 401 → { "success": false, "message": "Email atau password salah." }
// res 403 → { "success": false, "message": "Akun Anda tidak aktif. ..." }
```

**GET `/auth/me`** → `{ data: { user, permissions: string[], roles: string[] } }`
(dasar untuk menentukan menu/izin di mobile).

**Bentuk `UserResource`** (ringkas): `id, username, email, first_name, last_name,
full_name, phone, avatar, avatar_url, is_active, status, user_type,
email_verified_at, last_login_at, roles[], teacher?, staff?, guardian_students?,
tenant_id, created_at, updated_at`. Pada `/auth/me` juga menyertakan `permissions[]`.

---

## 4. Katalog Endpoint

Legenda: 🔓 publik · 🔒 `auth:sanctum` · 👑 role/permission tambahan.
(Kategori kelayakan-mobile ada di `API-GAP-ANALYSIS.md`.)

### 4.1 Auth 🔓/🔒
| Method | Path | Ket |
|---|---|---|
| POST | `/auth/login` 🔓 | terbit token |
| POST | `/auth/login-otp` 🔓 | `{email, code}` — kode 6 digit sekali-pakai dari admin; terbit token sama seperti `/auth/login`. throttle:auth |
| POST | `/auth/register` 🔓 | throttle:auth |
| POST | `/auth/forgot-password` 🔓 | ⚠️ rusak di server (method tak ada) — pakai `/auth/login-otp` |
| POST | `/auth/reset-password` 🔓 | ⚠️ rusak di server (method tak ada) |
| POST | `/auth/logout` 🔒 | revoke token |
| GET | `/auth/me` 🔒 | user+roles+permissions |
| PUT | `/auth/profile` 🔒 | update profil (avatar multipart) |
| PUT | `/auth/password` 🔒 | ganti password |

### 4.2 Dashboard 🔒
`GET /dashboard`, `GET /dashboard/stats`, `GET /dashboard/class/{classroom}`.

### 4.3 Academic 🔒
`apiResource`: `years`(+`/{year}/activate`), `semesters`, `curricula`,
`grade-levels`, `majors`(+`template`,`export`,`import`), `classrooms`
(+`template`,`export`,`import`,`/{c}/students`,`/{c}/schedule`,`/{c}/teachers` GET/PUT),
`subjects`, `schedules`(+`generate`), `time-slots`.

### 4.4 Students 🔒
| Method | Path |
|---|---|
| GET/POST | `/students` (index scoped `visibleTo`, store) |
| GET/PUT/DELETE | `/students/{student}` |
| GET | `/students/{student}/guardians` · `/enrollments` · `/grades` · `/attendance` · `/fees` · `/achievements` |
| POST | `/students/{student}/enroll` · `/photo` (multipart) |
| DELETE | `/students/{student}/photo` |
| apiResource | `/students/guardians` |

### 4.5 Teachers 🔒
`apiResource /teachers` · `GET /teachers/{t}/assignment` · `POST /teachers/{t}/photo`
· `POST /teachers/{t}/documents` · `DELETE .../documents/{media}`.

### 4.6 Staff 🔒
`apiResource`: `/staff`, `departments`, `positions`, `leave-requests`
(+`/{lr}/approve`, `/reject`).

### 4.7 Attendance 🔒
| Grup | Endpoint |
|---|---|
| Siswa | `GET /attendance/students`, `/students/daily`, `/students/summary`, `/students/consecutive-absences`, `/students/top-late`; `POST /students/bulk`, `/students/notify-daily`; `PUT /students/{attendance}` |
| Guru/Pegawai | `GET /attendance/teachers`, `/teachers/daily`, `/teachers/summary`; `PUT /teachers/{attendance}` |
| Izin | `apiResource /attendance/permissions` (+`/{p}/approve`,`/reject`); `GET /attendance/permissions-pending-count` |
| Libur | `apiResource /attendance/holidays` (+`generate-weekends`,`bulk-delete`,`check`) |
| QR | `GET /attendance/qr/students/{student}` · `/teachers/{teacher}`; `POST .../regenerate`; `GET .../download`; `GET .../bulk` |
| Export/RFID | `GET /attendance/qr/export`; `POST /attendance/qr/generate-rfid`; `PUT /attendance/rfid/students/{s}` · `/teachers/{t}` |
| Kartu | `GET/PUT/DELETE /attendance/card-templates/{type}` |
| Laporan | `GET /attendance/reports/monthly` · `/pdf` · `/excel` · `/weekly-trend` |
| Settings 👑`settings.attendance` | `GET/PUT /attendance/settings`; `POST .../test-whatsapp|test-telegram|test-email`; `GET .../telegram-bot-info` |

### 4.8 Scanner 🔒 (QR/RFID)
| Method | Path | Body |
|---|---|---|
| GET | `/scan/bootstrap` | — |
| POST | `/scan` | `{ unique_code, waktu: "masuk"\|"pulang", latitude?, longitude? }` |
| POST | `/scan/sync-offline` | `{ scans: [ { unique_code, waktu, scanned_at, latitude?, longitude? } ] }` |
| POST | `/scan/lookup` | `{ unique_code }` → `{ type: student\|teacher, ... }` |

### 4.9 Exams & Grades 🔒
`apiResource /exams/types`, `/exams`(+`/{e}/scores` GET/POST, `/scores/bulk`).
`GET /grades`, `/grades/student/{student}`, `/grades/classroom/{classroom}`,
`POST /grades/finalize`.

### 4.10 Finance 🔒
`apiResource`: `fee-types`, `fee-structures`, `payments`(+`/{p}/verify`,`/receipt`),
`payment-methods`, `discounts`. `GET /finance/fees`, `POST /finance/fees/generate`,
`GET /finance/fees/{fee}`. Laporan: `GET /finance/reports/summary|monthly|outstanding`.

### 4.11 Library 🔒
`apiResource`: `categories`, `books`(+`/{b}/copies`), `members`, `loans`
(+`/{l}/return`,`/extend`), `reservations`. `GET/PUT /library/settings`.

### 4.12 Reports 🔒
`GET /reports/report-cards`, `/report-cards/{rc}`, `POST /report-cards/generate`,
`POST /report-cards/{rc}/approve`, `GET /report-cards/{rc}/pdf`.
`GET /reports/generated`, `POST /reports/generate`, `GET /reports/download/{report}`.

### 4.13 Notifications 🔒
| Method | Path |
|---|---|
| GET | `/notifications` (paginator; `?unread_only=1`, `?per_page=`) · `/notifications/unread-count` → `{count}` |
| POST | `/notifications/{n}/read` · `/notifications/read-all` |
| DELETE | `/notifications/{n}` |
| apiResource | `/notifications/announcements` (+`/{a}/publish`) |

### 4.14 Settings 🔒👑
`GET/PUT /settings/menu` 👑`settings.manage` · `GET/POST /settings/school`
👑`settings.school`.

### 4.15 Admin 👑`role:super_admin|admin`
Users (👑`super_admin`): `apiResource /admin/users` (+`activate`,`deactivate`,
`reset-password`, `roles`, `student-options`). Schools (👑`super_admin`):
`GET/POST /admin/schools`, `POST /admin/schools/{id}/activate|deactivate`,
`DELETE /admin/schools/{id}`. `apiResource /admin/roles`, `GET /admin/permissions`,
`GET /admin/audit-logs`(+`/{id}`), `GET /admin/activity-logs`.

Keamanan Login (menu web **Pengaturan → Keamanan Login**, bukan untuk mobile):
`GET /admin/login-security/logs` (riwayat login, filter `email|user_id|method|successful|from_date|to_date`),
`POST|DELETE /admin/login-security/users/{user}/otp` (buat/cabut kode akses sekali-pakai —
kode polos hanya dikembalikan sekali saat dibuat),
`POST /admin/login-security/users/{user}/revoke-sessions` (cabut semua token perangkat user).

### 4.16 Super Admin 👑`role:super_admin`
`apiResource /super-admin/tenants` (+`activate`,`suspend`) — **stub 501**.
`GET /super-admin/health`, `/super-admin/metrics`.

### 4.17 Public 🔓 (throttle 20/mnt)
| Method | Path | Ket |
|---|---|---|
| POST | `/public/izin/lookup` · `/izin/submit` · `/izin/status` | portal izin tanpa login |
| POST | `/public/cek-kehadiran` · `/riwayat-kehadiran` | cek kehadiran (mis. orang tua) |

---

## 5. Contoh Scan (alur mobile paling relevan)

**POST `/scan`**
```jsonc
// req (header: Authorization: Bearer ...)
{ "unique_code": "STU-AB12CD34EF56", "waktu": "masuk", "latitude": -6.2, "longitude": 106.8 }
// res 200 → { "success": true, "message": "...", "data": { /* hasil check-in */ } }
// res 422 → { "success": false, "message": "Kode tidak ditemukan / di luar jam / dst" }
```

**POST `/scan/lookup`** → identitas pemilik kode:
```jsonc
{ "success": true, "message": "Siswa ditemukan",
  "data": { "type":"student", "id":"...", "nis":"...", "name":"...", "classroom":"XA", "status":"active" } }
```

---

## 6. Catatan Penting untuk Klien Mobile

1. **Toleransi dua bentuk error** (envelope vs FormRequest 422).
2. **Tenant otomatis** untuk user biasa; header hanya untuk super admin.
3. **URL gambar** (`avatar_url`, `photo_url`) dibentuk dari `APP_URL` — verifikasi
   dapat diakses dari perangkat; lihat rekomendasi.
4. **Token 7 hari, tanpa refresh** — siapkan re-login saat `401`.
5. **Pagination**: baca `meta`/`links`.
6. **Tanggal**: ISO-8601 (`toISOString()`) untuk timestamp; `Y-m-d` untuk tanggal.
7. **Enum absensi**: `present|absent|late|sick|permitted|alpha`; `waktu`: `masuk|pulang`.
