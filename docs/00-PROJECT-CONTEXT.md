# 00 — Project Context

## What this repository is

`sms-app` is a Laravel 12 School Management System (SMS) currently serving a **web admin panel** (Inertia.js + React, server-rendered from the same Laravel app) and a **REST API** consumed by that same web frontend. Per `STRUCTURE.md`, the long-term shape of the repo is:

```
sms-app/
├── backend/    # Laravel 12 API + Inertia web app  (EXISTS)
├── frontend/   # planned — currently the Inertia/React pages live inside backend/resources
├── mobile/     # planned — Flutter app (DOES NOT EXIST YET)
└── docker/     # docker-compose for local dev (EXISTS)
```

Only `backend/` and `docker/` exist today. There is no `frontend/` or `mobile/` directory in the repo — the React admin UI is embedded in `backend/resources`. **The Flutter mobile app has not been started.** This document set exists to assess whether the current REST API (built for the web admin panel) is ready to serve as the backend for that future mobile app, before any mobile-facing work begins.

## Relationship to other docs in this repo

The repo root already contains several planning/spec documents. They are **not all describing this codebase**:

- `APPLICATION_SPEC.md` documents the business logic of a **different, older application** (a Filament 3 + Livewire attendance system, table prefix `tb_*`, roles `superadmin/admin/kepsek/scanner/guru`). It is explicitly framed as a technology-independent reference extracted from that legacy app *for rebuilding in this repo* — it is prior art, not a description of `sms-app`'s current code. Do not cite its role/permission names or schema as if they apply here.
- `STRUCTURE.md`, `DATABASE.md`, `ROLE-ACCESS-PLAN.md`, `ATTENDANCE-PLAN.md`, `TEACHER-MODULE-PLAN.md`, `TESTCASE.md`, `CHANGELOG.md` describe **this** codebase, but `STRUCTURE.md` and `DATABASE.md` in particular describe the *intended* clean-architecture layout (full `Domain/{Module}/{Entities,Repositories,Services,Events}` and `Application/UseCases/*` trees for every module, 70+ tables across 13 modules). The actual code only partially implements this vision — see [01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md) for what is real vs. aspirational.
- `ROLE-ACCESS-PLAN.md` is a live, actively-updated execution log for this codebase's role/access work (dated entries up to 2026-07-18) and is a trustworthy source for *why* certain scoping rules (`scopeVisibleTo`, `teachingClassroomIds`) exist.

## Tech stack

| Layer | Technology |
|---|---|
| Language / runtime | PHP 8.4 |
| Framework | Laravel 12 (`laravel/framework ^12.0`) |
| Web UI | Inertia.js v2 (`inertiajs/inertia-laravel ^2.0`) + React (rendered server-side routes, client SPA-like pages) |
| Auth | Laravel Sanctum v4 (API tokens) + session/cookie auth for the Inertia web app |
| Authorization | `spatie/laravel-permission` v6 (roles + permissions), plus one Eloquent Policy and ad-hoc `user_type` checks |
| Database | PostgreSQL (confirmed by `ilike` usage in controllers and `DATABASE.md`) |
| Queue / cache | Redis (`predis/predis`), Laravel Horizon for queue monitoring |
| Multi-tenancy | Custom, homegrown (`tenant_id` column + `BelongsToTenant` trait + global scope). `stancl/tenancy` is a composer dependency but **is not wired up** (no tenancy config file, no `TenancyServiceProvider` registration found) — treat it as unused/vestigial, not the actual tenancy mechanism. |
| File/media | `spatie/laravel-medialibrary`, `intervention/image-laravel` |
| Import/export | `maatwebsite/excel`, `barryvdh/laravel-dompdf` |
| QR codes | `simplesoftwareio/simple-qrcode` |
| Messaging integrations | Custom providers for Fonnte / Wablas (WhatsApp) and Telegram (see [01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md)) |
| Testing | Pest 3 (`pestphp/pest`), PHPUnit 11 |

## Architecture style

The codebase is a **hybrid**, not a consistently-applied clean architecture:

- **Newer / actively maintained modules** (Attendance, Auth, Notification, Tenant, Academic, Student, Teacher, Staff persistence) live under a DDD-ish layout:
  - `app/Domain/{Module}/{Services,Events,Listeners,Enums,Rules,Jobs}` — business logic
  - `app/Infrastructure/Persistence/Eloquent/{Module}/*.php` — actual Eloquent models
  - `app/Infrastructure/External/Messaging/*` — third-party API adapters
  - `app/Application/Contracts/RepositoryInterface.php` + `app/Infrastructure/Persistence/Repositories/BaseRepository.php` — a generic repository scaffold exists but **is not used per-entity**; controllers query Eloquent models directly in almost all cases.
- **Legacy-compatibility shims**: `app/Models/User.php` and `app/Models/Tenant.php` are thin subclasses/wrappers kept so old code paths (`App\Models\User`) keep working while the real model lives in `App\Infrastructure\Persistence\Eloquent\Auth\User`.
- **Controllers**: `app/Http/Controllers/Api/V1/{Module}/*Controller.php`, all extending `App\Http\Controllers\Api\ApiController` (a shared JSON-envelope helper base class), plus one Inertia `app/Http/Controllers/Web/PageController.php` that renders every web page.
- **Modules that exist only as routes and migrations, with no controller/service/model implementation**: Exam/Grade, Library, Report, Notification (announcements), Setting, most of Finance, most of Staff, most of Academic (Curriculum/Subject/TimeSlot), Student Guardian/Enrollment as standalone resources, Tenant management, and most of Admin (Role/Permission/AuditLog/ActivityLog/System). See [01-EXISTING-SYSTEM.md](01-EXISTING-SYSTEM.md) §"Implemented vs. declared-only" for the full list — **this is the single most important fact for scoping any new client**, mobile or otherwise.

## Why this analysis exists

The user is preparing to build a mobile client against this API. Objective: confirm what is genuinely usable today, what needs shape changes, what's simply missing, and what must stay web/admin-only — before writing a line of mobile code. See [API-GAP-ANALYSIS.md](API-GAP-ANALYSIS.md) for the categorized breakdown and recommendations.
