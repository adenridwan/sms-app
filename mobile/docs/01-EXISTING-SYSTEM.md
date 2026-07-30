# 01 — Existing System Analysis

> Analisis mendalam sistem backend Laravel yang sudah ada, sebagai dasar
> pembangunan aplikasi mobile. Read-only — tidak ada perubahan kode.

Referensi kode disebutkan dengan path relatif dari `backend/`.

---

## 1. Versi & Struktur Proyek

- **Laravel `^12.0`**, **PHP `^8.4`**. Bootstrap gaya Laravel 11+ di
  `bootstrap/app.php` (tanpa `Http/Kernel.php`).
- Arsitektur **berlapis (DDD-ish)**:

| Lapis | Lokasi | Isi |
|---|---|---|
| Application | `app/Application/` | `DTOs/`, `UseCases/`, `Contracts/` (mis. `RepositoryInterface`), `Traits/` |
| Domain | `app/Domain/<Modul>/` | `Services/`, `Enums/`, `Events/`, `Listeners/`, `Repositories/`, `Rules/`, `Jobs/`, `Exceptions/` |
| Infrastructure | `app/Infrastructure/` | `Persistence/Eloquent/<Modul>/` (Model Eloquent), `External/Messaging/` (Fonnte/Wablas) |
| Presentation | `app/Http/` | `Controllers/Api/V1/<Modul>/`, `Requests/`, `Resources/`, `Middleware/` |

  Modul: Academic, Attendance, Auth, Exam, Finance, Library, Notification,
  Report, Setting, Staff, Student, Teacher, Tenant.
- **Model** sebagian besar di `app/Infrastructure/Persistence/Eloquent/<Modul>/`
  (mis. `Student\Student`, `Teacher\Teacher`, `Auth\User`, `Auth\UserProfile`,
  `Attendance\NotificationSetting`), sebagian di `app/Models/` (`User`, `Tenant`).
  > Ada **dua kelas User**: `App\Models\User` dan
  > `App\Infrastructure\Persistence\Eloquent\Auth\User` (yang memakai
  > `HasApiTokens`, `HasRoles`). Perhatikan saat menautkan tipe.
- Helper global: `app/helpers.php` (`tenant()`, `tenant_id()`, `format_currency`, dst).

---

## 2. Mekanisme Autentikasi

- **Laravel Sanctum** (`laravel/sanctum ^4.0`). Model User memakai trait
  `HasApiTokens` (`app/Infrastructure/Persistence/Eloquent/Auth/User.php`).
- API terproteksi memakai guard **`auth:sanctum`** (`routes/api_v1.php:35`).
- Middleware API meng-*prepend* `EnsureFrontendRequestsAreStateful`
  (`bootstrap/app.php:24`) → mendukung **dua mode** sekaligus:
  1. **Token (Bearer)** — untuk klien non-browser (**mobile**).
  2. **SPA stateful (cookie)** — untuk web Inertia same-origin.
- **Login** (`AuthController::login`, `app/Http/Controllers/Api/V1/Auth/AuthController.php`):
  - `Auth::attempt(email, password)`; tolak jika `status !== 'active'`.
  - Menyimpan `last_login_at`, `last_login_ip`.
  - Menerbitkan token: `$user->createToken('auth_token')->plainTextToken`.
  - Respons: `{ user: UserResource, token, token_type: "Bearer" }`.
- **Masa berlaku token:** `config/sanctum.php` → `SANCTUM_TOKEN_EXPIRATION`,
  default **`60*24*7` menit = 7 hari**. Tidak ada refresh-token bawaan.
- **Logout** (`/auth/logout`): `currentAccessToken()->delete()` (revoke token
  aktif).
- **`/auth/me`**: mengembalikan `user`, `permissions` (semua nama permission),
  `roles`.
- **Register/Forgot/Reset** tersedia (`/auth/register`, `/auth/forgot-password`,
  `/auth/reset-password`) — publik, di-throttle.

**Rate limiting** (`app/Providers/RouteServiceProvider.php`):

| Limiter | Batas | Kunci |
|---|---|---|
| `auth` | 5 / menit | IP |
| `api` | 60 / menit | user id / IP |
| `uploads` | 10 / menit | user id / IP |
| `exports` | 3 / menit | user id / IP |
| `public` (inline `throttle:20,1`) | 20 / menit | — |

---

## 3. Otorisasi: Role & Permission

- **spatie/laravel-permission**, guard `web`. Middleware alias: `role`,
  `permission`, `role_or_permission` (`bootstrap/app.php:29`).
- **11 role** (`database/seeders/RoleSeeder.php`):

| Role | Deskripsi | Cakupan |
|---|---|---|
| `super_admin` | Full access (semua permission `*`) | Sistem / lintas tenant |
| `admin` | Administrator sekolah | Kelola data sekolah |
| `kepala_sekolah` | Kepala Sekolah | View semua, approve rapor |
| `wakil_kepala_sekolah` | Wakil Kepala Sekolah | — |
| `guru` | Guru | Mengajar, absensi, nilai |
| `wali_kelas` | Wali Kelas | Homeroom |
| `tata_usaha` | Tata Usaha | Administrasi |
| `bendahara` | Keuangan | Fees, payments, laporan |
| `pustakawan` | Perpustakaan | Buku, peminjaman |
| `siswa` | Siswa | **Data sendiri** (own) |
| `orang_tua` | Orang Tua/Wali | **Data anak** (own) |

- **124 permission** dalam 16 grup (`database/seeders/PermissionSeeder.php`):
  dashboard, users, roles, academic, students, teachers, staff, attendance,
  exams, grades, finance, library, reports, notifications, settings, audit,
  system, tenants.
- Pola penting untuk mobile: ada permission **`*-own`** —
  `students.view-own`, `attendance.view-own`, `grades.view-own`,
  `finance.view-own` — dimiliki role `siswa` & `orang_tua`.
  > ⚠️ **Namun belum ada route API yang melayani data "own"** (lihat
  > `API-GAP-ANALYSIS.md`). Endpoint data (mis. `/students/{id}/attendance`)
  > saat ini ditujukan untuk staf/guru, bukan self-service siswa.

**Model otorisasi berlapis:**
1. **Route middleware** — `role:...`, `permission:...` (mis. grup `admin`,
   `super-admin`, `settings.*`).
2. **Query scope** — `Student::visibleTo($user)` (row-level; membatasi baris
   sesuai role/relasi). `StudentController::index` memakai ini alih-alih
   permission middleware.
3. **Tenant scoping** — otomatis via `tenant_id` (lihat §7).

---

## 4. Modul & Controller (REST API v1)

Semua di `app/Http/Controllers/Api/V1/<Modul>/`. Ringkas per modul:

| Modul | Controller utama | Fungsi |
|---|---|---|
| Auth | `AuthController`, `ProfileController`, `PasswordController` | login/logout/me/profile/password |
| Dashboard | `DashboardController` | statistik ringkas |
| Academic | `AcademicYearController`, `SemesterController`, `CurriculumController`, `GradeLevelController`, `MajorController`, `ClassroomController`, `SubjectController`, `ScheduleController`, `TimeSlotController` | master akademik + jadwal |
| Student | `StudentController`, `GuardianController`, `EnrollmentController` | data siswa + relasi |
| Teacher | `TeacherController` | data guru, foto, dokumen |
| Staff | `StaffController`, `DepartmentController`, `PositionController`, `LeaveRequestController` | kepegawaian |
| **Attendance** | `StudentAttendanceController`, `TeacherAttendanceController`, `LeavePermissionController`, `HolidayController`, `QrCodeController`, `QrExportController`, `RfidController`, `CardTemplateController`, `AttendanceReportController`, `AttendanceSettingController`, **`ScannerController`** | absensi, QR/RFID, scan, laporan, izin |
| Exam/Grade | `ExamTypeController`, `ExamController`, `ScoreController`, `GradeController` | ujian & nilai |
| Finance | `FeeTypeController`, `FeeStructureController`, `StudentFeeController`, `PaymentController`, `PaymentMethodController`, `DiscountController`, `ReportController` | tagihan & pembayaran |
| Library | `BookCategoryController`, `BookController`, `MemberController`, `LoanController`, `ReservationController`, `SettingController` | perpustakaan |
| Report | `ReportCardController`, `GeneratedReportController` | rapor & laporan |
| Notification | `NotificationController`, `AnnouncementController` | notifikasi & pengumuman |
| Setting | `MenuSettingController`, `SchoolProfileController` | pengaturan menu & profil sekolah |
| Admin | `UserController`, `RoleController`, `PermissionController`, `SchoolController`, `AuditLogController`, `ActivityLogController` | back-office |
| Super Admin | `TenantController` (stub), `SystemController` | tenant & health |
| Public | `PublicLeaveController`, `PublicAttendanceController` | portal publik (tanpa login) |

- **Business logic** ada di `app/Domain/<Modul>/Services/` (mis.
  `AttendanceScanService`, `QrCodeGeneratorService`, `WhatsAppService`,
  `NotificationDispatcher`, `LateCalculationService`, `AttendanceStatusResolver`).
- **Base controller** `App\Http\Controllers\Api\ApiController` menyediakan helper
  respons seragam (lihat `03-API-CONTRACT.md §Envelope`).

---

## 5. Tabel Siswa (Student)

`database/migrations/0001_01_01_000005_create_student_tables.php`. Semua UUID PK,
`tenant_id`, soft delete.

| Tabel | Kolom penting |
|---|---|
| `students` | `user_id` (unik), `nis`, `nisn`, `entry_date`, `entry_type` (new/transfer/return), `status` (active/inactive/graduated/transferred/dropped_out), `additional_info` (json). **Unik `(tenant_id, nis)`**. Kolom `unique_code` & `rfid_code` ditambah migrasi lain (untuk QR/RFID). |
| `student_guardians` | `student_id`, `user_id?`, `relationship` (father/mother/guardian/other), `name`, `nik`, `phone`, `email`, `is_primary_contact`, `is_emergency_contact` |
| `student_enrollments` | `student_id`, `academic_year_id`, `classroom_id`, `student_number_in_class` (no absen), `status`. **Unik `(student_id, academic_year_id)`** |
| `student_documents` | `type`, `file_path`, `is_verified`, `verified_by` |
| `student_achievements` | `title`, `category`, `level`, `rank`, `achievement_date` |
| `student_violations` | `violation_type`, `points`, `status` |

`StudentResource` (`app/Http/Resources/StudentResource.php`) mengekspos: `id`,
`nis`, `nisn`, `unique_code`, `rfid_code`, `nik`, `user`, `full_name`, `email`,
`gender(+label)`, `birth_place`, `birth_date`, `age`, `religion`, `address`,
`phone`, `entry_*`, `status(+label)`, `photo_url`, `current_class`, `parents`.

---

## 6. Tabel Absensi (Attendance)

`database/migrations/0001_01_01_000007_create_attendance_tables.php` (+ migrasi
`000014_add_attendance_columns`, `000015_create_attendance_extension_tables`).

| Tabel | Kolom penting |
|---|---|
| `attendance_settings` | per-tenant (unik): `check_in_start/end`, `check_out_start/end`, `late_tolerance_minutes`, `require_location`, `require_photo`, `location_radius`, `school_latitude/longitude`, `working_days` (json) |
| `student_attendances` | `student_id`, `classroom_id`, `academic_year_id`, `semester_id`, `attendance_date`, `status` (present/absent/late/sick/permitted/alpha), `check_in_time`, `check_out_time`, `latitude/longitude`, `recorded_by`. **Unik `(student_id, attendance_date)`** |
| `subject_attendances` | absensi per jadwal/mapel: `schedule_id`, `student_id`, `attendance_date`, `status`. Unik `(schedule_id, student_id, date)` |
| `employee_attendances` | absensi guru/staf: `user_id`, `status` (+on_duty/wfh), check-in/out time+GPS+photo, `late_minutes`, `overtime_minutes`. Unik `(user_id, attendance_date)` |
| `attendance_summaries` | rekap bulanan polymorphic (`attendable`): total/present/absent/late/sick/permitted + `attendance_percentage` |

Migrasi ekstensi (`000015`) menambah tabel pendukung: hari libur (`holidays`),
izin/leave permission, audit log absensi, dsb (dipakai `HolidayController`,
`LeavePermissionController`, `AttendanceAuditLog`).

Enum status: `app/Domain/Attendance/Enums/` (`AttendanceStatus`, `ScanType`
[masuk/pulang], `LeaveStatus`, `LeaveType`).

---

## 7. Multi-Tenancy

- Model **single-DB + `tenant_id`** (row scoping), bukan DB-per-tenant.
- `TenantService` (`app/Domain/Tenant/Services/TenantService.php`) menyimpan
  "current tenant"; helper `tenant()` / `tenant_id()` (`app/helpers.php`).
- Resolusi tenant di API: `ApiController::currentTenantId()` →
  `user->tenant_id` **atau** header **`X-Tenant-ID`** (untuk super admin yang
  `tenant_id`-nya null).
- Trait `BelongsToTenant` memberi global scope berbasis `tenant()`.
- `EnsureTenantMiddleware` (alias `tenant`) memvalidasi akses tenant (bukan
  menyetel current tenant). **Implikasi mobile:** user biasa cukup login —
  `tenant_id` mereka otomatis membatasi data; **tidak perlu** kirim `X-Tenant-ID`.

---

## 8. QR Code / Ref ID / RFID

`app/Domain/Attendance/Services/QrCodeGeneratorService.php` +
`Api/V1/Attendance/QrCodeController`, `QrExportController`, `RfidController`,
`ScannerController`.

- **`unique_code`** (kode QR): siswa `STU-XXXXXXXXXXXX`, guru `TCH-XXXXXXXXXXXX`
  (unik per tabel). Disimpan di kolom `students.unique_code` / `teachers.unique_code`.
- **`rfid_code`**: `RF-XXXXXXXXXX`, unik lintas students+teachers dalam satu tenant.
- Gambar QR di-*generate* sebagai **SVG data URI** (`data:image/svg+xml;base64,...`)
  — sengaja SVG karena PNG butuh ekstensi `imagick` yang tak selalu ada. Ada juga
  PNG via GD (`generateQrPng`) untuk export ZIP/kartu.
- **Scanner API** (`/api/v1/scan`, `ScannerController`):
  - `GET /scan/bootstrap` — data awal scanner (per tenant).
  - `POST /scan` — proses 1 scan: `{ unique_code, waktu: masuk|pulang, latitude?, longitude? }` → check-in/out.
  - `POST /scan/sync-offline` — kirim banyak scan sekaligus (offline): array
    `{ unique_code, waktu, scanned_at, latitude?, longitude? }`.
  - `POST /scan/lookup` — pratinjau pemilik kode (siswa/guru) tanpa mencatat.
- **Export/kartu**: `GET /attendance/qr/export`, `POST /attendance/qr/generate-rfid`,
  `CardTemplateController` (editor kartu ID). Ini fitur back-office (berat), bukan
  target end-user mobile.

> Untuk mobile: alur scan + sinkron offline + GPS sudah **siap** dan sangat cocok
> untuk aplikasi petugas/operator. Untuk **self check-in siswa** (siswa scan
> QR-nya sendiri) belum ada alur/otorisasi khusus — lihat gap analysis.

---

## 9. Integrasi WhatsApp

`app/Domain/Notification/Services/WhatsAppService.php` +
`app/Infrastructure/External/Messaging/{FonnteProvider,WablasProvider}.php`.

- **Per-tenant**: konfigurasi diambil dari `NotificationSetting::getForTenant()`
  (`wa_provider` = `fonnte` [default] / `wablas`, `wa_api_key`).
- API: `send(phone, message)`, `sendWithImage(...)`,
  `sendAttendanceNotification(phone, template, replacements)` dengan template
  `{placeholder}`.
- Dipakai oleh listener absensi (`SendCheckInNotification`,
  `SendCheckOutNotification`) & `NotificationDispatcher` (multi-channel:
  WA/Telegram/Email).
- Uji koneksi via `POST /attendance/settings/test-whatsapp` (guarded
  `permission:settings.attendance`).
- **Portal publik** (tanpa login) terkait WA/kehadiran: `POST /api/v1/public/izin/*`
  (ajukan/lihat izin), `POST /api/v1/public/cek-kehadiran`,
  `POST /api/v1/public/riwayat-kehadiran` — berguna untuk fitur orang tua tanpa akun.

---

## 10. Format Respons API

**Base controller** `App\Http\Controllers\Api\ApiController` — envelope seragam:

```jsonc
// sukses (success/created)
{ "success": true, "message": "Success", "data": <any> }

// koleksi (paginated) — success + meta + links
{ "success": true, "message": "Success", "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, ... },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." } }

// error (error/notFound/forbidden/unauthorized)
{ "success": false, "message": "Pesan error", "errors": { ... }? }
```

Helper: `success()`, `created()`, `collection()`, `resource()`, `error()`,
`notFound()`, `unauthorized()`, `forbidden()`, `validationError()`, `deleted()`,
`noContent()`.

---

## 11. Konvensi Validasi & Error

- Validasi via **FormRequest** (`app/Http/Requests/...`) dan/atau
  `$request->validate([...])` inline (mis. `ScannerController`).
- **Pesan validasi** sering di-lokalkan ke Bahasa Indonesia
  (`LoginRequest::messages()`).
- **⚠️ Ketidakkonsistenan bentuk error** yang harus diantisipasi klien:
  - FormRequest gagal → **ValidationException default Laravel** → HTTP **422**:
    `{ "message": "...", "errors": { "field": ["..."] } }` (tanpa `success`).
  - Error manual via `ApiController::error()` →
    `{ "success": false, "message": "...", "errors"?: {...} }`.
  - Auth gagal → `unauthorized()` (401) `{ success:false, message }`.
  Klien mobile sebaiknya menangani **kedua** bentuk (cek `success` bila ada,
  lalu fallback ke `message` + `errors`).
- Kode status: 200 OK, 201 Created, 401 Unauthorized, 403 Forbidden,
  404 Not Found, 422 Unprocessable, 429 Too Many Requests.

---

## 12. Media & URL Aset

- File (avatar, foto siswa, logo sekolah) disimpan di disk `public`
  (`storage/app/public`, disajikan via symlink `/storage`).
- Beberapa resource membentuk URL dengan `asset('storage/'.$path)`
  (mis. `UserResource::avatar_url`, `StudentResource::photo_url`) → **absolut
  berbasis `APP_URL`**.
  > ⚠️ Di lingkungan dev, `APP_URL` bisa tidak cocok dengan host yang diakses
  > (mis. `:8000` vs `:8080`) sehingga URL gambar gagal dimuat. `SchoolProfileController`
  > sudah memakai path **root-relatif** untuk logo. Untuk mobile (host berbeda),
  > pastikan `APP_URL` benar atau backend mengembalikan URL absolut yang valid.
  > Lihat rekomendasi di `API-GAP-ANALYSIS.md`.
