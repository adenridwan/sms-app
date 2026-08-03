# 07 — Feature List (Flutter Mobile App)

Every feature below was checked against the actual backend controller/service source (see [01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md) and [03-API-CONTRACT.md](03-API-CONTRACT.md)), not inferred from route names alone. Where a detail could not be confirmed from source in this pass, it is explicitly marked **"not verified — confirm before implementation"** rather than guessed.

Features F1–F11 are the **priority attendance workflow** (Login → Dashboard → Scan/Input → Validate → Submit → Result → WhatsApp trigger). F12–F14 are secondary, lower-priority companions.

---

## F1. Login

- **Actor**: Administrator, Teacher, School Staff (any app user)
- **Preconditions**: User has an active account provisioned by an administrator (mobile self-registration is not intended for these operational roles); device has network connectivity.
- **User flow**:
  1. Open app → Login screen.
  2. Enter email + password.
  3. Tap "Login"; show loading state.
  4. On success: store token securely, navigate to Dashboard (F3).
  5. On failure: show the exact backend message inline near the offending field(s).
- **API endpoint**: `POST /api/v1/auth/login`
- **Request**:
  ```json
  { "email": "user@school.test", "password": "secret123", "remember": false }
  ```
- **Response 200**:
  ```json
  {
    "success": true,
    "message": "Login berhasil.",
    "data": {
      "user": { "id": "...", "username": "...", "email": "...", "full_name": "...", "roles": ["guru"], ... },
      "token": "1|abcdef...",
      "token_type": "Bearer"
    }
  }
  ```
- **Validation** (server, `LoginRequest`): `email` required, string, valid email; `password` required, string, min 6; `remember` boolean.
- **Error scenarios**:
  | Condition | Status | Body |
  |---|---|---|
  | Missing/invalid email or password format | 422 | Laravel default shape: `{"message": "...", "errors": {"email": ["..."]}}` — **no `success` key**, different envelope than the rest of this table |
  | Wrong credentials | 401 | `{"success": false, "message": "Email atau password salah."}` |
  | Account `status != 'active'` | 403 | `{"success": false, "message": "Akun Anda tidak aktif. Silakan hubungi administrator."}` — no token is issued in this case (verified: the status check happens after `Auth::attempt` succeeds but before `createToken`) |
  | No connectivity | client-only | Generic offline/retry banner |
- **Acceptance criteria**:
  - Valid, active-account credentials reach the Dashboard and the token persists across app restarts until expiry or explicit logout.
  - Invalid credentials show the backend's literal message, not a generic "login failed."
  - An inactive account never ends up with a stored token client-side.

---

## F2. Post-Login Bootstrap

- **Actor**: System (automatic), on behalf of any logged-in role
- **Preconditions**: valid token (freshly issued from F1, or restored from secure storage at cold start)
- **User flow**:
  1. Immediately after successful login, or on app cold start with a stored token, call both endpoints below before showing Dashboard.
  2. On failure of either, still attempt to show Dashboard using last-cached values if available; otherwise show a retry state.
- **API endpoint**: `GET /api/v1/auth/me`, `GET /api/v1/scan/bootstrap`
- **Request**: none (GET, Bearer token header only)
- **Response — `/auth/me`**:
  ```json
  { "success": true, "message": "Success", "data": { "user": { ... }, "permissions": ["attendance.record", "..."], "roles": ["guru"] } }
  ```
- **Response — `/scan/bootstrap`**:
  ```json
  {
    "success": true, "message": "Bootstrap data loaded",
    "data": {
      "today": "2026-07-27", "current_time": "07:12:03",
      "is_holiday": false, "holiday_info": null,
      "settings": { "check_in_start": "...", "check_in_end": "...", "check_out_start": "...", "check_out_end": "...", "late_tolerance_minutes": 10, "require_location": false },
      "check_in_deadline": "07:30:00"
    }
  }
  ```
- **Validation**: none (GET)
- **Error scenarios**: 401 (expired/invalid token) → clear local session, force redirect to Login (F1), preserving any unsynced offline queue (F10).
- **Acceptance criteria**:
  - The Scan screen (F4/F5) never requests location permission unless `settings.require_location` is true.
  - If `is_holiday` is true, the Scan screen shows a soft pre-warning before the user attempts a scan (the backend still hard-rejects the actual submit either way — this is purely to save a wasted attempt).

---

## F3. Dashboard (Home)

- **Actor**: Administrator, Teacher, School Staff
- **Preconditions**: logged in, bootstrap (F2) completed
- **User flow**:
  1. Land here after login/bootstrap.
  2. Role-appropriate summary cards (today's attendance counts, late count, pending leave-permission badge for roles with approval rights).
  3. A prominent primary action: **"Scan Attendance"**, reachable in one tap.
  4. Secondary navigation to Daily Recap (F12), Leave Permissions (F13), Profile (F14), Logout (F11).
- **API endpoint**: `GET /api/v1/dashboard`, `GET /api/v1/dashboard/stats`
- **Request**: none
- **Response**: **not verified — confirm exact payload shape against `DashboardController` output before binding UI**; treat as a role-dependent stats object.
- **Validation**: none (GET)
- **Error scenarios**: 401 → redirect to Login; empty data → render an explicit "no data yet" state, not a blank screen.
- **Acceptance criteria**:
  - Dashboard renders within 2s on typical connectivity or shows skeleton loaders.
  - "Scan Attendance" is reachable in exactly one tap from Dashboard for all three actor roles.

---

## F4. Scan QR Code

- **Actor**: Administrator, Teacher, School Staff (whoever is operating the scan session)
- **Preconditions**: camera permission granted; app is either online or offline-queue mode is active.
- **User flow**:
  1. From Dashboard, tap "Scan Attendance" → Scan screen, "Scan QR" tab active by default.
  2. Live camera view decodes a QR code.
  3. On decode, extract the payload string (the student/teacher's `unique_code`, e.g. `STU-XXXXXXXXXXXX`) and advance automatically to Validate (F6).
  4. If decoding stalls, offer a flashlight toggle and a one-tap switch to "Input Ref ID" (F5) without losing screen context.
- **API endpoint**: none directly — this step is local camera decoding only; the decoded string feeds F6.
- **Request/Response**: N/A
- **Validation**: non-empty decoded string; a client-side prefix hint (`STU-`/`TCH-`) may be used for UX only — the backend is authoritative regardless of prefix.
- **Error scenarios**: camera permission denied → deep-link to app settings; poor lighting/decode timeout → flashlight + manual fallback; a code that decodes fine but doesn't match anything is **not** an F4 error — it surfaces as F6's "not found" state.
- **Acceptance criteria**:
  - A well-lit, valid QR code is decoded and handed to Validate within ~1 second of steady framing.
  - Switching to Ref ID entry mid-scan does not lose or corrupt session state (e.g., a pending offline queue count).

---

## F5. Input Student Ref ID

- **Actor**: Administrator, Teacher, School Staff
- **Preconditions**: same as F4, minus camera; either a physical RFID reader configured as keyboard-wedge/HID input, or manual typing.
- **User flow**:
  1. Tap "Input Ref ID" tab. Text field autofocuses.
  2. Either type the code manually, or scan a physical RFID card against a connected reader (emits keystrokes + Enter into the same field).
  3. Submit → advance to Validate (F6) with the entered code, identically to F4.
- **API endpoint**: none directly — feeds F6/F7. **Backend does not distinguish QR from RFID**: `Student::findByCode()`/`Teacher::findByCode()` match against `unique_code` **or** `rfid_code` in a single query, so this and F4 converge on the same downstream call.
- **Request/Response**: N/A
- **Validation**: non-empty string; server-side max length is `100` chars (enforced in `POST /scan` and `/scan/lookup`); client should trim whitespace, since keyboard-wedge readers sometimes emit leading/trailing characters.
- **Error scenarios**: empty submit blocked client-side before any network call; a malformed/truncated code from a misconfigured reader is only caught downstream by F6's "not found" response — there's no separate RFID-specific error path on the backend.
- **Acceptance criteria**: a valid RFID code, whether typed or reader-scanned, reaches the Validate step (F6) exactly like a decoded QR code — the two entry UIs are visually distinct, but functionally identical from that point on.

---

## F6. Validate Student / Teacher (Lookup)

- **Actor**: Administrator, Teacher, School Staff
- **Preconditions**: a code string obtained from F4 or F5.
- **User flow**:
  1. App calls lookup automatically once a code is captured.
  2. Show a brief "Validating..." loading state.
  3. On match: show a confirmation card (name, NIS/NIP, classroom for students, status) and let the user confirm before submitting (F7).
  4. On no match: show a "not found" state with retry (re-scan) or switch-input options — **never auto-advance to Submit on a failed lookup.**
- **API endpoint**: `POST /api/v1/scan/lookup`
- **Request**:
  ```json
  { "unique_code": "STU-A1B2C3D4E5F6" }
  ```
- **Response 200 (student)**:
  ```json
  { "success": true, "message": "Siswa ditemukan", "data": { "type": "student", "id": "...", "nis": "2024001", "name": "Budi Santoso", "classroom": "7A", "status": "active" } }
  ```
- **Response 200 (teacher)**:
  ```json
  { "success": true, "message": "Guru ditemukan", "data": { "type": "teacher", "id": "...", "nip": "...", "name": "Ibu Sari", "status": "active" } }
  ```
- **Validation** (server): `unique_code` required, string, max 100.
- **Error scenarios**:
  | Condition | Status | Body |
  |---|---|---|
  | Code matches nothing | 404 | `{"success": false, "message": "Kode tidak ditemukan"}` |
  | Missing/empty code (client bug) | 422 | Laravel default validation shape |
  | Token expired mid-session | 401 | default `{"message": "Unauthenticated."}` |
- **Acceptance criteria**:
  - A valid code returns a recognizable confirmation card within ~1s on typical connectivity.
  - An unrecognized code never silently proceeds to Submit; the user must explicitly retry, re-scan, or cancel.
  - Note: the response includes **no photo field** — the confirmation card cannot show a picture without a separate change server-side; plan the layout around name/NIS/classroom text only.

---

## F7. Submit Attendance

- **Actor**: Administrator, Teacher, School Staff
- **Preconditions**: a validated code from F6; a chosen direction — check-in (`masuk`) or check-out (`pulang`) (see open decision in [02-MOBILE-REQUIREMENTS.md §6](02-MOBILE-REQUIREMENTS.md) on whether this is inferred or explicit).
- **User flow**:
  1. User confirms on the validation card (F6), optionally toggling masuk/pulang.
  2. App submits the code + direction + geolocation (captured only if `require_location` is true per F2's bootstrap).
  3. Loading state.
  4. Route to Result (F8) based on the response.
- **API endpoint**: `POST /api/v1/scan`
- **Request**:
  ```json
  { "unique_code": "STU-A1B2C3D4E5F6", "waktu": "masuk", "latitude": -6.2, "longitude": 106.8 }
  ```
  (`latitude`/`longitude` omitted entirely when location isn't required/available.)
- **Response 200 — student check-in**:
  ```json
  {
    "success": true, "message": "Absen masuk berhasil",
    "data": {
      "type": "student", "action": "check_in",
      "student": { "id": "...", "nis": "2024001", "name": "Budi Santoso", "classroom": "7A" },
      "time": "07:12:03", "late": false, "late_minutes": 0,
      "late_info": { "...": "category info object — see LateCalculationService" },
      "total_violation_points": 0
    }
  }
  ```
  When late, `message` becomes `"Terlambat {n} menit"`, `late: true`, and `total_violation_points` reflects the newly-added penalty.
- **Response 200 — student check-out**:
  ```json
  { "success": true, "message": "Absen pulang berhasil", "data": { "type": "student", "action": "check_out", "student": {"id","nis","name"}, "time": "15:02:11", "check_in_time": "07:12:03" } }
  ```
  Teacher check-in/check-out responses mirror this with a `teacher` object (`id`, `nip`, `name`) instead of `student`, and no violation-points field.
- **Validation** (server, exact rules in `ScannerController@scan`): `unique_code` required string max 100; `waktu` required, must be `masuk` or `pulang`; `latitude` nullable numeric between -90/90; `longitude` nullable numeric between -180/180.
- **Error scenarios** (all HTTP 422, `{"success": false, "message": "..."}`):
  | Message (verbatim from backend) | Meaning |
  |---|---|
  | `Hari ini adalah hari libur` | Today is a configured holiday — scanning is blocked entirely |
  | `Kode tidak ditemukan` | Code doesn't match any student or teacher (can happen here even after a successful F6 lookup, if the code was somehow altered between steps) |
  | `Siswa tidak terdaftar di kelas manapun` | Student has no active classroom enrollment |
  | `Siswa sudah melakukan absen masuk pada HH:mm` | Duplicate check-in attempt |
  | `Siswa sudah melakukan absen pulang pada HH:mm` | Duplicate check-out attempt |
  | `Siswa belum melakukan absen masuk` | Check-out attempted with no prior check-in today |
  | `Lokasi wajib diaktifkan untuk melakukan absen.` | Tenant requires location and none was sent |
  | `Anda berada {n} m dari sekolah, di luar radius maksimal {n} m.` | Geofence check failed |
  | `Gagal menyimpan absensi: {exception}` | Unexpected server-side failure |
  | (Teacher scans have the equivalent "Guru sudah..." / "Guru belum..." messages) | |
  | Network failure (no response) | client-side — should route to the Offline Queue (F10), not shown as a hard error |
- **Acceptance criteria**:
  - A successful submit updates the Result screen with the exact backend message, time, and lateness info within ~2s on typical connectivity.
  - Every documented error message is shown **verbatim** — "already checked in" is informational for front-line staff, not a bug to hide.
  - Double-tapping "Confirm" does not fire a duplicate network request (client-side debounce).

---

## F8. View Attendance Result

- **Actor**: Administrator, Teacher, School Staff
- **Preconditions**: F7 has returned a response.
- **User flow**:
  1. Full-screen or prominent modal result appears immediately after submit.
  2. **Success**: affirmative state, name, action (masuk/pulang), time, lateness badge if `late: true`.
  3. **Failure**: distinct alert state, the exact backend message, and a contextual next action (e.g., "already checked in" → dismiss; "not enrolled" → contact admin; geofence failure → check location/permissions).
  4. Return to Scan (F4/F5), ready for the next person — either a single tap or an automatic timeout, since this flow is a high-throughput queue (many students scanned back-to-back at a school gate).
- **API endpoint**: none — this screen renders F7's response.
- **Validation / Error scenarios**: N/A (this screen *is* the error display for F7).
- **Acceptance criteria**:
  - Success vs. failure is distinguishable at a glance, at typical scanning distance/lighting (color + icon + large text, not just a text string).
  - Returning to the next scan takes at most one tap after a success, and requires an explicit acknowledgment after a failure (so operators don't miss error states in a rapid-scan rhythm).

---

## F9. WhatsApp Notification Trigger (system-initiated)

- **Actor**: System (backend) — not directly triggered by the mobile user
- **Preconditions**: the tenant's `NotificationSetting` has WhatsApp configured (provider + API key) and the student has a guardian with a phone number on file (or the equivalent staff contact for teacher scans).
- **User flow** (backend-only, no mobile screen):
  1. A successful check-in/check-out in F7 fires a domain event (`StudentCheckedIn`, `StudentCheckedOut`, `TeacherCheckedIn`, `TeacherCheckedOut`).
  2. A **queued** listener (`SendCheckInNotification`/equivalent) picks it up asynchronously and calls `NotificationDispatcher` → `WhatsAppService` → the tenant's configured provider (Fonnte or Wablas).
- **API endpoint**: none callable from the mobile app — entirely a server-side side effect of F7. There is no endpoint to check delivery status after the fact.
- **Request/Response**: N/A from the app's perspective.
- **Validation**: N/A (server-side).
- **Error scenarios**: Send failures are caught and logged server-side; **they never fail or roll back the attendance record** (the scan has already succeeded by the time the event fires), and **the app is never told whether the message was sent, attempted, or failed** — there's no `notification_sent` field anywhere in the F7 response.
- **Acceptance criteria**:
  - The mobile UI **must never assert** "parent has been notified" as a guaranteed fact.
  - At most, a soft, non-committal indicator (e.g., "WhatsApp notification will be sent if configured for this school") may be shown — this is a copy/UX constraint, not something verifiable via API today. If a hard delivery guarantee is required for launch, that needs new backend work (a `notification_sent`/`notification_status` field), which is out of scope for this document — see [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md).

---

## F10. Offline Attendance Queue & Sync

- **Actor**: Administrator, Teacher, School Staff (scan operator)
- **Preconditions**: F7's network call fails due to connectivity; offline mode enabled in-app.
- **User flow**:
  1. On network failure during F7, store the attempted scan locally (code, direction, client timestamp, lat/long if captured).
  2. Show a distinct "queued for sync" result state (not success, not failure) with a running pending-count badge, and immediately allow the operator to continue scanning the next person.
  3. When connectivity returns (foreground check and/or background sync), batch-submit the queue.
  4. Reconcile per-item results and present a sync summary (how many succeeded, how many failed and why).
- **API endpoint**: `POST /api/v1/scan/sync-offline`
- **Request**:
  ```json
  {
    "scans": [
      { "unique_code": "STU-A1B2C3D4E5F6", "waktu": "masuk", "scanned_at": "2026-07-27T07:05:00", "latitude": null, "longitude": null },
      { "unique_code": "STU-Z9Y8X7W6V5U4", "waktu": "masuk", "scanned_at": "2026-07-27T07:06:12" }
    ]
  }
  ```
- **Response 200**:
  ```json
  {
    "success": true, "message": "Sync completed: 1 berhasil, 1 gagal",
    "data": {
      "total": 2, "success": 1, "failed": 1,
      "results": [
        { "unique_code": "STU-A1B2C3D4E5F6", "scanned_at": "2026-07-27T07:05:00", "success": true, "message": "Absen masuk berhasil" },
        { "unique_code": "STU-Z9Y8X7W6V5U4", "scanned_at": "2026-07-27T07:06:12", "success": false, "message": "Siswa sudah melakukan absen masuk pada 07:00" }
      ]
    }
  }
  ```
- **Validation** (server): `scans` required array, min 1; each item's `unique_code` required string; `waktu` required, `masuk`/`pulang`; `scanned_at` required date; `latitude`/`longitude` nullable numeric.
- **Error scenarios**: per-item failures are **expected and normal** (e.g., a queued duplicate submitted while offline, later found to already exist) — these are reported inside `data.results`, not as a whole-request failure. A whole-request 422 only happens if the array itself is malformed (empty, missing fields).
- **Acceptance criteria**:
  - No locally-queued scan is ever silently dropped — every queued item appears in the sync summary as either success or a specific, readable failure reason.
  - The queue survives an app restart before it has synced.
  - The operator can review and manually retry just the failed items from a completed sync run.

---

## F11. Logout

- **Actor**: Administrator, Teacher, School Staff
- **Preconditions**: logged in.
- **User flow**:
  1. Tap Logout (Dashboard/Profile menu).
  2. Confirm (recommended, to avoid accidental session loss mid-shift).
  3. Call logout, clear stored token, return to Login.
- **API endpoint**: `POST /api/v1/auth/logout`
- **Request**: none (Bearer token header only)
- **Response**: `{ "success": true, "message": "Logout berhasil.", "data": null }`
- **Validation**: none
- **Error scenarios**: token already invalid/expired (401) — treat as "already logged out" and clear local session regardless of the server response.
- **Acceptance criteria**:
  - No cached token remains usable after logout.
  - Any unsynced offline attendance queue (F10) is **preserved**, not deleted — it belongs to the device's pending work, not the login session, and must still be syncable after the next login.

---

## Secondary features (lower priority)

## F12. View Daily Attendance Recap

- **Actor**: Administrator, Teacher (own classes only), School Staff (per permission)
- **Preconditions**: logged in; `attendance.view` permission.
- **User flow**: From Dashboard, open "Today's Recap," pick a classroom (required) and date (defaults to today) → list of every enrolled student with their resolved status for that day, including students who haven't scanned at all.
- **API endpoint**: `GET /api/v1/attendance/students/daily`
- **Request** (query params): `classroom_id` (required, UUID), `date` (required, `YYYY-MM-DD`)
- **Response 200**:
  ```json
  {
    "success": true, "message": "Success",
    "data": {
      "date": "2026-07-27", "classroom_id": "...",
      "students": [
        { "student_id": "...", "nis": "...", "name": "...", "student_number_in_class": 1, "attendance_id": "...", "status": "hadir", "status_label": "Hadir", "status_color": "...", "check_in_time": "07:05", "check_out_time": null, "menit_keterlambatan": 0, "notes": null }
      ],
      "summary": { "...": "aggregate counts — not individually verified, confirm shape before binding a summary widget" }
    }
  }
  ```
- **Validation** (server): `classroom_id` required, UUID, must exist; `date` required, valid date. Access is further gated server-side by `authorizeClassroomAccess()` — a teacher who doesn't teach the requested classroom is rejected even with a syntactically valid request.
- **Error scenarios**: 403 if the user isn't authorized for that classroom; 422 on missing/invalid `classroom_id`/`date`; empty `students` array for a classroom with no active enrollments (render as empty state, not an error).
- **Acceptance criteria**: a teacher can only ever pull recaps for classrooms returned by their own `teachingClassroomIds()` — attempting another classroom's ID is rejected server-side, and the app should not offer such classrooms as selectable options in the first place.

## F13. Review & Approve/Reject Leave Permissions

- **Actor**: Administrator, `wali_kelas` (homeroom teacher)
- **Preconditions**: appropriate permission; at least one pending leave permission exists.
- **User flow**: Dashboard shows a pending-count badge → tap through to a list → open one → Approve, or Reject with a required reason.
- **API endpoints**:
  - `GET /api/v1/attendance/permissions-pending-count` → `{ "success": true, "message": "Success", "data": { "count": 3 } }`
  - `GET /api/v1/attendance/permissions` → list (pagination shape **not individually verified** for this resource — audit before binding, per the `StudentCollection` bug noted in [01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md))
  - `POST /api/v1/attendance/permissions/{id}/approve` — no request body
  - `POST /api/v1/attendance/permissions/{id}/reject` — request: `{ "reason": "string, required, max 500 chars" }`
- **Response** (approve/reject, both success and failure route through the same shape): `{ "success": true|false, "message": "...", "data": {...} }` — approve/reject failures return HTTP 422 with `success: false` and a message from `LeaveApprovalService` (exact message set **not individually verified** — confirm before writing client-side copy).
- **Validation**: `reject` requires a non-empty `reason` (max 500); `approve` has no body.
- **Error scenarios**: 403 if the user lacks approval rights or the permission isn't in their scope; 422 for invalid state transitions (e.g., approving an already-decided request) — exact messages to confirm against `LeaveApprovalService` before implementation.
- **Acceptance criteria**: approving or rejecting immediately decrements the pending-count badge in the UI without requiring a manual screen refresh.

## F14. View My Profile & QR Code

- **Actor**: Administrator, Teacher, School Staff (viewing their own identity — relevant if they themselves are subject to staff attendance scanning)
- **Preconditions**: logged in; user has a linked `teacher`/`staff` record to have a QR code at all.
- **User flow**: Profile tab shows name/role from `/auth/me`; if a linked teacher/staff record exists, a "My QR Code" section renders that person's own attendance QR.
- **API endpoint**: `GET /api/v1/auth/me` (to resolve identity/linked records); `GET /api/v1/attendance/qr/teachers/{teacher_id}` (to render the code) — there is **no dedicated "my own QR" shortcut endpoint**; the app must resolve its own `teacher_id` from `/auth/me`'s relations first.
- **Response** (QR endpoint): an SVG data URI (`data:image/svg+xml;base64,...`) per [01-EXISTING-SYSTEM.md §7](01-EXISTING-SYSTEM.md) — requires an SVG-capable image widget (e.g. `flutter_svg`), not a plain `Image.network`/PNG decoder.
- **Validation**: N/A
- **Error scenarios**: user has no linked teacher/staff record → hide the "My QR Code" section entirely, don't show an error.
- **Acceptance criteria**: the rendered QR, when scanned by the app's own Scan screen (F4) on a second device, round-trips correctly to a successful lookup (F6) for that same person.
