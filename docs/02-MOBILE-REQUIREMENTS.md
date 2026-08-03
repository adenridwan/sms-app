# 02 — Mobile Application Requirements (Flutter)

This defines the requirements for the planned Flutter mobile app, built against the existing API documented in [03-API-CONTRACT.md](03-API-CONTRACT.md) and scoped by the findings in [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md). No mobile code exists yet — this is the requirements baseline before implementation starts.

## 1. Primary users

| App-facing role | Maps to backend role(s) | Notes |
|---|---|---|
| **Administrator** | `super_admin`, `admin` | Full attendance operations + leave approval + (where implemented) admin data views. |
| **Teacher** | `guru`, `wali_kelas` | Attendance operations scoped to their own classes (`teachingClassroomIds()`); `wali_kelas` additionally sees approval actions for their homeroom. |
| **School staff** | `tata_usaha`, and potentially `bendahara`/`pustakawan` if they also operate the scanner | Attendance operations; administrative back-office actions (finance, library) are explicitly **out of scope** per §4. |

The backend exposes 11 granular Spatie roles plus a coarser `user_type` (see [01-EXISTING-SYSTEM.md §5](01-EXISTING-SYSTEM.md)). **The app must gate features using the `permissions`/`roles` arrays returned by `GET /auth/me`, not `user_type`** — this was an explicit recommendation from the gap analysis and is treated as settled for this requirements doc.

One app build, role-gated UI, is assumed rather than three separate builds — Administrator/Teacher/Staff differ only in which sections and actions are visible/enabled, not in core navigation structure.

## 2. Priority workflow: Student Attendance

This is the v1 anchor feature — see full breakdown in [07-FEATURE-LIST.md](07-FEATURE-LIST.md) and flow/wireframes in [08-UI-UX-FLOW.md](08-UI-UX-FLOW.md).

```
Login → Dashboard → Scan QR / Input Ref ID → Validate student → Submit attendance
      → Backend saves attendance → Show success/failure result
      → (async, backend-only) Trigger WhatsApp notification when applicable
```

Two capture methods feed the **same** backend code path:

1. **QR Code** — camera scan decodes a `unique_code` string (e.g. `STU-XXXXXXXXXXXX`) printed/displayed on the student's card.
2. **Student Ref ID** — RFID card read via an external reader (keyboard-wedge/HID input) or manually typed `rfid_code`.

The backend does not distinguish the two at the API level: `Student::findByCode()` / `Teacher::findByCode()` match against **either** `unique_code` or `rfid_code` in a single query. The app should treat both entry methods as producing one interchangeable "scanned code" value, submitted through the same endpoints (`POST /scan/lookup` then `POST /scan`).

## 3. Functional scope (v1)

**In scope:**
- Login / logout
- Dashboard with attendance summary and a primary "Scan Attendance" action
- QR scan capture
- Manual/RFID Ref ID capture
- Student/teacher code validation (lookup) before committing
- Attendance submission (check-in `masuk` / check-out `pulang`)
- Success/failure result display
- Offline scan queue + batch sync (the backend already supports this via `POST /scan/sync-offline` — a mobile scanning workflow is the exact use case this endpoint was built for)
- Daily attendance recap view (read-only)
- Leave permission review/approve/reject (Administrator, `wali_kelas`)

**Explicitly out of scope for v1** (per [API-GAP-ANALYSIS.md §4](API-GAP-ANALYSIS.md)):
- Any `/admin/*` or `/super-admin/*` surface (user/role/tenant management, audit logs, system health)
- Academic/Finance/Staff/Library data-entry (CRUD) — back-office web-only workflows
- Card template editor, import/export, PDF/Excel report generation
- Grades, exam results, report cards, fee/payment status, announcements/notification feed, library loans — **none of these exist on the backend yet** (see gap analysis §3); not buildable until the corresponding API work is prioritized separately
- Native push notifications — no backend infrastructure exists for this; WhatsApp/Telegram messaging is a separate, already-working, backend-only channel (see §5 below)

## 4. Non-functional requirements

| Requirement | Detail |
|---|---|
| **Offline-first for scanning** | The Scan screen must function with no connectivity: capture, validate-if-possible, and queue submissions locally, syncing via `POST /scan/sync-offline` when connectivity returns. This is not optional — it's the primary reason the backend already has a batch-sync endpoint shaped exactly for this. |
| **Scan throughput** | The flow must support rapid sequential scanning (a queue of students at a school gate) — result screen → next scan should be a single tap or fully automatic, not a multi-screen round trip each time. |
| **Token storage** | Sanctum bearer token stored in secure/encrypted device storage (e.g. Flutter Secure Storage), never in plain shared preferences. |
| **Session lifetime** | Backend tokens expire after 7 days by default with **no refresh endpoint** (see gap analysis). The app must handle silent expiry gracefully (detect 401, redirect to Login, preserve any pending offline queue) rather than crashing or losing queued scans. |
| **Location permission** | Only requested/used when the tenant's `AttendanceSetting.require_location` is true (learned from `GET /scan/bootstrap`). Do not request location unconditionally. |
| **Camera permission** | Required for QR capture; app must offer the Ref ID manual-entry path as a full substitute when camera access is denied, not just as a fallback after failure. |
| **Localization** | Bahasa Indonesia primary — all backend messages (validation, error strings) are already in Indonesian and should be surfaced verbatim, not re-translated or paraphrased, so operator-facing error text matches what admins see on the web app. |
| **Device targets** | Should support both dedicated Android scanning handheld devices (staff-operated, possibly with a physical RFID reader attached) and general Administrator/Teacher smartphones (camera-only QR scanning). |
| **Error transparency** | Because the backend's error envelope is inconsistent (see below), the app's HTTP layer must handle **both** `{success, message, errors}` and Laravel's default `{message, errors}` shapes without crashing on either. |

## 5. Known backend constraints that shape mobile design

Carried forward from [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md) — restated here because they directly constrain UX decisions in [08-UI-UX-FLOW.md](08-UI-UX-FLOW.md):

- **No delivery confirmation for WhatsApp notifications.** The notification is dispatched asynchronously (queued job) after a successful scan, with no field in the submit response indicating whether it was sent or delivered. **The UI must never claim a guardian "has been notified" as a verified fact** — see [07-FEATURE-LIST.md, Feature F9](07-FEATURE-LIST.md).
- **Password reset is not implemented** (`forgot`/`reset` routes point at methods that don't exist). Until fixed, the app cannot offer working self-service password recovery — Login screen should route users to contact an administrator instead of promising a reset flow that will 500.
- **No refresh token / device session management.** Plan for re-authentication UX around the fixed 7-day expiry.
- **QR is delivered as an SVG data URI**, not PNG. If the app ever needs to *display* another user's QR (e.g. Feature F14, viewing one's own code), it needs an SVG-capable renderer (e.g. `flutter_svg`).
- **Must-change-password is not enforced by the API** — a user with an admin-forced password reset can log in via mobile with no signal. If this matters for the launch, it needs an explicit product decision (see gap analysis recommendation #6), since the API doesn't currently expose the `must_change_password` flag on the login/`me` response body in a form confirmed usable by mobile.

## 6. Open decisions (need product input before implementation)

1. **Masuk vs. pulang selection**: should the app infer check-in vs. check-out from time-of-day/context, or always require an explicit toggle? (Affects Feature F6/F7 flow — see [08-UI-UX-FLOW.md](08-UI-UX-FLOW.md).)
2. **Single app build vs. role-specific builds** — assumed single build with role-gated UI (§1); confirm before navigation architecture is finalized.
3. **Offline queue retention policy** — how long do unsynced scans persist locally, and what happens if the same code is scanned twice offline before syncing (duplicate detection is currently only enforced server-side, at sync time)?
4. **RFID hardware assumption** — keyboard-wedge (HID, appears as text input) is assumed as the integration method; confirm against actual procured hardware before building the Ref ID input screen.
5. **Which secondary feature ships alongside attendance in v1** — daily recap and leave approval are both plausible companions; prioritize per §3, or defer both to a v1.1.
