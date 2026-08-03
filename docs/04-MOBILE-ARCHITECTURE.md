# 04 — Mobile Architecture (Flutter)

Architecture for the Flutter client against the existing Laravel API. This document defines structure and conventions only — **no code is implemented here**. It assumes the requirements in [02-MOBILE-REQUIREMENTS.md](02-MOBILE-REQUIREMENTS.md), the endpoints verified in [03-API-CONTRACT.md](03-API-CONTRACT.md), and the flow in [08-UI-UX-FLOW.md](08-UI-UX-FLOW.md).

## 1. Principles

1. **Feature-first, layered within each feature.** Features are the unit of ownership; `presentation`/`domain`/`data` inside each feature is the unit of testability. Nothing in `core/` knows about a specific feature; features may depend on `core/` and on `domain` layers of other features (via exported interfaces), never on another feature's `presentation` or `data`.
2. **Offline-first is not an afterthought.** The backend already exposes a batch offline-sync endpoint (`POST /scan/sync-offline`) built for exactly this use case (see [01-EXISTING-SYSTEM.md §7](01-EXISTING-SYSTEM.md)). The `attendance` feature's data layer is designed around a local queue from day one, not retrofitted later.
3. **The API's inconsistencies are absorbed at the edge, once.** The backend returns two different error envelopes and at least one known-broken pagination `meta` (see [01-EXISTING-SYSTEM.md §9-10](01-EXISTING-SYSTEM.md)). Every one of these quirks is normalized in `core/api` / `core/errors` so that feature code never special-cases backend behavior. Full detail in [09-ERROR-HANDLING.md](09-ERROR-HANDLING.md).
4. **The client is not a trust boundary.** Role/permission gating in the UI is convenience, not security — the backend's Spatie roles/policies are the real enforcement. See [10-SECURITY.md](10-SECURITY.md).
5. **Build only what the backend can support today.** Per [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md), several plausible features (grades, fees, a real notification feed, push notifications) have no backend behind them yet. Their feature folders are **not created** until the corresponding API exists — see §9 "Explicitly deferred features" below.

## 2. Folder structure

```
lib/
├── main_development.dart          # entrypoint per flavor, sets env + runs bootstrap
├── main_staging.dart
├── main_production.dart
├── app/
│   ├── app.dart                   # MaterialApp.router root widget, theme, locale
│   ├── router/
│   │   ├── app_router.dart        # go_router configuration + redirect logic
│   │   ├── app_routes.dart        # route name/path constants
│   │   └── route_guards.dart      # auth/role guards used by redirect()
│   └── bootstrap.dart             # DI container wiring, error zone, logging init
│
├── core/
│   ├── api/
│   │   ├── api_client.dart              # Dio instance + base configuration
│   │   ├── interceptors/
│   │   │   ├── auth_interceptor.dart    # attaches Bearer token
│   │   │   ├── logging_interceptor.dart # request/response logging (redacted)
│   │   │   ├── error_interceptor.dart   # normalizes both error envelopes (see 09-ERROR-HANDLING.md)
│   │   │   └── retry_interceptor.dart   # safe-method retry with backoff
│   │   ├── api_endpoints.dart            # endpoint path constants, grouped by feature
│   │   └── api_result.dart               # Result<T, Failure> used by all repositories
│   ├── auth/
│   │   ├── session_controller.dart       # cross-cutting auth session state (Riverpod)
│   │   ├── auth_state.dart               # sealed: Unknown / Authenticated / Unauthenticated
│   │   └── current_user.dart             # cached identity + roles/permissions, read by guards
│   ├── config/
│   │   ├── app_config.dart               # per-flavor values (base URL, env name, flags)
│   │   ├── flavor.dart                   # enum Flavor { development, staging, production }
│   │   └── feature_flags.dart            # e.g. offlineSyncEnabled, biometricLockEnabled
│   ├── errors/
│   │   ├── failure.dart                  # sealed Failure hierarchy (see 09-ERROR-HANDLING.md)
│   │   ├── exception_mapper.dart         # DioException/local exception → Failure
│   │   └── error_presenter.dart          # Failure → user-facing copy + presentation hint
│   ├── logging/
│   │   ├── app_logger.dart               # thin wrapper over `logger`, redaction rules
│   │   └── log_sinks.dart                # console (debug) / crash-reporting (release) sinks
│   └── storage/
│       ├── secure_token_storage.dart     # flutter_secure_storage wrapper (see 06-AUTH-FLOW.md)
│       ├── local_database.dart           # Drift database definition (offline queue + caches)
│       └── preferences_storage.dart      # non-sensitive local prefs (theme, last sync time)
│
└── features/
    ├── auth/
    │   ├── data/
    │   │   ├── auth_api.dart                  # POST /auth/login, /register, /logout, GET /auth/me
    │   │   ├── auth_repository_impl.dart
    │   │   └── models/
    │   │       ├── login_request.dart
    │   │       └── auth_response.dart         # user + token + token_type
    │   ├── domain/
    │   │   ├── entities/user.dart
    │   │   ├── repositories/auth_repository.dart   # abstract interface
    │   │   └── usecases/
    │   │       ├── login_usecase.dart
    │   │       ├── logout_usecase.dart
    │   │       └── restore_session_usecase.dart    # app-start: token present? call /auth/me
    │   └── presentation/
    │       ├── login_page.dart
    │       ├── login_controller.dart          # Riverpod Notifier, drives LoginPage
    │       └── widgets/
    │
    ├── dashboard/
    │   ├── data/ (dashboard_api.dart, dashboard_repository_impl.dart, models/)
    │   ├── domain/ (entities/dashboard_summary.dart — shape pending confirmation, see §9;
    │   │             repositories/dashboard_repository.dart; usecases/get_dashboard_usecase.dart)
    │   └── presentation/ (dashboard_page.dart, dashboard_controller.dart, widgets/stat_card.dart, ...)
    │
    ├── students/                      # deliberately thin in v1 — see §9 scope note
    │   ├── data/ (student_lookup_api.dart wraps POST /scan/lookup read-side only for now)
    │   ├── domain/ (entities/student_summary.dart)
    │   └── presentation/ (— no standalone screens in v1; consumed by attendance feature)
    │
    ├── attendance/                    # the priority feature — see §8 for full detail
    │   ├── data/
    │   │   ├── scan_api.dart                  # POST /scan/lookup, /scan, /scan/sync-offline, GET /scan/bootstrap
    │   │   ├── attendance_repository_impl.dart
    │   │   ├── local/
    │   │   │   ├── offline_queue_dao.dart     # Drift DAO for queued scans
    │   │   │   └── offline_queue_table.dart
    │   │   └── models/ (scan_lookup_response.dart, scan_submit_response.dart, ...)
    │   ├── domain/
    │   │   ├── entities/ (validated_person.dart, attendance_result.dart, queued_scan.dart)
    │   │   ├── repositories/attendance_repository.dart
    │   │   └── usecases/
    │   │       ├── validate_code_usecase.dart      # POST /scan/lookup
    │   │       ├── submit_attendance_usecase.dart  # POST /scan, falls back to queue on NetworkFailure
    │   │       ├── enqueue_offline_scan_usecase.dart
    │   │       ├── sync_offline_queue_usecase.dart # POST /scan/sync-offline
    │   │       ├── get_daily_recap_usecase.dart    # secondary: GET /attendance/students/daily
    │   │       ├── list_leave_permissions_usecase.dart   # secondary
    │   │       └── decide_leave_permission_usecase.dart  # secondary: approve/reject
    │   └── presentation/
    │       ├── scan_page.dart                 # tabs: QR / Ref ID (F4/F5)
    │       ├── scan_controller.dart           # state machine mirroring 08-UI-UX-FLOW.md §4
    │       ├── validate_confirm_sheet.dart    # F6 confirmation card
    │       ├── attendance_result_page.dart    # F8 success/failure/queued states
    │       ├── sync_summary_page.dart         # F10 sync summary
    │       ├── daily_recap_page.dart          # secondary
    │       └── leave_permissions_page.dart    # secondary
    │
    ├── qr_scanner/                    # capture-only component, deliberately separate from attendance
    │   ├── data/ (— none; no backend calls, pure device capability)
    │   ├── domain/ (entities/scan_capture.dart — decoded string + capture method)
    │   └── presentation/ (qr_camera_view.dart, ref_id_input_field.dart)
    │   # attendance/presentation composes qr_scanner's widgets; qr_scanner never imports attendance.
    │
    ├── notifications/                 # scope intentionally reduced — see §9
    │   ├── data/ (— none in v1; no backend endpoint exists, see API-GAP-ANALYSIS.md §3)
    │   ├── domain/ (entities/local_alert.dart — in-app-only banners, e.g. pending-leave badge sourced from attendance feature)
    │   └── presentation/ (notification_badge.dart — a shared widget, not a screen)
    │
    └── profile/
        ├── data/ (profile_api.dart — GET/PUT /auth/profile, GET /attendance/qr/teachers/{id})
        ├── domain/ (entities/own_qr_code.dart; repositories/profile_repository.dart;
        │            usecases/update_profile_usecase.dart, get_own_qr_usecase.dart)
        └── presentation/ (profile_page.dart, my_qr_page.dart)
```

## 3. Layer responsibilities (per feature)

| Layer | Contains | Depends on | Never contains |
|---|---|---|---|
| **domain** | Entities (plain Dart, no JSON/Dio knowledge), repository *interfaces*, use cases (one class = one action, e.g. `SubmitAttendanceUseCase`) | Nothing outside Dart/`core/errors` (for `Failure` types) | HTTP, JSON keys, widgets, Riverpod |
| **data** | DTOs/models (`freezed` + `json_serializable`), remote data sources (thin wrappers around `core/api`'s Dio instance), local data sources (Drift DAOs), repository *implementations* mapping DTOs → domain entities and `DioException` → `Failure` | `domain` (implements its interfaces), `core/api`, `core/storage` | Widgets, navigation, presentation state |
| **presentation** | Pages/screens, widgets, Riverpod `Notifier`/`AsyncNotifier` controllers holding UI state, view-state classes (`sealed class ScanViewState`) | `domain` (use cases only — never `data` directly) | Dio, SQL, raw JSON |

This gives each feature the same shape as [07-FEATURE-LIST.md](07-FEATURE-LIST.md)'s features: a use case per documented "API endpoint" row, a repository per feature, and a controller per screen/state-machine in [08-UI-UX-FLOW.md](08-UI-UX-FLOW.md).

## 4. API client (`core/api`)

- **HTTP client**: `dio`. Chosen over `package:http` for its interceptor chain, which is where all of the backend's quirks get absorbed exactly once (see principle 3).
- **Base configuration**: base URL from `AppConfig` (per-flavor, §7), `connectTimeout`/`receiveTimeout` set deliberately **short** (e.g. 5–8s) for the attendance-submit call specifically — the whole point of the offline queue (F10) is to detect "we're offline" fast and queue, not hang the scan screen waiting on a slow connection.
- **Interceptor order** (registration order matters in Dio):
  1. `AuthInterceptor` — attaches `Authorization: Bearer <token>` from `SessionController`. **Never** attaches an `X-Tenant-ID` header — that mechanism is web/super-admin-only (see [01-EXISTING-SYSTEM.md §5](01-EXISTING-SYSTEM.md)); a mobile user's tenant is always implicit from their own account.
  2. `LoggingInterceptor` — logs method/path/status/duration through `core/logging`, with request/response bodies redacted per [09-ERROR-HANDLING.md](09-ERROR-HANDLING.md)/[10-SECURITY.md](10-SECURITY.md) (never logs `password`, `token`, or full `unique_code`/`rfid_code` values in release builds).
  3. `ErrorInterceptor` — the single place that inspects a failed response and normalizes it into a `Failure` (full spec in [09-ERROR-HANDLING.md](09-ERROR-HANDLING.md)); attaches the mapped `Failure` to the thrown `DioException` so repositories don't re-parse response bodies themselves.
  4. `RetryInterceptor` — retries idempotent `GET`s on transient network failure with capped exponential backoff; explicitly **excluded** for `POST /scan` (must never silently retry an attendance submission — that's what the offline queue is for, not blind HTTP retry) and for `POST /scan/sync-offline` is allowed to retry the whole batch (the backend already reports per-item duplicates gracefully, see [09-ERROR-HANDLING.md](09-ERROR-HANDLING.md) §7).
- **Result type**: every repository method returns `Result<T, Failure>` (a small `freezed` sealed union, not a third-party FP package) rather than throwing across the domain/presentation boundary. Use cases and controllers pattern-match on the result; nothing above the data layer ever catches a `DioException` directly.
- **Endpoint constants**: `api_endpoints.dart` centralizes every path from [03-API-CONTRACT.md](03-API-CONTRACT.md) that the app actually calls, grouped by feature with a comment citing the contract doc's status (✅/🔴) so nobody accidentally wires up one of the ~35 known-broken routes.

## 5. Authentication handling & token storage

Full detail in [06-AUTH-FLOW.md](06-AUTH-FLOW.md). Architecturally:

- **`core/auth`** owns cross-cutting session state — a `SessionController` (Riverpod) exposing the current `AuthState` (`unknown` / `authenticated(User)` / `unauthenticated`), read by `AuthInterceptor` (to attach the token) and `route_guards.dart` (to redirect). This is intentionally separate from `features/auth`.
- **`features/auth`** owns the *screens and use cases that change* the session — login, logout, and (once the backend supports it) password reset. `LoginUseCase` succeeding is what pushes a new `AuthState.authenticated` into `SessionController`; the feature doesn't hold session state itself.
- **`core/storage/secure_token_storage.dart`** wraps `flutter_secure_storage` (iOS Keychain / Android Keystore-backed). Stores the bearer token and a client-recorded `issuedAt` timestamp. It does **not** attempt to store a server-declared expiry, because the login/register response contains no `expires_at` field (verified against `AuthController::login`) — see [06-AUTH-FLOW.md](06-AUTH-FLOW.md) for why expiry is handled reactively (on a 401) rather than proactively.

## 6. State management

**Riverpod** (`flutter_riverpod` + `riverpod_generator`), for three reasons specific to this app:

1. It doubles as the dependency-injection mechanism (§7) — no second DI framework (`get_it`, etc.) needed, one dependency graph to reason about.
2. Use cases and repositories are plain classes exposed via `Provider`s, so they're trivially mockable in tests without a widget tree — important given how much of this app's correctness (offline queue reconciliation, dual error-envelope handling) is business logic, not UI.
3. `AsyncNotifier`/`AsyncValue` map cleanly onto the `Result<T, Failure>` pattern already chosen for the data layer, and onto the explicit state machine in [08-UI-UX-FLOW.md §4](08-UI-UX-FLOW.md) (`Idle → Capturing → Validating → Confirmed → Submitting → Result*`) — the Scan screen's state is naturally a sealed class driven by one `Notifier`, not a scatter of booleans.

Each screen gets one controller (`ScanController`, `LoginController`, `DashboardController`, ...) exposing a single sealed view-state type consumed by its page widget. Widgets do not call use cases directly.

## 7. Dependency injection

No separate DI container — **Riverpod providers are the DI graph**, colocated by convention with the thing they provide (a repository's `Provider` lives in its `data/` file, a use case's `Provider` lives in its `domain/usecases/` file). This keeps the dependency chain readable file-by-file: `xApiProvider → xRepositoryProvider → xUseCaseProvider → xControllerProvider`, each depending only on the one below it.

`core/api/api_client.dart` exposes a single `dioProvider`; every feature's `*Api` class is constructed from it via its own provider — there's exactly one Dio instance in the app, configured once in `app/bootstrap.dart`.

Test overrides use Riverpod's `ProviderScope(overrides: [...])` to swap real repositories for fakes — no separate test DI setup required.

## 8. Routing

**`go_router`**, chosen for its declarative `redirect` callback, which is where auth-gating belongs (rather than scattering `if (!loggedIn) push(LoginPage)` checks across screens):

- `app_router.dart` defines all routes from [08-UI-UX-FLOW.md §1](08-UI-UX-FLOW.md)'s navigation map.
- A single top-level `redirect()` reads `SessionController`'s `AuthState`: `unknown` → splash/loading route (waiting on `restoreSessionUsecase`), `unauthenticated` → Login, `authenticated` → allow, unless the target route requires a specific permission the user's cached `permissions` list doesn't contain, in which case redirect to Dashboard with a "not authorized" toast (a UX convenience — see the trust-boundary note in [10-SECURITY.md](10-SECURITY.md): the backend's 403 is still the real gate, this just avoids showing a dead end).
- Route guards for the two role-gated secondary screens (Daily Recap needs `attendance.view`, Leave Permissions needs approval rights) live in `route_guards.dart` as small predicate functions over the cached permission list, not duplicated per-screen.
- No deep-linking requirements identified for v1 (not a consumer-facing app with shareable URLs); `go_router` is still the right choice for the redirect/guard ergonomics alone.

## 9. Scope notes on the proposed feature folders

- **`students/`** is deliberately thin in v1. The backend's student CRUD (`/students/*`) is a back-office, web-appropriate surface per [API-GAP-ANALYSIS.md §4](API-GAP-ANALYSIS.md) — the mobile app doesn't manage student records, it only needs the identity data that `POST /scan/lookup` already returns inline. Don't build a `StudentRepository` wrapping `GET /students`; if a future need arises (e.g. a searchable student picker), design it then against a confirmed requirement rather than pre-building CRUD the app doesn't use.
- **`notifications/`** is not a notification *feed* — no backend endpoint exists for one (`/notifications/*` routes are entirely unimplemented, see [03-API-CONTRACT.md](03-API-CONTRACT.md)), and there is no push-notification infrastructure at all (WhatsApp/Telegram are backend-to-guardian-phone only, not backend-to-app). In v1, `notifications/` holds only small in-app UI affordances derived from data the app already has (e.g. the pending-leave-count badge on Dashboard, sourced from `attendance`'s own `GET /attendance/permissions-pending-count`). **Recommendation**: rename this folder's intent internally to "in-app alerts" to avoid the team building toward a feed that isn't there; don't scaffold a `NotificationRepository` against a non-existent API.
- **`dashboard/`**'s domain entity shape is marked pending in the tree above because `DashboardController`'s exact response payload was not verified during the API audit (see [07-FEATURE-LIST.md, F3](07-FEATURE-LIST.md)) — confirm the real JSON shape from the backend before writing `DashboardSummary`'s fields, rather than guessing and reworking later.
- **No feature folder exists yet for**: grades, fees, report cards, library, or a real notification/announcement feed. Per [API-GAP-ANALYSIS.md §3](API-GAP-ANALYSIS.md), none of these have a backend to build against. Adding empty feature folders for them now would just be speculative structure — create them when their API work is actually scheduled.

## 10. Environment configuration (`core/config`)

- **Flavors**: `development` / `staging` / `production` via Flutter's native flavor mechanism + one `main_<flavor>.dart` entrypoint each, rather than `--dart-define` alone, so flavor-specific app icons/bundle IDs are also supported if the school deployment ever needs side-by-side installs (e.g. a staging build on a tester's phone next to production).
- **`AppConfig`**: one immutable class holding `baseUrl`, `flavorName`, and `enableVerboseLogging`, injected once at `bootstrap.dart` and read via a Riverpod `Provider` — nothing reads `Platform`/`--dart-define` directly outside this one file.
- **No tenant configuration in the app.** Per [01-EXISTING-SYSTEM.md §5](01-EXISTING-SYSTEM.md), tenancy is resolved server-side from the authenticated user; there is no tenant picker, tenant ID field, or `X-Tenant-ID` header anywhere in the mobile app. If a future "single app, multiple schools" requirement emerges, that's a backend + product conversation before it's a mobile config concern.
- **Feature flags** (`feature_flags.dart`): a small, explicit list — e.g. `offlineSyncEnabled` (default true), `biometricAppLockEnabled` (see [10-SECURITY.md](10-SECURITY.md)) — not a general-purpose remote-config system; this app doesn't need one yet.

## 11. Logging (`core/logging`)

Not present in the originally proposed `core/` tree — **added as a recommendation** (see §12) because every other cross-cutting concern (API, auth, errors, storage) has one, and logging is exactly as cross-cutting.

- Thin wrapper (`AppLogger`) over the `logger` package, with named channels (`api`, `auth`, `attendance.sync`, `nav`) so log output can be filtered per subsystem during development.
- **Redaction is mandatory, not optional**: the wrapper strips/masks `password`, `token`, `Authorization` header values, and truncates `unique_code`/`rfid_code` (student/teacher scan credentials — see [10-SECURITY.md](10-SECURITY.md)) before anything is logged, mirroring the phone-number masking convention the backend itself already uses in `FonnteProvider::maskPhoneNumber()`.
- Debug builds log to console at `verbose`; release builds log at `warning`+ to a crash-reporting sink (Sentry or Firebase Crashlytics — pick one; either integrates as a `LogSink` implementation without touching call sites elsewhere in the app).
- Every normalized `Failure` (from `core/errors`) is logged once, at the point it's created, with its type and status code — not re-logged again at every layer it passes through.

## 12. Recommendations

1. **Confirm `DashboardController`'s response shape and every `*Collection` pagination `meta` before writing the corresponding domain entities/DTOs.** Both were flagged as unverified/buggy in the API audit ([01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md), [03-API-CONTRACT.md](03-API-CONTRACT.md)) — building `freezed` models against a guessed shape means rework later.
2. **Add `core/logging` to the proposed structure** (done above) — it's a peer of `api`/`auth`/`errors`/`storage`, not something to bolt on per-feature later.
3. **Use `Drift` (SQLite) for the offline attendance queue**, not just a flat local file or `SharedPreferences` list. The queue needs typed records with a status field (`pending`/`syncing`/`synced`/`failed`), needs to survive app restarts (per [07-FEATURE-LIST.md, F10](07-FEATURE-LIST.md)'s acceptance criteria), and will likely grow to also back the Daily Recap's offline caching later — a real embedded database avoids a rewrite when that happens. `Hive`/`Isar` are reasonable lighter alternatives if the team wants to start simpler and the recap-caching use case is deferred.
4. **Do not let `qr_scanner` depend on `attendance`, or vice versa in the wrong direction.** `qr_scanner` is a pure capture component (camera + text input → a decoded string); `attendance` composes it. This keeps the QR/RFID capture UI reusable if a future feature (e.g. "scan a teacher's own QR to view their profile," see [07-FEATURE-LIST.md, F14](07-FEATURE-LIST.md)) needs the same capture widget without pulling in attendance's submission/queue logic.
5. **Treat every backend message string as display-only, never as a branch condition in app logic.** The backend's business-rule errors (already-checked-in, holiday, geofence, etc.) are Indonesian prose from `ApiController::error()`, not stable error codes (see [09-ERROR-HANDLING.md](09-ERROR-HANDLING.md)). Branch on HTTP status + endpoint, show the message verbatim, and don't parse it for meaning.
6. **Package shortlist** (for the team to confirm, not prescribed as final): `dio`, `flutter_riverpod` + `riverpod_generator`, `go_router`, `freezed` + `json_serializable`, `drift`, `flutter_secure_storage`, `mobile_scanner` (QR capture), `flutter_svg` (rendering the backend's SVG-only QR output, see [01-EXISTING-SYSTEM.md §7](01-EXISTING-SYSTEM.md)), `connectivity_plus` (online/offline transition detection to trigger auto-sync), `logger`, `mocktail` (test doubles).
