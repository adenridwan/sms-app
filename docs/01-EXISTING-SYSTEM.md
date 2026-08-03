# 01 — Existing System

All findings below were verified against the actual source in `backend/`, not against the aspirational docs at the repo root. Where I quote a file, the path is relative to `backend/`.

## 1. Laravel version & project structure

- Laravel `^12.0`, PHP `^8.4` (`composer.json`).
- Routing is registered in `app/Providers/RouteServiceProvider.php`, not the default Laravel 12 `bootstrap/app.php` `withRouting()` closure alone — that closure only wires `web.php`, `api.php` (health/info stub) and `console.php`. The real API lives in **`routes/api_v1.php`**, mounted by `RouteServiceProvider` at prefix `api/v1` with route-name prefix `api.v1.`.
- Three route files matter:
  - `routes/api.php` — just `/api/health` and `/api/` info stub.
  - `routes/api_v1.php` — the actual REST API (422 lines, see [03-API-CONTRACT.md](03-API-CONTRACT.md)).
  - `routes/web.php` — Inertia pages, session-authenticated.
- `routes/auth.php` exists in neither `withRouting()` nor `RouteServiceProvider` — **it is dead code, never loaded**. A comment in `web.php` confirms this was discovered and logout was migrated into `web.php` directly.

## 2. Authentication mechanism

Two parallel auth systems:

1. **Web (Inertia)**: standard Laravel session/cookie auth. Guarded by `auth` + custom `password.current` middleware (`App\Http\Middleware\EnsurePasswordIsCurrent`) which forces a password change when `users.preferences->must_change_password` is true (set for auto-generated passwords / admin resets).
2. **API**: Laravel Sanctum personal access tokens.
   - `POST /api/v1/auth/login` — validates credentials, checks `status === 'active'`, updates `last_login_at/ip`, issues token via `$user->createToken('auth_token')->plainTextToken`. Token expiration: `config('sanctum.expiration')`, default **7 days** (`config/sanctum.php`, `SANCTUM_TOKEN_EXPIRATION` env, minutes).
   - `POST /api/v1/auth/register` — public, delegates to `App\Domain\Auth\Services\AuthService::register()`.
   - `POST /api/v1/auth/logout` — revokes only the current token (`currentAccessToken()->delete()`).
   - `GET /api/v1/auth/me` — returns user + `roles` + `getAllPermissions()`.
   - **Important gap for mobile**: `password.current` (must-change-password enforcement) is only applied in `web.php`. Nothing in `api_v1.php` checks `mustChangePassword()`. A mobile client will never be told a password reset is pending unless it independently reads `user.preferences` or a future field is added.
   - `Route::middleware(['auth:sanctum'])` wraps almost the entire authenticated API; `EnsureFrontendRequestsAreStateful` is prepended to the `api` middleware group globally (`bootstrap/app.php`), which means Sanctum will also accept **cookie/session auth for any domain listed in `SANCTUM_STATEFUL_DOMAINS`** — this is for the Inertia SPA, not relevant to a mobile client, but confirms the API is not purely token-only by default (a mobile client should always send `Authorization: Bearer <token>` and never worry about CSRF/stateful cookies).

## 3. API routes

Full route inventory is in [03-API-CONTRACT.md](03-API-CONTRACT.md). Headline structure of `routes/api_v1.php`:

- Public (throttled `auth`, 5/min/IP): `auth/login`, `auth/register`, `auth/forgot-password`, `auth/reset-password`.
- Authenticated (`auth:sanctum`): `auth/*`, `dashboard*`, `academic/*`, `students/*`, `teachers/*`, `staff/*`, `attendance/*`, `scan/*`, `exams/*`, `grades/*`, `finance/*`, `library/*`, `reports/*`, `notifications/*`, `settings/*`.
- Admin-gated (`role:super_admin|admin`): `admin/*` (users, roles, permissions, audit logs, activity logs).
- Super-admin-only (`role:super_admin`): `super-admin/*` (tenants, system health/metrics).
- Public, unauthenticated, throttled 20/min: `public/izin/*` (leave submission portal), `public/cek-kehadiran`, `public/riwayat-kehadiran` (attendance lookup portal, by NIS only — no secret/auth).

## 4. Controllers, services, repositories, models, middleware

### Implemented vs. declared-only (critical finding)

I cross-checked every controller class referenced in `routes/api_v1.php` against the filesystem. **62 controller classes are referenced; 27 exist, 35 do not.** Hitting any of the 35 missing routes throws `ReflectionException: Class "..." does not exist` (verified — `php artisan route:list` itself crashes on the first missing class it encounters, `Api\V1\Academic\CurriculumController`).

**Exist and implemented:**
- Academic: `AcademicYearController`, `ClassroomController`, `GradeLevelController`, `MajorController`, `ScheduleController`, `SemesterController`
- Admin: `UserController` only
- Attendance (fully implemented, the most mature module): `AttendanceReportController`, `AttendanceSettingController`, `CardTemplateController`, `HolidayController`, `LeavePermissionController`, `PublicAttendanceController`, `PublicLeaveController`, `QrCodeController`, `RfidController`, `ScannerController`, `StudentAttendanceController`, `TeacherAttendanceController`
- Auth: `AuthController`, `PasswordController`, `ProfileController`
- `DashboardController`
- Finance: `FeeTypeController`, `PaymentController` only
- Student: `StudentController` only
- Teacher: `TeacherController`

**Referenced in routes but do NOT exist (will 500 if called):**
- Academic: `CurriculumController`, `SubjectController`, `TimeSlotController`
- Admin: `ActivityLogController`, `AuditLogController`, `PermissionController`, `RoleController`, `SystemController`
- Exam (entire module): `ExamController`, `ExamTypeController`, `GradeController`, `ScoreController`
- Finance: `DiscountController`, `FeeStructureController`, `PaymentMethodController`, `ReportController`, `StudentFeeController`
- Library (entire module): `BookCategoryController`, `BookController`, `LoanController`, `MemberController`, `ReservationController`, `SettingController`
- Notification (entire module): `AnnouncementController`, `NotificationController`
- Report (entire module): `GeneratedReportController`, `ReportCardController`
- Setting (entire module): `SettingController`
- Staff (entire module): `DepartmentController`, `LeaveRequestController`, `PositionController`, `StaffController`
- Student: `EnrollmentController`, `GuardianController`
- Tenant (entire module): `TenantController`

Migrations for most of these modules' tables **do** exist (`exam_grade`, `finance`, `library`, `notification`, `report`, `system` migrations are all present), so the schema is there — only the application layer (controller/service/model wiring for those specific missing pieces) is missing. `Staff` and `Student\StudentGuardian`/`StudentEnrollment` Eloquent models already exist in `app/Infrastructure/Persistence/Eloquent/`, they simply have no dedicated controller exposing them as their own resource (guardians/enrollments are currently only reachable as nested reads under `StudentController`).

### Services (Domain layer, real and used)

`app/Domain/Attendance/Services/`: `AttendanceScanService` (drives `ScannerController`), `AttendanceStatusResolver`, `AuditLogService`, `LateCalculationService`, `LeaveApprovalService`, `QrCodeGeneratorService`.
`app/Domain/Auth/Services/AuthService.php`: registration logic.
`app/Domain/Notification/Services/`: `NotificationDispatcher`, `TelegramService`, `WhatsAppService`.
`app/Domain/Tenant/Services/TenantService.php`: exists despite `Tenant\TenantController` not existing — likely used internally (e.g. by seeders/tenant middleware resolution) rather than exposed via API yet.

### Repositories

`app/Application/Contracts/RepositoryInterface.php` + `app/Infrastructure/Persistence/Repositories/BaseRepository.php` define a generic repository pattern, but **no controller or service in the codebase uses it** — every controller queries Eloquent models (`Student::query()`, etc.) directly. Treat the repository layer as unused scaffolding, not a pattern to extend.

### Models

Real models live under `app/Infrastructure/Persistence/Eloquent/`:
- `Academic/`: `AcademicYear`, `Classroom`, `GradeLevel`, `Major`, `Semester`
- `Attendance/`: `AttendanceAuditLog`, `AttendanceSetting`, `CardTemplate`, `EmployeeAttendance`, `Holiday`, `LeavePermission`, `NotificationSetting`, `OfflineScanQueue`, `ScannerDevice`, `StudentAttendance`
- `Auth/`: `User`, `UserProfile`
- `Staff/Staff`, `Student/{Student, StudentEnrollment, StudentGuardian}`, `Teacher/{Teacher, TeacherClassroom}`

`app/Models/User.php` and `app/Models/Tenant.php` are compatibility subclasses (the former adds mutators that route flat `first_name`/`phone`/etc. fields into the related `user_profiles` row; the latter is a near-duplicate of the real Tenant persistence model under a different namespace — worth consolidating eventually but not a blocker).

### Middleware

Registered in `bootstrap/app.php`:
- `role`, `permission`, `role_or_permission` → Spatie Permission middleware aliases.
- `tenant` → `App\Http\Middleware\EnsureTenantMiddleware` — **registered but not applied to any route** in `web.php` or `api_v1.php` (verified by grep). Tenant isolation in practice comes entirely from the `BelongsToTenant` Eloquent global scope + `X-Tenant-ID` header handling baked into `ApiController::currentTenantId()`, not from this middleware. Treat `EnsureTenantMiddleware` as currently dead code.
- `password.current` → `EnsurePasswordIsCurrent`, web-only (see §2).
- `HandleInertiaRequests` → web only, shares data into Inertia pages.
- Sanctum's `EnsureFrontendRequestsAreStateful` is prepended globally to the `api` middleware group.

## 5. User roles & permissions

Authorization is `spatie/laravel-permission` v6, guard `web` for all roles (`RoleSeeder` creates roles with `guard_name => 'web'`).

**11 seeded roles** (`database/seeders/RoleSeeder.php`): `super_admin` (all permissions, wildcard), `admin`, `kepala_sekolah`, `wakil_kepala_sekolah`, `guru`, `wali_kelas`, `tata_usaha`, `bendahara`, `pustakawan`, `siswa`, `orang_tua`. Permissions follow `module.action` dot notation (e.g. `students.view`, `attendance.record`, `grades.finalize`).

**A second, coarser classification exists in parallel**: `users.user_type` (enum-like string column: `super_admin`, `admin`, `teacher`, `student`, `parent`), backing helper methods `User::isSuperAdmin()/isAdmin()/isTeacher()/isStudent()/isParent()`. This is **not the same list** as the 11 Spatie roles (e.g. there is no `user_type` value distinguishing `guru` from `wali_kelas`, or `tata_usaha` from `bendahara` from `pustakawan` — those are presumably all `user_type = 'admin'` or `'teacher'` while carrying a more specific Spatie role). Any mobile client doing role-based UI branching needs to pick **one** of these two systems and should default to the granular Spatie roles/permissions (`GET /auth/me` returns both `roles` and `permissions`), not `user_type`.

**Row-level scoping** is implemented ad hoc per-model rather than centrally:
- `Student::scopeVisibleTo(User $user)` — admin-tier roles see all; `guru`/`wali_kelas` see only students in their `teachingClassroomIds()`; `siswa` sees only themselves; `orang_tua` sees only their linked children via `student_guardians`.
- `User::teachingClassroomIds()` — union of homeroom assignment, active-schedule teaching, and manual `teacher_classrooms` assignment for the active academic year. Deliberately **not cached** (a past bug: static caching returned stale data across requests in long-lived workers).

`EnsureTenantMiddleware` is not wired in, but tenant isolation still functions via `BelongsToTenant`'s global scope on every tenant-scoped model, keyed off `auth()->user()->tenant_id`, with an `X-Tenant-ID` header fallback specifically for super-admin accounts (`tenant_id = null`) impersonating/viewing a tenant.

## 6. Student & attendance tables

**`students`** (`App\Infrastructure\Persistence\Eloquent\Student\Student`): `id` (UUID), `tenant_id`, `user_id`, `nis`, `nisn`, `unique_code` (QR payload, auto-generated `STU-XXXXXXXXXXXX` on create), `rfid_code`, `poin_pelanggaran` (violation points), `entry_date`, `entry_type`, `previous_school`, `status` (`active`/`graduated`/`transferred`/`dropped`), `graduation_date`, `graduation_certificate_number`, `additional_info` (JSON), soft-deletes. Related: `student_guardians` (parent/guardian contacts, one is `is_primary_contact`), `student_enrollments` (classroom history, `status = 'active'` = current).

**`student_attendances`** (`StudentAttendance`): `id`, `tenant_id`, `student_id`, `classroom_id`, `academic_year_id`, `semester_id`, `attendance_date`, `status`, `menit_keterlambatan` (minutes late), `check_in_time`, `check_out_time`, `notes`, `excuse_document`, `recorded_by` (user), `latitude`/`longitude` (decimal(_,8), captured from scanner geolocation).

Known data-integrity note from `ROLE-ACCESS-PLAN.md` (still open as of the plan's last update): **attendance status values stored in the DB are English** (`present`, ...) while some attendance controllers validate **Indonesian** (`hadir`, ...) status strings — a pre-existing inconsistency in this codebase, not something introduced by this analysis. Any new client (mobile included) must go through `App\Domain\Attendance\Enums\AttendanceStatus` (which exposes `slug()`/`label()`) rather than assuming a single hardcoded status vocabulary.

Related attendance tables with models: `holidays`, `leave_permissions`, `employee_attendances` (teacher/staff), `attendance_settings`, `notification_settings`, `card_templates`, `attendance_audit_logs`, `offline_scan_queues`, `scanner_devices`.

## 7. QR / RFID functionality

Fully implemented, in `App\Domain\Attendance\Services\QrCodeGeneratorService` + `Api\V1\Attendance\{QrCodeController,RfidController,ScannerController}`:

- **Unique code**: every student/teacher gets a `unique_code` (`STU-`/`TCH-` + 12 random uppercase chars), generated lazily on first QR request or student/teacher creation. This code is the actual scan payload.
- **QR image**: rendered as **SVG only**, base64-encoded as a `data:image/svg+xml;base64,...` URI (`generateQrImage()`). A code comment explicitly notes PNG was avoided because it needs the `imagick` PHP extension, which isn't guaranteed present — this SVG data-URI is what any client (web or mobile) should expect back, not a raw PNG binary or a hosted image URL.
- Endpoints: get/download/regenerate/bulk for both students and teachers (`GET|POST attendance/qr/{students,teachers}/...`).
- **RFID**: separate from QR — `rfid_code` column on `students`/`teachers`, updated via `PUT attendance/rfid/{students,teachers}/{id}`, enforced unique per tenant by `App\Domain\Attendance\Rules\UniqueRfidCode`.
- **Lookup**: `Student::findByCode()` / `scopeFindByCode()` matches by **either** `unique_code` (QR) **or** `rfid_code` — the scan pipeline is code-agnostic.
- **Scanner API** (`Api\V1\Attendance\ScannerController`) — the closest thing to a "device-facing" API already in the codebase, and instructive for mobile design:
  - `GET scan/bootstrap` — preload data for a kiosk/device.
  - `POST scan` — single scan, body `{unique_code, waktu: 'masuk'|'pulang', latitude?, longitude?}`, returns success/fail with message.
  - `POST scan/sync-offline` — batch endpoint for offline-queued scans (array of scans with `scanned_at` client timestamps), returns a per-item success/fail report. **This offline-sync pattern already matches what a mobile app would need.**
  - `POST scan/lookup` — preview identity (student or teacher) by code before committing a scan.

## 8. WhatsApp / Telegram integration

Real and implemented, per-tenant configurable:

- `App\Domain\Notification\Services\WhatsAppService::initializeForTenant($tenantId)` loads `NotificationSetting::getForTenant()` and picks a provider based on `wa_provider` (`'wablas'` or default `'fonnte'`), instantiating `App\Infrastructure\External\Messaging\{FonnteProvider,WablasProvider}` and injecting the **tenant's own** `wa_api_key` (from the DB, not from `.env`).
- Both providers implement `MessagingProviderInterface` (`send`, `sendWithImage`, `isConfigured`, `getName`). Fonnte's constructor falls back to `config('services.fonnte.api_key')` if no key is passed, but in practice `WhatsAppService` always passes the tenant's key explicitly — the global `.env` keys (`FONNTE_API_KEY`, `WABLAS_API_KEY`, `TELEGRAM_BOT_TOKEN`, all in `config/services.php`) are effectively dead/fallback-only for this flow.
- `App\Domain\Notification\Services\TelegramService` + `Infrastructure\External\Messaging\TelegramProvider` provide an equivalent Telegram path.
- Attendance events (`StudentCheckedIn`, `StudentCheckedOut`, `TeacherCheckedIn`, `TeacherCheckedOut`) have listeners (`SendCheckInNotification`, `SendCheckOutNotification`) that presumably fan out to WhatsApp/Telegram on scan — this is how a guardian gets notified when their child checks in/out.
- Admin-facing settings & test endpoints exist: `GET|PUT attendance/settings`, `POST attendance/settings/test-whatsapp`, `POST attendance/settings/test-telegram`, `GET attendance/settings/telegram-bot-info` — all implemented in `AttendanceSettingController`.
- There is **no push-notification (FCM/APNs) infrastructure** in the codebase — "notification" here means WhatsApp/Telegram/announcements only. A mobile app wanting native push notifications would need this built from scratch; it doesn't exist to gap-check.

## 9. API response format

Base class `App\Http\Controllers\Api\ApiController` (all V1 controllers extend it) standardizes **manually-constructed** responses into:

```json
{ "success": true, "message": "...", "data": { ... } }
```
and for errors:
```json
{ "success": false, "message": "...", "errors": { ... } }
```
Helpers: `success()`, `resource()`, `collection()`, `created()` (201), `error()`, `notFound()` (404), `unauthorized()` (401), `forbidden()` (403), `validationError()` (422), `noContent()` (204), `deleted()`.

**This envelope is not actually universal** — see [03-API-CONTRACT.md](03-API-CONTRACT.md) and [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md) for why: framework-level errors (auth failures, route-model-binding 404s, FormRequest validation failures, unhandled exceptions) bypass `ApiController` entirely and return Laravel's default JSON shape instead.

**Pagination is inconsistent too**: `ApiController::collection()` is written to forward the real Laravel paginator `meta`/`links` from `$collection->response()->getData(true)`, but the one paginated resource I inspected in depth, `StudentCollection`, **overrides `with()`** to return `'meta' => ['success' => true, 'message' => '...']` — which replaces, rather than merges with, the real pagination meta (`current_page`, `last_page`, `total`, `per_page`). A client calling `GET /students` today gets `data` (the page of records) but **no usable pagination metadata**, only a decorative `meta.message`. Any other `*Collection` class should be checked individually for the same bug before mobile relies on paging.

## 10. Validation & error-response conventions

- Validation is a **mix of two styles**: dedicated `FormRequest` classes (only exist for `Auth` — login/register/change-password/update-profile — and `Student`/`Teacher` store/update) with Indonesian custom `messages()`, versus inline `$request->validate([...])` calls directly in controller methods (used everywhere else: `ScannerController`, `RfidController`, bulk-delete actions, etc.). No single validation convention covers the whole API.
- **Authorization is a mix of three mechanisms** in the same codebase: (a) route middleware `role:super_admin|admin` / `permission:...`, (b) Eloquent Policies via `$this->authorize(...)` (only `StudentPolicy` exists), (c) inline `abort_unless($request->user()->can('students.update'), 403)` calls. No consistent pattern to predict which mechanism guards a given endpoint without reading its controller.
- **Error response format diverges by failure source** (this is the most consequential finding for API-contract design):
  - Errors a controller catches and returns itself → `ApiController::error()` envelope (`{success:false, message, errors?}`).
  - `FormRequest` validation failures (422) → Laravel's **default** shape: `{"message": "The email field is required. (and 1 more error)", "errors": {"email": ["..."]}}`. No `success` key at all.
  - Unauthenticated (401, Sanctum) → default `{"message": "Unauthenticated."}`.
  - Unauthorized (403, Spatie role/permission middleware or Policy denial) → default `{"message": "This action is unauthorized."}` or `{"message": "User does not have the right roles."}`.
  - Route-model-binding miss (404, e.g. `students/{student}` with an unknown UUID) → default `{"message": "No query results for model [...]."}`.
  - `bootstrap/app.php`'s custom exception renderer (`withExceptions`) only rewrites responses for **web/Inertia** requests (`! $request->is('api/*')`) into elegant error pages — it explicitly does **not** touch `api/*` responses, so all of the above default-Laravel shapes reach API clients unmodified today.
- Rate limits (`app/Providers/RouteServiceProvider.php`): `auth` 5/min/IP (login/register/password), `uploads` 10/min/user-or-IP, `exports` 3/min/user-or-IP, generic `api` 60/min/user-or-IP (defined but I did not confirm it's applied as the default throttle for the whole `api_v1.php` group — worth a targeted check before depending on it). Public portal endpoints (`public/*`) use a flat `throttle:20,1` (20/min/IP).
