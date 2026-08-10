# 09 — Error Handling (Flutter Mobile App)

Design for turning the backend's inconsistent error responses into one predictable model the rest of the app can rely on. No code is implemented here. Builds on [04-MOBILE-ARCHITECTURE.md §4](04-MOBILE-ARCHITECTURE.md) and the backend facts in [01-EXISTING-SYSTEM.md §9-10](01-EXISTING-SYSTEM.md).

## 1. The problem, restated

The backend returns **two different JSON shapes** depending on how a given error originated (confirmed by reading `ApiController`, `bootstrap/app.php`, and several controllers directly — see [01-EXISTING-SYSTEM.md §10](01-EXISTING-SYSTEM.md)):

- **Controller-thrown errors** (e.g. a business-rule rejection in `ScannerController@scan`): `{"success": false, "message": "...", "errors"?: {...}}`.
- **Framework-level errors** (Sanctum auth failures, `FormRequest` validation, route-model-binding misses, unhandled exceptions): Laravel's default shape, `{"message": "...", "errors"?: {...}}` — **no `success` key at all**.

A Flutter HTTP layer written against only one of these shapes will mis-parse the other roughly half the time. This document defines the one place that difference gets absorbed, so nothing above it ever has to know which shape it received.

## 2. Failure taxonomy (`core/errors/failure.dart`)

A single `sealed class Failure` (via `freezed`), exhaustively pattern-matched wherever errors are handled:

| Failure | Typical trigger | Carries |
|---|---|---|
| `NetworkFailure` | No connectivity, DNS failure, connect/receive timeout | — |
| `ServerFailure` | HTTP 5xx | status code, raw message |
| `ValidationFailure` | HTTP 422 where the errors map keys correspond to request field names (a `FormRequest` or inline `$request->validate()` failure) | `Map<String, List<String>> fieldErrors`, top-level `message` |
| `BusinessRuleFailure` | HTTP 422 from `ApiController::error()` where the failure is a domain rule, not a field problem (e.g. "already checked in," "today is a holiday") | `message` only — **no field to bind to** |
| `AuthenticationFailure` | HTTP 401 | — (triggers the session-expiry flow in [06-AUTH-FLOW.md §6](06-AUTH-FLOW.md)) |
| `AuthorizationFailure` | HTTP 403 | `message` (e.g. "This action is unauthorized." or a Spatie role/permission message) |
| `NotFoundFailure` | HTTP 404 | `message` |
| `RateLimitFailure` | HTTP 429 | retry-after hint if the header is present |
| `UnknownFailure` | Anything that doesn't parse as any of the above (defensive catch-all) | raw status + body, for logging |

`ValidationFailure` vs. `BusinessRuleFailure` are both HTTP 422 and both can arrive in *either* JSON envelope — the distinguishing signal is **shape of the `errors` value**, not which envelope wrapped it (see §3).

## 3. Normalization algorithm (`core/api/interceptors/error_interceptor.dart`)

Runs once, in Dio's error interceptor, before any repository sees the failure:

```
on HTTP error response with body B, status S:
  message = B['message'] ?? "Terjadi kesalahan" (generic fallback, should rarely trigger)

  if S == 401:  → AuthenticationFailure()
  if S == 403:  → AuthorizationFailure(message)
  if S == 404:  → NotFoundFailure(message)
  if S == 429:  → RateLimitFailure(message, retryAfter: headers['Retry-After'])
  if S >= 500:  → ServerFailure(status: S, message)

  if S == 422:
    errors = B['errors']
    if errors is a Map<String, List<String>> AND its keys look like request field names
        (i.e. the calling repository passed in the list of field names it submitted,
         so this check is a set-intersection, not a guess)
      → ValidationFailure(fieldErrors: errors, message)
    else
      → BusinessRuleFailure(message)   # covers ApiController::validationError() calls with
                                          non-field errors, and plain error(msg, 422) calls
                                          with no errors key at all

  else (unrecognized status/shape) → UnknownFailure(status: S, raw: B)
```

The `success` key, if present, is **read but never required** — its absence (the default-Laravel shape) does not change which branch fires, because every branch above keys off HTTP status + the shape of `errors`, not the envelope wrapper. This is what makes the two backend shapes irrelevant above this one function.

**Why the field-name intersection check for `ValidationFailure`, instead of just "has an `errors` map = validation"**: `ApiController::validationError()` and inline `$request->validate()` failures both populate `errors`, but so do some hand-built `error($message, 422, $errors)` calls that aren't per-field at all — e.g. `PasswordController::change()`'s wrong-current-password response is `error('Password saat ini tidak valid', 422, ['current_password' => [...]])`, which *is* field-shaped and should be `ValidationFailure`, while `ScannerController@scan`'s business-rule 422s have no `errors` key at all and should be `BusinessRuleFailure`. The intersection check (does `errors`' keys overlap with fields the request actually submitted) handles both correctly without hardcoding a per-endpoint list.

## 4. Repository contract

Every repository method returns `Future<Result<T, Failure>>` — never throws a raw `DioException` or lets one escape past the data layer. Concretely:

```
Result<AttendanceOutcome, Failure> submitAttendance(...)
```

Use cases and presentation-layer controllers pattern-match exhaustively (`switch` over the sealed `Result`/`Failure`), which the Dart compiler enforces — adding a new `Failure` subtype later forces every call site that cares to be updated, rather than silently falling through to a generic handler.

## 5. Known business-rule messages (reference, not exhaustive branching)

These are cataloged in full in [07-FEATURE-LIST.md, F7](07-FEATURE-LIST.md) — restated here only to make the point in §7 concrete: messages like `"Hari ini adalah hari libur"`, `"Siswa sudah melakukan absen masuk pada HH:mm"`, `"Anda berada {n} m dari sekolah, di luar radius maksimal {n} m."` are all `BusinessRuleFailure`s. **The app displays these verbatim; it does not parse them to decide behavior.** If the app ever needs to *behave* differently for "already checked in" vs. "holiday" (e.g., different icon, different next action), that distinction should come from a stable signal — today there isn't one beyond the message text and the calling endpoint/context, which is why recommendation §9.1 below matters.

## 6. UI presentation rules, per screen type

| Screen type | `ValidationFailure` | `BusinessRuleFailure` | `AuthenticationFailure` | `AuthorizationFailure` | `NetworkFailure` |
|---|---|---|---|---|---|
| **Form screens** (Login, Reject-leave-reason) | Bind to the named field(s) inline | Show as a form-level banner | n/a (login) / global handler (elsewhere) | Form-level banner | Inline "check your connection" + retry button |
| **Scan/Submit flow** (F7) | n/a (no user-editable form at this step) | Route to the **Result: Failure** screen (F8), message shown verbatim, full-screen | Global session-expiry handler ([06-AUTH-FLOW.md §6](06-AUTH-FLOW.md)) takes over — this screen doesn't render its own 401 state | Route to Result: Failure (should be rare — a 403 here means the user's own token lost a permission mid-session) | **Not an error at all** — routes to the Offline Queue (F10) instead, per §7 |
| **Read screens** (Dashboard, Daily Recap) | n/a | Inline error state with retry | Global handler | Inline "you don't have access to this" empty state | Inline "check your connection" + retry, or show last-cached data with a stale-data indicator if available |
| **Global** | — | — | `SessionController` forces logout + redirect, see [06-AUTH-FLOW.md](06-AUTH-FLOW.md) | — | — |

The one deliberate special case: **`NetworkFailure` during `POST /scan` is not presented as an error at all.** It's the trigger condition for `EnqueueOfflineScanUseCase` (see [04-MOBILE-ARCHITECTURE.md](04-MOBILE-ARCHITECTURE.md), [07-FEATURE-LIST.md F10](07-FEATURE-LIST.md)). Every other screen's `NetworkFailure` handling is a conventional "you're offline, try again" state.

### 6a. Perilaku offline sebagaimana terimplementasi (2026-08-09)

Diuji langsung dengan mematikan backend, lalu menutup & membuka ulang aplikasi.

| Alur | Saat jaringan mati | Berkas |
|---|---|---|
| **Buka aplikasi (sesi tersimpan)** | Tetap masuk dari cache, tidak dilempar ke login; muncul pemberitahuan "memakai sesi tersimpan". Hanya `401` eksplisit yang mengakhiri sesi. | [auth_controller.dart](../mobile/lib/features/auth/presentation/auth_controller.dart) |
| **Scan QR** | Langsung masuk antrean, ditampilkan sebagai keberhasilan bersyarat — bukan error. | [scan_controller.dart](../mobile/lib/features/attendance/presentation/scan_controller.dart) |
| **Input manual** | Pratinjau identitas gagal (wajar, butuh server) tapi **penyimpanan tetap terbuka** dan masuk antrean. Kode yang benar-benar tak dikenal saat online tetap dicegah. | [manual_input_screen.dart](../mobile/lib/features/attendance/presentation/manual_input_screen.dart) |
| **Absen kelas** | Seluruh sesi (kelas + tanggal + status tiap siswa) masuk antrean tersendiri. | [class_attendance_queue_controller.dart](../mobile/lib/features/attendance/presentation/class_attendance_queue_controller.dart) |
| **Layar lain (Beranda, rekap)** | Tampil dari data terakhir bila ada, atau keadaan kosong yang jujur. | — |

**Dua antrean, bukan satu.** Scan tunggal (`offline_scans`) dan sesi absen kelas
(`offline_class_attendance`) disimpan terpisah di SharedPreferences karena
bentuk datanya berbeda: satu entri absen kelas memuat seisi kelas, tak bisa
dipaksa masuk bentuk scan tunggal. Keduanya bertahan setelah aplikasi ditutup
dan ditampilkan berdampingan di layar Antrean.

**Sinkronisasi berjalan otomatis.** Begitu `backendStatusProvider` berubah dari
`offline` ke `online`, kedua antrean dikirim ulang di belakang layar tanpa
menunggu pengguna membuka layar Antrean. Kegagalan pada jalur otomatis ini
sengaja ditelan — ini bukan aksi yang diminta pengguna, jadi tidak boleh
memunculkan error di layar mana pun; antrean tetap utuh dan dicoba lagi pada
pemulihan berikutnya, atau lewat tombol sinkron manual.

**Kenapa pengulangan aman.** `POST /attendance/students/bulk` memperbarui baris
yang sudah ada untuk tanggal tersebut (diuji: "0 baru, 2 diperbarui"), jadi
entri yang sempat terkirim sebagian tidak menghasilkan duplikat. Entri absen
kelas untuk **kelas + tanggal yang sama** juga saling menimpa di antrean, bukan
menumpuk — mengoreksi absen dua kali saat offline harus menyisakan satu entri
terbaru, bukan dua yang bertentangan.

## 7. Retry policy

| Call | Auto-retry on transient failure? | Rationale |
|---|---|---|
| `GET` requests (dashboard, recap, lookup, bootstrap) | Yes — capped exponential backoff (e.g. 2 attempts, 500ms/1500ms) via `RetryInterceptor` | Idempotent by nature; a flaky connection shouldn't force a manual pull-to-refresh every time |
| `POST /scan` (submit attendance) | **No** | Retrying blindly risks a false "already checked in" on the retry if the first attempt actually landed server-side before the client-perceived timeout — the correct behavior on a network failure here is to queue (F10), not retry, and let the explicit sync step reconcile |
| `POST /scan/sync-offline` | Yes, whole-batch retry is safe | The backend already reports per-item duplicates/failures gracefully inside a 200 response (`data.results[].success`) rather than erroring the batch — re-submitting the same batch (including already-synced items, if the client's local "synced" flag update raced with a crash) is handled correctly server-side by the same duplicate-check logic `POST /scan` uses per item |
| `POST /auth/login`, `/logout` | No | User-initiated, single-shot actions; a failed login should surface immediately, not silently retry with the same (possibly wrong) credentials |
| `POST/PUT` elsewhere (profile update, leave approve/reject) | No | Same reasoning as login — mutating, user-initiated, should fail fast and let the user decide to retry |

## 8. Logging & global fallback

- Every `Failure` is logged exactly once, at the point `error_interceptor.dart` creates it — type, HTTP status, and endpoint path, via `core/logging` (see [04-MOBILE-ARCHITECTURE.md §11](04-MOBILE-ARCHITECTURE.md)). Message bodies are logged, but request bodies containing `password` are not (see [10-SECURITY.md](10-SECURITY.md)).
- **Truly unexpected failures** (a bug, not a modeled `Failure`) are caught at the top of the widget tree via Flutter's `FlutterError.onError` and `PlatformDispatcher.instance.onError`, logged with full stack trace to the crash-reporting sink, and shown a generic "Terjadi kesalahan tak terduga, coba lagi" screen — distinct from the `UnknownFailure` case in §2 (which is still a *parsed*, if unrecognized, API error; this fallback is for exceptions the error-handling system itself didn't anticipate, e.g. a null-pointer bug in the app).

## 9. Recommendations

1. **Ask the backend team to standardize the error envelope** (wrap Sanctum/validation/framework errors in the same `{success, message, errors}` shape `ApiController` already uses elsewhere) — this was already flagged in [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md) recommendation #2. Doing so would let §3's normalization function shrink to a single, trivial case instead of the two-shape branching above; worth doing before or shortly after mobile work starts, since every client (including the existing web app) benefits.
2. **Ask for stable, machine-readable error codes on business-rule 422s** (e.g. `"code": "already_checked_in"` alongside the existing Indonesian `message`). Today, any behavior more sophisticated than "show the message" would require string-matching Indonesian prose, which breaks silently if the copy is ever edited. Until this exists, treat §5's rule as firm: messages are display-only.
3. **Audit pagination `meta` on every `*Collection` resource the app consumes** before building list screens around it (Daily Recap, Leave Permissions) — `StudentCollection` is confirmed to drop real pagination data (see [01-EXISTING-SYSTEM.md §9](01-EXISTING-SYSTEM.md)); assume the same bug elsewhere until checked.
