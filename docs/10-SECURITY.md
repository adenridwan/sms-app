# 10 — Security (Flutter Mobile App)

Security posture and hardening decisions for the mobile client. No code is implemented here. This complements, and does not replace, the backend's own authorization model (Sanctum + Spatie roles/policies — see [01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md)).

## 1. Trust boundary: the client is not the enforcement point

**Every role/permission check the app performs client-side is a UX convenience, never a security control.** The backend already enforces authorization independently (route middleware, Policies, inline `can()` checks — see [01-EXISTING-SYSTEM.md §10](01-EXISTING-SYSTEM.md)); the app's job is to *reflect* that so users aren't shown dead-end actions, not to *implement* it a second time.

Concretely: if the Dashboard hides "Approve leave" for a user without `attendance.manage`, that's because rendering the button would be pointless, not because the button is dangerous to leave visible. If a rooted device, a modified APK, or a stale cached permission list causes the button to render anyway, the resulting `POST /attendance/permissions/{id}/approve` call still gets a `403` from the real backend check — the app must handle that gracefully (a normal `AuthorizationFailure`, per [09-ERROR-HANDLING.md](09-ERROR-HANDLING.md)), not treat it as an app bug. Never assume "the UI wouldn't have let them get here" as a substitute for handling the backend's actual response.

## 2. Token storage & transmission

- The Sanctum bearer token is stored **only** in `flutter_secure_storage` (iOS Keychain, Android Keystore-backed), never in `SharedPreferences`, never in a plain file, never in app state that could be dumped via a debug snapshot.
- Transmitted **only** as `Authorization: Bearer <token>` over HTTPS — never as a query parameter (query strings routinely end up in server access logs, proxy logs, and browser/webview history; headers do not).
- Never logged. `core/logging`'s redaction rules (see [04-MOBILE-ARCHITECTURE.md §11](04-MOBILE-ARCHITECTURE.md)) strip the `Authorization` header and any `token` field from request/response logging unconditionally, including in debug builds — a token leaked into a developer's local log file is still a leaked token.
- Cleared immediately on logout and on any detected `401` (session-expiry flow, [06-AUTH-FLOW.md §6](06-AUTH-FLOW.md)) — never left in storage "just in case."

## 3. Transport security

- The app's `AppConfig` base URL is HTTPS-only for staging/production flavors; a cleartext `http://` base URL should be rejected at build time for those flavors (a lint/assert in `bootstrap.dart`, not just a convention), with `http://` permitted only for a local-development flavor pointed at a dev machine.
- **Certificate pinning**: recommended for production, but with an explicit operational trade-off called out rather than a blanket mandate. Pinning the leaf/intermediate certificate breaks the app silently on the school's next TLS cert rotation until an app update ships — for a small-team backend, that's a real support-burden risk, not just a theoretical one. **Recommendation**: pin the CA (not the leaf) if a CA that's already fixed/known is in use (e.g., Let's Encrypt's root, or the hosting provider's managed cert authority), which survives routine cert renewal; or defer pinning entirely for v1 and rely on OS trust-store validation + HSTS (if enabled server-side), revisiting pinning once the backend's TLS setup and rotation cadence are confirmed. Don't pin the leaf certificate without a very solid renewal/app-update pipeline behind it.

## 4. Session lifetime & revocation gaps (inherited from the backend)

Per [06-AUTH-FLOW.md §1](06-AUTH-FLOW.md), the backend has no refresh token, no device-session listing, and tokens are named identically (`'auth_token'`) regardless of which device logged in. Security implications specific to a **shared scanning device** (a school-gate kiosk phone, not a personal device) are worth stating plainly:

- A token issued to a shared device remains valid for up to 7 days with **no way for an admin to remotely revoke just that device's access** short of that specific token being deleted (which today requires knowing which token that is — Sanctum supports per-token revocation, but no endpoint currently exposes a device's own token ID for self-service revocation).
- **Mitigations available today, without a backend change**:
  1. App-level logout (`POST /auth/logout`) revokes the *current* token — train staff to log out at end-of-shift on shared devices, not just lock the screen.
  2. **Recommend an app-level PIN/biometric lock** (`local_auth` package) layered on top of the valid Sanctum session — even with a valid 7-day token stored, a stolen-but-screen-locked device shouldn't grant instant access to the attendance app the moment the OS lock screen is bypassed. This is a meaningful defense-in-depth control given the token's multi-day validity and the lack of remote revocation, and directly relevant here since the primary users (Administrator/Teacher/Staff) plausibly share scanning hardware at a school gate.
- **Mitigation that requires a backend change** (repeated from [06-AUTH-FLOW.md §8](06-AUTH-FLOW.md) because it's a security recommendation as much as a UX one): device-scoped token naming + a self-service "list/revoke my active sessions" endpoint.

## 5. Sensitive data at rest on the device

The app persists real student/staff PII locally, not just a token. Inventory and treatment:

| Data | Where | Sensitivity | Treatment |
|---|---|---|---|
| Bearer token | `flutter_secure_storage` | High (bearer credential) | OS-encrypted keystore, cleared on logout/expiry |
| Cached user profile (name, roles, phone) | `flutter_secure_storage` or encrypted local DB | Medium | Cleared on logout |
| Offline attendance queue (student name, NIS, scan timestamp, GPS if captured) | Local database (`Drift`, per [04-MOBILE-ARCHITECTURE.md §12](04-MOBILE-ARCHITECTURE.md)) | **High — real minors' PII, potentially including location, persisted for as long as the device stays offline** | **Recommend encrypting the local database at rest** (e.g. SQLCipher-backed Drift, or the local DB library's built-in encryption) rather than a plaintext SQLite file. This queue can realistically hold data for hours in a genuinely offline school (see [02-MOBILE-REQUIREMENTS.md](02-MOBILE-REQUIREMENTS.md)'s offline-first requirement) — long enough that "it's usually synced quickly" isn't a sufficient argument against encrypting it. |
| Cached recap/dashboard data (if offline caching is added later) | Local database | Medium | Same local DB encryption applies; clear on logout |

## 6. Geolocation privacy

- Location is requested **only** when the tenant's `AttendanceSetting.require_location` is true (learned from `GET /scan/bootstrap`, per [07-FEATURE-LIST.md, F7](07-FEATURE-LIST.md)) — never requested unconditionally at app startup "just in case."
- The permission request must carry a clear, specific purpose string (e.g., "Used to confirm you're at the school when recording attendance") — not a generic OS-default prompt.
- The app reads location **once, at the moment of a scan submission** — it does not run continuous/background location tracking. There is no product requirement for tracking a device's movement, only for confirming its position at a single instant, and the implementation should reflect that narrow scope (avoid `Geolocator`-style continuous position streams; use a single one-shot fix).
- Coordinates are transmitted to `POST /scan` and, if queued offline, held in the local queue only until sync succeeds — not retained afterward as a standalone location history.

## 7. QR/RFID code sensitivity

`unique_code` and `rfid_code` function as **bearer credentials for attendance actions** — anyone who has the physical card or a photo of the printed QR code can trigger a check-in/check-out for that student, by backend design (this is inherent to the system, not something the mobile app introduces or can fix). Given that:

- The app should not log, cache, or display a scanned code beyond the immediate validate→submit session — once a scan is processed (or queued), the raw code value has no further use in the UI and shouldn't linger in memory/logs longer than that.
- The **validate/confirm step (F6 in [07-FEATURE-LIST.md](07-FEATURE-LIST.md))** — showing the resolved name/NIS/classroom before committing the attendance action — is a deliberate, valuable mitigation already designed into the flow: it gives the human operator a chance to notice a misread/wrong code before it's submitted. **Do not remove or auto-skip this confirmation step** for the sake of scan speed; it's a meaningful check against both accidental misfires and a level of casual misuse (e.g., someone scanning a code that isn't the student in front of them).

## 8. Client-side authorization is advisory (restated concretely)

Every gated action in the app must independently handle a `403` from the backend as a normal, expected outcome — not an exceptional/crash-worthy state — regardless of whether the UI "should have" prevented reaching that action. This matters specifically because:

- Cached `permissions` (from `/auth/me`, see [06-AUTH-FLOW.md §7](06-AUTH-FLOW.md)) can become stale mid-session if an admin changes the user's role from the web console — the app won't know until its next `/auth/me` refresh, but the backend will enforce the new permission set immediately.
- A tampered client (rooted device, patched APK) could bypass the app's own gating entirely — the backend's checks are what actually protects the data in that scenario, and the app's error handling needs to degrade gracefully (show "you don't have access," not crash) when that happens.

## 9. Input handling

- The Ref ID manual-entry field (F5) enforces the same `max:100` length the backend validates (`ScannerController@scan`/`@lookup`) client-side too, purely to avoid pointless oversized payloads — this is a UX/bandwidth courtesy, not a security control (the backend validates independently regardless).
- No raw SQL, no WebView bridge, and no dynamic code execution anywhere in the app — there's no architectural surface here for injection-style attacks; Dio + Laravel's Eloquent parameter binding handle the actual data path safely. Keep it that way: don't introduce a WebView-based screen (e.g., for viewing a PDF report) without specifically re-reviewing what it's given access to.

## 10. App hardening (release builds)

- Build with obfuscation and split debug info (`flutter build apk --obfuscate --split-debug-info=<dir>`, and the iOS equivalent) so a decompiled release binary doesn't trivially reveal class/method names describing the attendance/auth logic.
- Disable verbose/debug logging in release builds (see [04-MOBILE-ARCHITECTURE.md §11](04-MOBILE-ARCHITECTURE.md) — release logs at `warning`+ only, to the crash-reporting sink, never to console).
- **Recommend `FLAG_SECURE`** (Android) / the iOS equivalent screenshot/screen-recording block on the Scan and Result screens specifically, since they display real student names and attendance status — prevents these from being captured in the OS's recent-apps screenshot thumbnail or an unintended screen recording. Not necessarily needed app-wide (e.g. the Login screen doesn't need it), but worth it exactly where student PII is on screen.
- Request only the permissions the app actually uses (camera, location — conditionally, per §6). No contacts, no broad storage access, no background-location entitlement.

## 11. Dependency hygiene

- Pin dependency versions in `pubspec.lock` (committed), don't float on `^` ranges for security-relevant packages (`dio`, `flutter_secure_storage`, the local DB library) without a deliberate review of the changelog.
- Periodically check `pub audit`/advisory feeds for the chosen packages — `mobile_scanner` (camera/native platform code) and `dio` (network layer) are the two with the largest attack-surface-relevant native/parsing footprint in this app's dependency tree.

## 12. Data protection / compliance note

This app handles **real personal data belonging to minors** (student names, NIS, attendance timestamps, and conditionally GPS location) on a mobile device, in a country (Indonesia) with an active data protection law (UU PDP). This document does not attempt a legal compliance analysis — that's outside the scope of a technical architecture doc, and should be reviewed with whoever owns compliance for the school/product — but flags it explicitly so it isn't missed:

- **Recommend the product owner define a data retention policy** for the offline queue and any locally cached recap data (how long can synced-but-not-yet-purged records sit on a device?), rather than leaving it to an engineering default.
- The existing public, unauthenticated NIS-only attendance-lookup endpoints ([01-EXISTING-SYSTEM.md §3](01-EXISTING-SYSTEM.md), [API-GAP-ANALYSIS.md §2](API-GAP-ANALYSIS.md)) already represent a pre-existing exposure on the web side; the mobile app's stricter, authenticated-and-guardian-scoped equivalent (recommended in the gap analysis) is the right pattern to follow here rather than reusing the public endpoints inside an authenticated app context.

## 13. Recommendations summary

1. App-level PIN/biometric lock (`local_auth`) as defense-in-depth given the backend's multi-day, non-revocable token lifetime and the shared-device use case (§4).
2. Encrypt the local offline-queue database at rest — it holds real student PII, potentially for hours (§5).
3. Pin the CA, not the leaf certificate, if pinning is adopted for production — or defer pinning and revisit once the backend's TLS rotation cadence is known (§3).
4. Enable `FLAG_SECURE`/screenshot-blocking on Scan and Result screens specifically (§10).
5. Preserve the validate/confirm step (F6) as a deliberate misfire-mitigation — don't optimize it away for scan speed (§7).
6. Push the two backend-side asks already raised in [06-AUTH-FLOW.md §8](06-AUTH-FLOW.md) (device-scoped tokens + self-service session revocation) — they're security recommendations as much as UX ones.
7. Get a data-retention policy for on-device PII from the product/compliance owner before launch, not after (§12).
