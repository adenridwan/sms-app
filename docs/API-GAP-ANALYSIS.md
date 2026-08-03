# API Gap Analysis — Mobile Readiness

Companion to [03-API-CONTRACT.md](03-API-CONTRACT.md). That document lists every route's implementation status; this one re-sorts the same information by what it means for a mobile client, and closes with prioritized recommendations. No code has been changed as part of this analysis.

Legend for the "why" column: 📱 = works for mobile as-is, 🔧 = works but needs a change first, ❌ = doesn't exist yet, 🚫 = exists but shouldn't be given to mobile.

---

## 1. Ready for mobile (as-is)

These are implemented, verified callable (controller + method both exist), and their shape is reasonable for a mobile client to consume directly.

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `PUT /auth/profile`, `PUT /auth/password`, `POST /auth/register` |
| Scanner (device/kiosk pattern — the closest thing to a pre-built mobile API in this codebase) | `GET /scan/bootstrap`, `POST /scan`, `POST /scan/sync-offline`, `POST /scan/lookup` |
| QR codes | `GET /attendance/qr/students/{id}`, `/qr/students/{id}/download`, `/qr/students/bulk`, `POST /qr/students/{id}/regenerate`, and the teacher equivalents |
| RFID | `PUT /attendance/rfid/students/{id}`, `/rfid/teachers/{id}` |
| Attendance (student) | `GET /attendance/students`, `/students/daily`, `/students/summary`, `/students/consecutive-absences`, `/students/top-late`; `POST /students/bulk`, `/students/notify-daily`; `PUT /students/{id}` |
| Attendance (teacher) | `GET /attendance/teachers`, `/teachers/daily`, `/teachers/summary`; `PUT /teachers/{id}` |
| Leave permissions | Full CRUD + `POST /permissions/{id}/approve`, `/reject`; `GET /permissions-pending-count` |
| Holidays | Full CRUD + `POST /generate-weekends`, `/bulk-delete`, `/check` |
| Dashboard | `GET /dashboard`, `/dashboard/stats`, `/dashboard/class/{id}` |
| Academic (read/reference data) | `GET` on years, semesters, grade-levels, majors, classrooms, schedules; `GET /classrooms/{id}/students`, `/schedule`, `/teachers` |
| Teacher | Full CRUD + `assignment`, photo upload/delete, document upload/delete — **entire module fully wired**, no gaps found |
| Student | Full CRUD + photo upload/delete (top-level fields only — see §2 for the broken nested reads) |
| Public portal (usable as unauthenticated "guest" screens) | `POST /public/cek-kehadiran`, `/riwayat-kehadiran`, `/izin/lookup`, `/izin/submit`, `/izin/status` |

Attendance is the most production-ready module in the whole API — every route I checked resolves to a real, working method. If the mobile app's first release is attendance-centric (a teacher scanning students in/out, or a parent checking their child's attendance), the backend is largely there today.

---

## 2. Needs modification before mobile can rely on it

Implemented and callable, but the current shape/behavior would cause real problems for a mobile client if adopted unchanged.

| Issue | Where | Why it matters for mobile | Suggested fix |
|---|---|---|---|
| Two different error envelopes | Whole API — see [01-EXISTING-SYSTEM.md §10](01-EXISTING-SYSTEM.md) | Controller-thrown errors return `{success, message, errors}`; framework errors (422 validation, 401, 403, 404, 500) return Laravel's default `{message, errors?}` with no `success` key. A mobile HTTP client written against one shape will mis-parse the other. | Add a global JSON exception renderer (in `bootstrap/app.php`'s `withExceptions`, scoped to `api/*`) that normalizes every error response into the `ApiController` envelope, or update `ApiController` conventions to match Laravel defaults — pick one, apply everywhere. |
| Pagination metadata lost | `StudentCollection::with()` overwrites `meta` with a static `{success, message}` instead of merging the real paginator meta | Mobile lists (infinite scroll / page indicators) need `current_page`, `last_page`, `total`, `per_page`. Right now `GET /students` returns none of that. | Audit every `*Collection` resource for the same bug; fix `with()` to merge `parent::with($request)` (or the paginator's own meta) instead of replacing it. |
| QR delivered only as SVG data URI | `QrCodeGeneratorService::generateQrImage()` | Flutter/mobile toolchains don't render SVG natively without an extra package (`flutter_svg`) or a decode step; this was a deliberate choice to avoid a missing `imagick` extension server-side, not a mobile-aware decision. | Either add a PNG output path server-side (once `imagick`/`gd` availability is confirmed) or explicitly document "mobile must decode/render SVG" so it's a conscious choice, not a surprise. |
| Public attendance/leave lookup keyed only by NIS, no auth | `PublicAttendanceController@check/history`, `PublicLeaveController@lookup/status` | Fine for a public web widget where a parent has to already know the exact NIS + submit through a heavily-throttled form. Wrapping the same endpoints into an authenticated mobile app as "my child's attendance" would let any logged-in user view **any** student's attendance by guessing/knowing their NIS (often sequential or on a printed ID card) — a real, if narrow, information-disclosure risk once it's one tap inside an app instead of a manual public form. | For the mobile app, add authenticated equivalents that resolve the student from `student_guardians.user_id` (the caller's own linked children) instead of accepting a free-text `nis`. Keep the public/unauthenticated versions only for the existing public web portal use case. |
| Must-change-password flow is web-only | `EnsurePasswordIsCurrent` middleware only applied in `web.php`; `api_v1.php` never checks `mustChangePassword()` | A mobile user with an admin-forced password reset (or an auto-generated initial password) will log in successfully via the API with no signal that they must change it, then presumably hit confusing behavior elsewhere. | Surface `must_change_password` explicitly in the login/`me` response payload so the mobile app can force a change-password screen client-side; there's no server-side enforcement on the API today so this needs an explicit contract decision either way. |
| Single, non-refreshable, non-device-scoped Sanctum token | `AuthController@login/register` always call `createToken('auth_token')` | 7-day fixed expiry (`SANCTUM_TOKEN_EXPIRATION`), no refresh endpoint, no per-device token naming, no "list/revoke my sessions" endpoint. A mobile app either forces re-login weekly or needs new token-management endpoints. | Name tokens per device (`createToken($request->header('X-Device-Name', 'mobile'))`) and add `GET /auth/sessions` + `DELETE /auth/sessions/{id}` so users can see/revoke device logins — currently impossible from the API. |
| Two overlapping role systems | `spatie/laravel-permission` (11 granular roles) vs. `users.user_type` (5 coarse values) — see [01-EXISTING-SYSTEM.md §5](01-EXISTING-SYSTEM.md) | A mobile client doing role-based navigation/UI needs one source of truth; picking the wrong one (`user_type`) loses the guru/wali_kelas/tata_usaha/bendahara/pustakawan distinction entirely. | Standardize mobile-side role gating on the `roles`/`permissions` arrays already returned by `GET /auth/me`, not `user_type`. No backend change strictly required, but this should be written down as a contract decision so it isn't rediscovered per-screen. |
| Student nested detail routes exist but are broken | `students/{id}/guardians\|enrollments\|grades\|attendance\|fees\|achievements` — see §3, these are "needs modification" in the sense that the underlying relations (`guardians()`, `enrollments()`, `currentEnrollment()`) already exist on the `Student` model; only the controller method + resource wrapper are missing | A student/parent detail screen in the mobile app plausibly wants exactly these sub-resources. | Quick to implement relative to true net-new features — see recommendation #1 below. |

---

## 3. Missing endpoint (needs to be built from scratch)

Declared as a route (so the intent is documented in the codebase) but nothing behind it — no controller, and in most cases no service/model either.

| Capability | Current state | Notes |
|---|---|---|
| Password reset (forgot/reset) | Route exists, controller method doesn't | Blocks **every** client's self-service password recovery, not mobile-specific, but a mobile app cannot ship without it. |
| Grades / exam results (`/exams/*`, `/grades/*`) | Routes + migration exist; zero models, services, or controllers | If the mobile app is meant to show a student/parent their grades, this is a from-scratch build (schema is ready). |
| Report cards / rapor (`/reports/report-cards/*`) | Routes exist; controller missing | Needed for a "download my child's report card" mobile feature. |
| Per-student fee/payment status (`/finance/fees/*`) | Route + `StudentFee` concept exist in name only; only tenant-wide fee-type/payment admin CRUD works | A parent-facing "what do I still owe" screen has nothing to call today. |
| Notifications & announcements feed (`/notifications/*`) | Routes exist; controller missing entirely | If the mobile app wants an in-app notification/announcement feed, it must be built. Note this is separate from the working WhatsApp/Telegram attendance alerts, which are push-to-phone-number, not an in-app feed. |
| Native push notifications (FCM/APNs) | Does not exist in any form | The only "notification" channel implemented is outbound WhatsApp/Telegram messages to a phone number (see [01-EXISTING-SYSTEM.md §8](01-EXISTING-SYSTEM.md)). A mobile app wanting real push notifications needs an entirely new subsystem: device-token registration endpoint, storage, and a dispatch integration — nothing to gap-check, it's new scope. |
| Library / book loans (`/library/*`) | Routes + migration exist; controller missing | Likely low priority unless the mobile app targets students borrowing books. |
| Device/session management | No `tokens()` listing endpoint | Related to the token-refresh gap above; currently a user cannot see or revoke their own active mobile logins via the API. |
| Student self-service enrollment (`/students/{id}/enroll`) | `EnrollmentController` doesn't exist | Relevant if the mobile app will ever handle re-enrollment/class-transfer flows; otherwise low priority (this is an admin workflow today). |

---

## 4. Should not be exposed to mobile

Either implemented-and-working or not, these are back-office/admin surfaces that don't belong in a student/parent/teacher mobile app regardless of their implementation status. Flagging them here so they're consciously excluded from mobile scope rather than accidentally wrapped.

| Area | Reason |
|---|---|
| `/admin/*`, `/super-admin/*` (user management, roles/permissions, audit logs, activity logs, tenant management, system health/metrics) | Administrative/operational surface, gated to `super_admin`/`admin` roles by design. Even the parts that work (`admin/users/*`) are back-office CRUD with no mobile-appropriate UI shape. If a dedicated "ops" mobile app is ever wanted, scope it as its own client with its own review — don't fold it into a general school-community app. |
| Academic/Finance/Staff **write** operations (creating classrooms, academic years, fee types, staff records, payment entries) | Back-office data entry best done on the richer web admin UI (bulk import/export, spreadsheet templates). Mobile should at most **read** this data (e.g. "my schedule", "my classroom"), not manage it. |
| Card template editor (`attendance/card-templates/*`) | A drag-and-drop ID-card design tool — inherently a desktop/web UI, no sensible mobile equivalent. |
| Import/export & report-file endpoints (majors/classrooms template & import, attendance `reports/pdf`, `reports/excel`) | File-upload and generated-spreadsheet/PDF workflows are a poor mobile fit; leave these on web. A mobile app can still *link out* to a web-generated PDF if needed, but shouldn't reimplement the generation flow. |
| Tenant-switching (`X-Tenant-ID` header / `EnsureTenantMiddleware`) | Exists for a super-admin viewing a specific tenant from the web console. A student/parent/teacher's tenant is already fixed by their account — mobile should never need to send this header. |

---

## Recommendations, in priority order

1. **Fix the ~9 "method missing" routes before anything else.** Unlike the fully-missing modules, these are cheap: the underlying data/logic already exists (e.g. `Student::guardians()`/`enrollments()`/`currentEnrollment()`, `AcademicYearController::setActive()`, `PaymentController::pay()/cancel()/summary()`). This is route wiring plus a thin resource wrapper, not new business logic: `PasswordController::forgot/reset`, `AcademicYearController::activate`→alias to `setActive`, `ScheduleController::generate`, `StudentController::guardians/enrollments/grades/attendance/fees/achievements`, `PaymentController::verify/receipt`. This benefits the existing web app too, not just mobile.
2. **Standardize the error envelope for `api/*` before writing the mobile HTTP client.** Doing this after the mobile app exists means retrofitting every screen's error handling; doing it first means the client only ever parses one shape.
3. **Audit and fix pagination `meta` on every `*Collection` resource**, not just `StudentCollection` — mobile list screens depend on it.
4. **Decide, in writing, which role system mobile uses for UI gating** (recommend: Spatie `roles`/`permissions` from `/auth/me`, not `user_type`) so it isn't re-litigated per screen.
5. **Scope and prioritize the genuinely missing modules against what the mobile app's first release actually needs** — grades, fees, and notifications are all "build from scratch," so which one(s) ship in v1 is a product decision, not a technical one. Attendance (fully working) is the natural first-release anchor given its maturity.
6. **Add device-scoped, refreshable Sanctum tokens plus a session-management endpoint** before shipping, so mobile users aren't silently logged out every 7 days with no recourse.
7. **Harden the public NIS-only attendance/leave lookups before reusing them inside an authenticated mobile screen** — add guardian-linked authenticated equivalents rather than exposing the public, unauthenticated versions inside the app.
8. **Settle the QR image format question** (SVG-only today) with whoever owns the mobile app's rendering stack, so it's a deliberate choice rather than a rendering bug discovered late.
9. **If native push notifications are wanted, scope it as its own workstream** — nothing in the current codebase (WhatsApp/Telegram-only) provides a starting point for it.
