# School Management System (SMS) Enterprise - Struktur Folder

## Overview

```
sms-app/
├── backend/          # Laravel 12 API
├── frontend/         # React + Inertia.js
├── mobile/           # Flutter App
├── docker/           # Docker Configuration
└── STRUCTURE.md      # Dokumentasi ini
```

---

## Backend (Laravel 12 - Clean Architecture)

```
backend/
├── app/
│   ├── Domain/                    # Business Logic (Core Layer)
│   │   ├── Academic/              # Modul Akademik
│   │   │   ├── Entities/          # Domain Models
│   │   │   ├── Repositories/      # Repository Interfaces
│   │   │   ├── Services/          # Domain Services
│   │   │   ├── Events/            # Domain Events
│   │   │   ├── Listeners/         # Event Listeners
│   │   │   └── Exceptions/        # Domain Exceptions
│   │   ├── Student/               # Modul Siswa
│   │   ├── Teacher/               # Modul Guru
│   │   ├── Staff/                 # Modul Staff
│   │   ├── Finance/               # Modul Keuangan
│   │   ├── Attendance/            # Modul Absensi
│   │   ├── Library/               # Modul Perpustakaan
│   │   ├── Exam/                  # Modul Ujian
│   │   ├── Report/                # Modul Laporan
│   │   ├── Notification/          # Modul Notifikasi
│   │   ├── Setting/               # Modul Pengaturan
│   │   ├── Auth/                  # Modul Autentikasi
│   │   └── Tenant/                # Modul Multi-Tenant
│   │
│   ├── Application/               # Application Layer (Use Cases)
│   │   ├── UseCases/              # Business Use Cases
│   │   │   ├── Academic/
│   │   │   ├── Student/
│   │   │   ├── Teacher/
│   │   │   ├── Staff/
│   │   │   ├── Finance/
│   │   │   ├── Attendance/
│   │   │   ├── Library/
│   │   │   ├── Exam/
│   │   │   ├── Report/
│   │   │   ├── Notification/
│   │   │   ├── Setting/
│   │   │   ├── Auth/
│   │   │   └── Tenant/
│   │   ├── DTOs/                  # Data Transfer Objects
│   │   ├── Contracts/             # Interface Contracts
│   │   └── Traits/                # Shared Traits
│   │
│   ├── Infrastructure/            # Infrastructure Layer
│   │   ├── Persistence/
│   │   │   ├── Eloquent/          # Eloquent Models
│   │   │   │   └── Academic/      # AcademicYear, Semester, GradeLevel,
│   │   │   │                      #   Major, Classroom (HasUuid + BelongsToTenant)
│   │   │   └── Repositories/      # Repository Implementations
│   │   ├── Cache/                 # Redis Cache
│   │   ├── Queue/                 # Job Queue Handlers
│   │   ├── Mail/                  # Mail Services
│   │   ├── Storage/               # File Storage
│   │   └── External/              # External API Integrations
│   │
│   ├── Http/                      # Presentation Layer
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── V1/            # API Version 1
│   │   │       │   ├── Academic/
│   │   │       │   ├── Student/
│   │   │       │   ├── Teacher/
│   │   │       │   ├── Staff/
│   │   │       │   ├── Finance/
│   │   │       │   ├── Attendance/
│   │   │       │   ├── Library/
│   │   │       │   ├── Exam/
│   │   │       │   ├── Report/
│   │   │       │   ├── Notification/
│   │   │       │   ├── Setting/
│   │   │       │   ├── Auth/
│   │   │       │   └── Tenant/
│   │   │       └── V2/            # API Version 2 (Future)
│   │   ├── Requests/              # Form Request Validation
│   │   ├── Resources/             # API Resources
│   │   │   └── Academic/          # MajorResource, GradeLevelResource,
│   │   │                          #   ClassroomResource
│   │   └── Middleware/            # HTTP Middleware
│   │
│   ├── Exports/                   # Excel Exports (maatwebsite/excel)
│   │   └── Academic/              # MajorsExport, MajorsTemplateExport,
│   │                              #   ClassroomsExport, ClassroomsTemplateExport
│   ├── Imports/                   # Excel Imports
│   │   └── Academic/              # MajorsImport, ClassroomsImport
│   │                              #   (upsert by kode, laporan error per baris)
│   │
│   ├── Console/                   # Artisan Commands
│   ├── Exceptions/                # Exception Handlers
│   └── Providers/                 # Service Providers
│
├── database/
│   ├── migrations/                # Database Migrations
│   ├── seeders/                   # Database Seeders
│   └── factories/                 # Model Factories
│
├── config/                        # Configuration Files
├── routes/                        # Route Definitions
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   ├── Feature/
│   │   ├── Api/
│   │   └── Http/
│   └── Integration/
│
├── storage/
│   ├── app/
│   │   ├── public/
│   │   ├── private/
│   │   └── temp/
│   ├── framework/
│   └── logs/
│
├── resources/
│   ├── views/
│   └── lang/
│
├── bootstrap/cache/
└── public/
```

---

## Frontend (React + Inertia.js + shadcn/ui)

> Catatan: frontend web saat ini terintegrasi di dalam Laravel (`backend/resources/js`),
> dirender via Inertia.js — bukan folder `frontend/` terpisah.

```
backend/resources/js/
├── app.tsx                        # Bootstrap Inertia + React Query + init tema
│                                  #   (fallback ke pages/Error.tsx jika page belum ada)
├── vite-env.d.ts                  # Deklarasi tipe vite/client
│
├── components/
│   └── ui/                        # shadcn/ui Components
│       ├── alert.tsx
│       ├── alert-dialog.tsx
│       ├── avatar.tsx
│       ├── badge.tsx
│       ├── button.tsx
│       ├── card.tsx
│       ├── checkbox.tsx
│       ├── collapsible.tsx        # Expand/collapse submenu sidebar
│       ├── dialog.tsx
│       ├── dropdown-menu.tsx
│       ├── input.tsx
│       ├── label.tsx
│       ├── progress.tsx
│       ├── select.tsx
│       ├── separator.tsx
│       ├── sheet.tsx
│       ├── sidebar.tsx            # Sidebar collapsible (icon-only saat ditutup)
│       ├── sonner.tsx
│       ├── switch.tsx
│       ├── table.tsx
│       ├── tabs.tsx
│       ├── textarea.tsx
│       └── tooltip.tsx
│
├── pages/                         # Page Components (Inertia)
│   ├── Welcome.tsx
│   ├── Dashboard.tsx
│   ├── Error.tsx                  # Halaman error elegan (401/403/404/419/429/500/503)
│   ├── auth/                      # Login, Register
│   ├── students/                  # Index, Create
│   ├── attendance/                # students, teachers, permissions, holidays,
│   │                              #   qr-codes, reports, settings
│   ├── settings/                  # Majors.tsx (Jurusan), ClassRooms.tsx (Kelas)
│   │                              #   — CRUD + Import (dengan template) + Export
│   └── scanner/                   # QR Scanner (kiosk)
│
├── layouts/
│   └── MainLayout.tsx             # Sidebar (logo sekolah, menu collapsible, footer
│                                  #   user+logout), navbar (dropdown tema/user/logout),
│                                  #   footer section
│
├── hooks/                         # use-mobile, useOfflineQueue, useOfflineRoster
├── stores/                        # State Management (Zustand)
├── services/                      # api.ts, attendance.ts (API layer)
├── types/                         # index.ts, attendance.ts (TypeScript Types)
└── lib/                           # Utility Functions (cn, dll)
```

---

## Mobile (Flutter - Clean Architecture)

```
mobile/
├── lib/
│   ├── core/                      # Core Utilities
│   │   ├── api/                   # API Client
│   │   ├── config/                # App Configuration
│   │   ├── constants/             # Constants
│   │   ├── errors/                # Error Handling
│   │   ├── network/               # Network Utils
│   │   ├── storage/               # Local Storage
│   │   └── utils/                 # Utilities
│   │
│   ├── features/                  # Feature Modules
│   │   ├── auth/
│   │   │   ├── data/              # Data Layer
│   │   │   ├── domain/            # Domain Layer
│   │   │   └── presentation/      # UI Layer
│   │   ├── dashboard/
│   │   ├── academic/
│   │   ├── students/
│   │   ├── teachers/
│   │   ├── attendance/
│   │   ├── finance/
│   │   ├── notifications/
│   │   ├── profile/
│   │   └── settings/
│   │
│   └── shared/                    # Shared Resources
│       ├── widgets/               # Reusable Widgets
│       ├── themes/                # App Themes
│       ├── extensions/            # Dart Extensions
│       └── models/                # Shared Models
```

---

## Docker

```
docker/
├── nginx/
│   └── conf.d/                    # Nginx Configuration
├── php/                           # PHP-FPM Configuration
├── postgres/                      # PostgreSQL Init Scripts
└── redis/                         # Redis Configuration
```

---

## Penjelasan Arsitektur

### Clean Architecture Layers

1. **Domain Layer** (Innermost)
   - Berisi business logic murni
   - Tidak bergantung pada framework
   - Entities, Repository Interfaces, Domain Services

2. **Application Layer**
   - Use Cases / Interactors
   - Orchestrates Domain Layer
   - DTOs untuk transfer data

3. **Infrastructure Layer**
   - Implementasi Repository
   - Eloquent Models
   - External Services (Redis, Mail, etc.)

4. **Presentation Layer** (Http)
   - Controllers
   - Request Validation
   - API Resources

### Modul Bisnis

| Modul | Fungsi |
|-------|--------|
| Academic | Kurikulum, Mata Pelajaran, Jadwal |
| Student | Data Siswa, Enrollment |
| Teacher | Data Guru, Penugasan |
| Staff | Data Karyawan |
| Finance | SPP, Pembayaran, Invoice |
| Attendance | Absensi Siswa & Guru |
| Library | Perpustakaan, Peminjaman |
| Exam | Ujian, Nilai, Remedial |
| Report | Rapor, Laporan |
| Notification | Push Notification, Email |
| Setting | Konfigurasi Sistem |
| Auth | Authentication, Authorization |
| Tenant | Multi-Tenant Management |

---

## Naming Convention

### Backend (Laravel/PHP)
- **Controllers**: `StudentController.php` (PascalCase, singular)
- **Models**: `Student.php` (PascalCase, singular)
- **Migrations**: `2024_01_01_000000_create_students_table.php`
- **Requests**: `StoreStudentRequest.php`, `UpdateStudentRequest.php`
- **Resources**: `StudentResource.php`, `StudentCollection.php`
- **Services**: `StudentService.php`
- **Repositories**: `StudentRepository.php`, `StudentRepositoryInterface.php`

### Frontend (React/TypeScript)
- **Components**: `StudentCard.tsx` (PascalCase)
- **Hooks**: `useStudents.ts` (camelCase with 'use' prefix)
- **Types**: `Student.ts` (PascalCase)
- **Utils**: `formatDate.ts` (camelCase)

### Mobile (Flutter/Dart)
- **Files**: `student_repository.dart` (snake_case)
- **Classes**: `StudentRepository` (PascalCase)
- **Variables**: `studentName` (camelCase)
