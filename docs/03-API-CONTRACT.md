# 03 — API Contract

Base path: `/api/v1` (mounted by `App\Providers\RouteServiceProvider` from `routes/api_v1.php`). All routes are `application/json` in, `application/json` out (except file downloads noted below). Auth: `Authorization: Bearer <sanctum-token>` unless marked **Public**.

Every row's **Status** was verified by grepping the actual controller class for the exact method name the route points to — not assumed from the route definition alone. Four statuses:

- ✅ **OK** — controller and method both exist, route is callable.
- 🔴 **Broken: method missing** — controller class exists, but the method the route points to does not. Calling it throws a fatal error (`Error: Call to undefined method`), not a clean 404/422.
- 🔴 **Broken: controller missing** — the controller class itself does not exist. Calling it throws `ReflectionException`.
- ⚪ **Unrouted** — a working method exists on the controller but no route currently calls it (mentioned where relevant as a quick win).

## Response envelope (when it applies)

Success:
```json
{ "success": true, "message": "Human-readable message", "data": { /* payload */ } }
```
Error (thrown manually by a controller):
```json
{ "success": false, "message": "Human-readable message", "errors": { "field": ["..."] } }
```
**This envelope only applies to responses a controller builds itself.** Framework-level failures (auth, validation via `FormRequest`, route-model-binding misses, uncaught exceptions) return Laravel's default JSON error shape instead — no `success` key. See [01-EXISTING-SYSTEM.md §10](01-EXISTING-SYSTEM.md) for the full explanation; a mobile client **cannot assume `success` is always present** and must branch on HTTP status code as the primary signal, treating the envelope as a bonus when present.

Paginated list responses (`ApiController::collection()`) are *supposed* to include `meta` (page info) and `links`, but the one collection resource inspected (`StudentCollection`) overwrites `meta` with a static `{success, message}` pair instead of forwarding pagination data — verify per-resource before relying on `meta.current_page` etc.

## Auth

| Method | Path | Handler | Auth | Status |
|---|---|---|---|---|
| POST | `/auth/login` | `AuthController@login` | Public (throttle `auth`: 5/min/IP) | ✅ OK |
| POST | `/auth/register` | `AuthController@register` | Public (throttle `auth`) | ✅ OK |
| POST | `/auth/forgot-password` | `PasswordController@forgot` | Public (throttle `auth`) | 🔴 **Broken: method missing** — `PasswordController` only defines `change()`. |
| POST | `/auth/reset-password` | `PasswordController@reset` | Public (throttle `auth`) | 🔴 **Broken: method missing** — same class, no `reset()`. |
| POST | `/auth/logout` | `AuthController@logout` | Sanctum | ✅ OK — revokes only the current token. |
| GET | `/auth/me` | `AuthController@me` | Sanctum | ✅ OK — returns user + `roles` + `permissions`. |
| PUT | `/auth/profile` | `ProfileController@update` | Sanctum | ✅ OK |
| PUT | `/auth/password` | `PasswordController@change` | Sanctum | ✅ OK — verifies `current_password`, clears must-change flag. |

`ProfileController` also has working `show()` and `deleteAvatar()` methods that are **not routed** (⚪) — cheap to expose if mobile needs a dedicated "get my profile" or "remove my avatar" endpoint.

**Password reset being completely unimplemented affects every client, not just mobile** — this should be flagged/fixed independent of the mobile project.

## Dashboard

| Method | Path | Handler | Auth | Status |
|---|---|---|---|---|
| GET | `/dashboard` | `DashboardController@index` | Sanctum | ✅ OK |
| GET | `/dashboard/stats` | `DashboardController@stats` | Sanctum | ✅ OK |
| GET | `/dashboard/class/{classroom}` | `DashboardController@classStats` | Sanctum | ✅ OK — role-guarded internally (admin: any class, guru/wali_kelas: own classes only, else 403). |

## Academic

| Method | Path | Handler | Status |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/academic/years` (+`/{year}`) | `AcademicYearController` (apiResource) | ✅ OK |
| POST | `/academic/years/{year}/activate` | `AcademicYearController@activate` | 🔴 **Broken: method missing** — controller has `setActive()`, not `activate()`. |
| GET/POST/PUT/DELETE | `/academic/semesters` (+`/{id}`) | `SemesterController` (apiResource) | ✅ OK |
| GET/POST/PUT/DELETE | `/academic/curricula` (+`/{id}`) | `CurriculumController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/academic/grade-levels` (+`/{id}`) | `GradeLevelController` (apiResource) | ✅ OK |
| GET | `/academic/majors/template`, `/export` | `MajorController@template/export` | ✅ OK (file downloads) |
| POST | `/academic/majors/import` | `MajorController@import` | ✅ OK (throttle `uploads`) |
| GET/POST/PUT/DELETE | `/academic/majors` (+`/{id}`) | `MajorController` (apiResource) | ✅ OK |
| GET | `/academic/classrooms/template`, `/export` | `ClassroomController@template/export` | ✅ OK |
| POST | `/academic/classrooms/import` | `ClassroomController@import` | ✅ OK (throttle `uploads`) |
| GET/POST/PUT/DELETE | `/academic/classrooms` (+`/{id}`) | `ClassroomController` (apiResource) | ✅ OK |
| GET | `/academic/classrooms/{id}/students` | `ClassroomController@students` | ✅ OK |
| GET | `/academic/classrooms/{id}/schedule` | `ClassroomController@schedule` | ✅ OK |
| GET | `/academic/classrooms/{id}/teachers` | `ClassroomController@teachers` | ✅ OK |
| PUT | `/academic/classrooms/{id}/teachers` | `ClassroomController@syncTeachers` | ✅ OK |
| GET/POST/PUT/DELETE | `/academic/subjects` (+`/{id}`) | `SubjectController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/academic/schedules` (+`/{id}`) | `ScheduleController` (apiResource) | ✅ OK |
| POST | `/academic/schedules/generate` | `ScheduleController@generate` | 🔴 **Broken: method missing** — controller has `bulkStore()`, `byClass()`, `byTeacher()` instead, none routed. |
| GET/POST/PUT/DELETE | `/academic/time-slots` (+`/{id}`) | `TimeSlotController` | 🔴 **Broken: controller missing** |

## Student

| Method | Path | Handler | Status |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/students` (+`/{student}`) | `StudentController` (apiResource, non-standard root parameter) | ✅ OK — `index` supports `search`, `status`, `gender`, `class_id`, `sort`, `direction`, `per_page` query params; row-visibility via `Student::visibleTo($user)`. |
| GET | `/students/{student}/guardians` | `StudentController@guardians` | 🔴 **Broken: method missing** |
| GET | `/students/{student}/enrollments` | `StudentController@enrollments` | 🔴 **Broken: method missing** |
| GET | `/students/{student}/grades` | `StudentController@grades` | 🔴 **Broken: method missing** |
| GET | `/students/{student}/attendance` | `StudentController@attendance` | 🔴 **Broken: method missing** |
| GET | `/students/{student}/fees` | `StudentController@fees` | 🔴 **Broken: method missing** |
| GET | `/students/{student}/achievements` | `StudentController@achievements` | 🔴 **Broken: method missing** |
| POST | `/students/{student}/enroll` | `EnrollmentController@store` | 🔴 **Broken: controller missing** |
| POST | `/students/{student}/photo` | `StudentController@uploadPhoto` | ✅ OK |
| DELETE | `/students/{student}/photo` | `StudentController@deletePhoto` | ✅ OK |
| GET/POST/PUT/DELETE | `/students/guardians` (+`/{id}`) | `GuardianController` | 🔴 **Broken: controller missing** |

Note the `Student` model already has `guardians()`, `enrollments()`, `currentEnrollment()`, `currentClass` relations implemented — the *data* is one line away, only the controller methods returning it don't exist yet. `StudentController@show` already eager-loads `parents.user` and `StudentResource` already serializes a `parents` array, so a per-student guardian list is **partially** obtainable today via `GET /students/{id}` even though the dedicated `/guardians` sub-route is broken.

## Teacher

| Method | Path | Handler | Status |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/teachers` (+`/{teacher}`) | `TeacherController` (apiResource, non-standard root parameter) | ✅ OK |
| GET | `/teachers/{teacher}/assignment` | `TeacherController@assignment` | ✅ OK |
| POST/DELETE | `/teachers/{teacher}/photo` | `TeacherController@uploadPhoto/deletePhoto` | ✅ OK |
| POST | `/teachers/{teacher}/documents` | `TeacherController@uploadDocument` | ✅ OK |
| DELETE | `/teachers/{teacher}/documents/{media}` | `TeacherController@deleteDocument` | ✅ OK |

Teacher module is **fully wired** — every route resolves to a real method.

## Staff

| Method | Path | Handler | Status |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/staff` (+`/{staff}`) | `StaffController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/staff/departments` (+`/{id}`) | `DepartmentController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/staff/positions` (+`/{id}`) | `PositionController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/staff/leave-requests` (+`/{id}`) | `LeaveRequestController` | 🔴 **Broken: controller missing** |
| POST | `/staff/leave-requests/{id}/approve`, `/reject` | `LeaveRequestController@approve/reject` | 🔴 **Broken: controller missing** |

Entire Staff module is declared-only. `Staff` Eloquent model already exists (`app/Infrastructure/Persistence/Eloquent/Staff/Staff.php`), tables exist (`create_teacher_staff_tables` migration), only controllers are missing.

## Attendance — the most mature module, fully wired

Every route below resolves to a real, existing method. This is the only module I'd call production-ready as-is.

| Method | Path | Handler |
|---|---|---|
| GET | `/attendance/students`, `/students/daily`, `/students/summary`, `/students/consecutive-absences`, `/students/top-late` | `StudentAttendanceController` |
| POST | `/attendance/students/bulk`, `/students/notify-daily` | `StudentAttendanceController` |
| PUT | `/attendance/students/{attendance}` | `StudentAttendanceController@update` |
| GET | `/attendance/teachers`, `/teachers/daily`, `/teachers/summary` | `TeacherAttendanceController` |
| PUT | `/attendance/teachers/{attendance}` | `TeacherAttendanceController@update` |
| GET/POST/PUT/DELETE | `/attendance/permissions` (+`/{id}`) | `LeavePermissionController` (apiResource) |
| POST | `/attendance/permissions/{id}/approve`, `/reject` | `LeavePermissionController` |
| GET | `/attendance/permissions-pending-count` | `LeavePermissionController@pendingCount` |
| GET/POST/PUT/DELETE | `/attendance/holidays` (+`/{id}`) | `HolidayController` (apiResource) |
| POST | `/attendance/holidays/generate-weekends`, `/bulk-delete`, `/check` | `HolidayController` |
| GET | `/attendance/qr/students/{id}`, `/qr/students/bulk`, `/qr/students/{id}/download` | `QrCodeController` — QR is an **SVG data URI**, see [01-EXISTING-SYSTEM.md §7](01-EXISTING-SYSTEM.md) |
| POST | `/attendance/qr/students/{id}/regenerate` | `QrCodeController@regenerateStudent` |
| GET | `/attendance/qr/teachers/{id}`, `/qr/teachers/bulk`, `/qr/teachers/{id}/download` | `QrCodeController` |
| POST | `/attendance/qr/teachers/{id}/regenerate` | `QrCodeController@regenerateTeacher` |
| PUT | `/attendance/rfid/students/{id}`, `/rfid/teachers/{id}` | `RfidController` |
| GET/PUT/DELETE | `/attendance/card-templates/{type}` | `CardTemplateController` (`DELETE` = reset to default) |
| GET | `/attendance/reports/monthly`, `/reports/pdf`, `/reports/excel`, `/reports/weekly-trend` | `AttendanceReportController` (pdf/excel are file downloads, not JSON) |
| GET/PUT | `/attendance/settings` | `AttendanceSettingController` |
| POST | `/attendance/settings/test-whatsapp`, `/test-telegram` | `AttendanceSettingController` |
| GET | `/attendance/settings/telegram-bot-info` | `AttendanceSettingController` |

### Scanner (device/kiosk-facing — closest existing analog to a mobile API)

| Method | Path | Handler | Body |
|---|---|---|---|
| GET | `/scan/bootstrap` | `ScannerController@bootstrap` | — |
| POST | `/scan` | `ScannerController@scan` | `{unique_code, waktu: 'masuk'\|'pulang', latitude?, longitude?}` |
| POST | `/scan/sync-offline` | `ScannerController@syncOffline` | `{scans: [{unique_code, waktu, scanned_at, latitude?, longitude?}, ...]}` — per-item success/fail report returned |
| POST | `/scan/lookup` | `ScannerController@lookup` | `{unique_code}` — preview identity before committing |

### Public portal (no auth, `throttle:20,1`)

| Method | Path | Handler | Notes |
|---|---|---|---|
| POST | `/public/izin/lookup` | `PublicLeaveController@lookup` | ✅ OK |
| POST | `/public/izin/submit` | `PublicLeaveController@submit` | ✅ OK — leave/sick submission with photo evidence |
| POST | `/public/izin/status` | `PublicLeaveController@status` | ✅ OK |
| POST | `/public/cek-kehadiran` | `PublicAttendanceController@check` | ✅ OK — takes only `nis` (+ optional `date`), **no secret/auth** to prove the requester is the student's guardian |
| POST | `/public/riwayat-kehadiran` | `PublicAttendanceController@history` | ✅ OK — same `nis`-only exposure, returns a month of attendance history |

## Exam & Grade

| Method | Path | Handler | Status |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/exams/types` (+`/{id}`) | `ExamTypeController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/exams` (+`/{exam}`) | `ExamController` | 🔴 **Broken: controller missing** |
| GET | `/exams/{exam}/scores` | `ExamController@scores` | 🔴 **Broken: controller missing** |
| POST | `/exams/{exam}/scores`, `/scores/bulk` | `ScoreController` | 🔴 **Broken: controller missing** |
| GET | `/grades`, `/grades/student/{id}`, `/grades/classroom/{id}` | `GradeController` | 🔴 **Broken: controller missing** |
| POST | `/grades/finalize` | `GradeController@finalize` | 🔴 **Broken: controller missing** |

Entire module is declared-only. Migration `exam_grade_tables` exists; no Eloquent models, services, or controllers exist for it at all.

## Finance

| Method | Path | Handler | Status |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/finance/fee-types` (+`/{id}`) | `FeeTypeController` (apiResource) | ✅ OK |
| GET/POST/PUT/DELETE | `/finance/fee-structures` (+`/{id}`) | `FeeStructureController` | 🔴 **Broken: controller missing** |
| GET | `/finance/fees`, `/fees/{fee}` | `StudentFeeController` | 🔴 **Broken: controller missing** |
| POST | `/finance/fees/generate` | `StudentFeeController@generate` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/finance/payments` (+`/{id}`) | `PaymentController` (apiResource) | ✅ OK |
| POST | `/finance/payments/{id}/verify` | `PaymentController@verify` | 🔴 **Broken: method missing** — controller has `pay()`/`cancel()`/`summary()` instead, none routed. |
| GET | `/finance/payments/{id}/receipt` | `PaymentController@receipt` | 🔴 **Broken: method missing** |
| GET/POST/PUT/DELETE | `/finance/payment-methods` (+`/{id}`) | `PaymentMethodController` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/finance/discounts` (+`/{id}`) | `DiscountController` | 🔴 **Broken: controller missing** |
| GET | `/finance/reports/summary`, `/monthly`, `/outstanding` | `ReportController` | 🔴 **Broken: controller missing** |

Finance is roughly one-third wired: fee types and payment CRUD work; verification, receipts, structures, per-student fee generation, methods, discounts, and reporting do not exist.

## Library, Report, Notification, Setting — entirely declared-only

None of the following controllers exist. Migrations for the underlying tables do exist (`library_tables`, `report_tables`, `notification_tables`); no models, services, or controllers back them.

| Module | Routes | Would-be handler |
|---|---|---|
| Library | categories, books (+`/copies`), members, loans (+`/return`,`/extend`), reservations, settings (CRUD) | `Library\{BookCategoryController,BookController,MemberController,LoanController,ReservationController,SettingController}` |
| Report | report-cards (index/show/generate/approve/pdf), generated reports (index/generate/download) | `Report\{ReportCardController,GeneratedReportController}` |
| Notification | index/unread-count/read/read-all/destroy, announcements (CRUD +`/publish`) | `Notification\{NotificationController,AnnouncementController}` |
| Setting | index/show/update by group | `Setting\SettingController` |

## Admin & Super-Admin

Gated by `role:super_admin|admin` (outer) and `role:super_admin` (inner, users/tenants/system):

| Method | Path | Handler | Status |
|---|---|---|---|
| GET | `/admin/users/roles` | `UserController@roles` | ✅ OK (super_admin only) |
| GET | `/admin/users/student-options` | `UserController@studentOptions` | ✅ OK (super_admin only) |
| GET/POST/PUT/DELETE | `/admin/users` (+`/{id}`) | `Admin\UserController` (apiResource) | ✅ OK (super_admin only) |
| POST | `/admin/users/{id}/activate`, `/deactivate`, `/reset-password` | `Admin\UserController` | ✅ OK (super_admin only) |
| GET/POST/PUT/DELETE | `/admin/roles` (+`/{id}`) | `RoleController` | 🔴 **Broken: controller missing** |
| GET | `/admin/permissions` | `PermissionController@index` | 🔴 **Broken: controller missing** |
| GET | `/admin/audit-logs` (+`/{id}`) | `AuditLogController` | 🔴 **Broken: controller missing** |
| GET | `/admin/activity-logs` | `ActivityLogController@index` | 🔴 **Broken: controller missing** |
| GET/POST/PUT/DELETE | `/super-admin/tenants` (+`/{id}`) | `Tenant\TenantController` | 🔴 **Broken: controller missing** |
| POST | `/super-admin/tenants/{id}/activate`, `/suspend` | `Tenant\TenantController` | 🔴 **Broken: controller missing** |
| GET | `/super-admin/health`, `/metrics` | `Admin\SystemController` | 🔴 **Broken: controller missing** |

Only user management (`admin/users/*`) actually works; roles/permissions/audit/activity/tenant/system-health admin surfaces are all unimplemented.

## Summary count

Of the 62 distinct controller classes referenced across `routes/api_v1.php`, **27 exist**. Of the routes pointing at those 27 existing classes, a further **9 point at methods that don't exist on them** (`forgot`/`reset` on `PasswordController`; `activate` on `AcademicYearController`; `generate` on `ScheduleController`; `guardians`/`enrollments`/`grades`/`attendance`/`fees`/`achievements` on `StudentController`; `verify`/`receipt` on `PaymentController`). Net: **roughly 40% of the declared API surface is callable today**; the rest 500s on first hit. Full module-by-module status is in the tables above and mirrored, grouped by mobile-readiness, in [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md).
