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
│   │   └── Middleware/            # HTTP Middleware
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

```
frontend/
├── src/
│   ├── components/
│   │   ├── ui/                    # shadcn/ui Components
│   │   │   ├── button/
│   │   │   ├── card/
│   │   │   ├── dialog/
│   │   │   ├── dropdown/
│   │   │   ├── input/
│   │   │   ├── select/
│   │   │   ├── table/
│   │   │   ├── tabs/
│   │   │   ├── toast/
│   │   │   ├── avatar/
│   │   │   ├── badge/
│   │   │   ├── checkbox/
│   │   │   ├── radio/
│   │   │   ├── switch/
│   │   │   ├── tooltip/
│   │   │   ├── popover/
│   │   │   ├── command/
│   │   │   ├── calendar/
│   │   │   └── sidebar/
│   │   ├── common/                # Shared Components
│   │   ├── forms/                 # Form Components
│   │   ├── tables/                # Table Components
│   │   ├── charts/                # Chart Components
│   │   ├── navigation/            # Navigation Components
│   │   ├── modals/                # Modal Components
│   │   └── notifications/         # Notification Components
│   │
│   ├── pages/                     # Page Components (Inertia)
│   │   ├── dashboard/
│   │   ├── academic/
│   │   ├── students/
│   │   ├── teachers/
│   │   ├── staff/
│   │   ├── finance/
│   │   ├── attendance/
│   │   ├── library/
│   │   ├── exams/
│   │   ├── reports/
│   │   ├── settings/
│   │   └── auth/
│   │
│   ├── layouts/                   # Layout Components
│   │   ├── main/                  # Main Dashboard Layout
│   │   ├── auth/                  # Auth Layout
│   │   └── error/                 # Error Pages Layout
│   │
│   ├── hooks/                     # Custom React Hooks
│   ├── stores/                    # State Management
│   ├── services/
│   │   └── api/                   # API Service Layer
│   ├── utils/                     # Utility Functions
│   ├── types/                     # TypeScript Types
│   ├── styles/                    # Global Styles
│   └── assets/
│       ├── images/
│       ├── icons/
│       └── fonts/
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
