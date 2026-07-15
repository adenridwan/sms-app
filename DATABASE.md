# SMS Enterprise - Database Schema

## Overview

Database menggunakan **PostgreSQL 16** dengan total **70+ tabel** yang terbagi dalam 13 modul.

---

## Entity Relationship Diagram (Simplified)

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   TENANTS   │────<│    USERS    │────<│  PROFILES   │
└─────────────┘     └─────────────┘     └─────────────┘
       │                   │
       │         ┌─────────┼─────────┐
       │         │         │         │
       ▼         ▼         ▼         ▼
┌───────────┐ ┌───────┐ ┌───────┐ ┌───────┐
│ STUDENTS  │ │TEACHER│ │ STAFF │ │PARENTS│
└───────────┘ └───────┘ └───────┘ └───────┘
       │          │
       │          │
       ▼          ▼
┌─────────────────────────────────────────────────────┐
│                    ACADEMIC                          │
│  (Years, Semesters, Curricula, Subjects, Schedules) │
└─────────────────────────────────────────────────────┘
       │
       ├──────────────────────────────────────────────┐
       │                    │                          │
       ▼                    ▼                          ▼
┌─────────────┐     ┌─────────────┐           ┌─────────────┐
│ ATTENDANCE  │     │    EXAMS    │           │   FINANCE   │
│             │     │   GRADES    │           │  PAYMENTS   │
└─────────────┘     └─────────────┘           └─────────────┘
                           │
                           ▼
                    ┌─────────────┐
                    │   REPORTS   │
                    │   (RAPOR)   │
                    └─────────────┘
```

---

## Modules & Tables

### 1. Tenant (Multi-Tenant)
| Table | Description |
|-------|-------------|
| `tenants` | Data sekolah/institusi |
| `tenant_domains` | Domain per tenant |

### 2. Authentication & Users
| Table | Description |
|-------|-------------|
| `users` | Akun pengguna |
| `user_profiles` | Profil lengkap pengguna |
| `password_reset_tokens` | Token reset password |
| `sessions` | Session management |
| `personal_access_tokens` | API tokens (Sanctum) |

### 3. Authorization (Spatie Permission)
| Table | Description |
|-------|-------------|
| `permissions` | Daftar permission |
| `roles` | Daftar role |
| `model_has_permissions` | User-Permission pivot |
| `model_has_roles` | User-Role pivot |
| `role_has_permissions` | Role-Permission pivot |

### 4. Academic
| Table | Description |
|-------|-------------|
| `academic_years` | Tahun ajaran |
| `semesters` | Semester |
| `curricula` | Kurikulum |
| `grade_levels` | Tingkat kelas (X, XI, XII) |
| `majors` | Jurusan (IPA, IPS, dll) |
| `classrooms` | Kelas/Rombel |
| `subjects` | Mata pelajaran |
| `subject_grade_levels` | Mapel per tingkat |
| `time_slots` | Jam pelajaran |
| `schedules` | Jadwal pelajaran |

### 5. Students
| Table | Description |
|-------|-------------|
| `students` | Data siswa |
| `student_guardians` | Orang tua/wali |
| `student_enrollments` | Pendaftaran per tahun |
| `student_documents` | Dokumen siswa |
| `student_achievements` | Prestasi siswa |
| `student_violations` | Pelanggaran/tata tertib |

### 6. Teachers & Staff
| Table | Description |
|-------|-------------|
| `departments` | Bidang/unit kerja |
| `positions` | Jabatan |
| `teachers` | Data guru |
| `teacher_subjects` | Mapel yang diajar |
| `teacher_positions` | Riwayat jabatan |
| `staff` | Data karyawan non-guru |
| `leave_requests` | Pengajuan cuti |

### 7. Attendance
| Table | Description |
|-------|-------------|
| `attendance_settings` | Pengaturan absensi |
| `student_attendances` | Absensi harian siswa |
| `subject_attendances` | Absensi per mapel |
| `employee_attendances` | Absensi guru/staff |
| `attendance_summaries` | Rekap absensi |

### 8. Exams & Grades
| Table | Description |
|-------|-------------|
| `exam_types` | Jenis ujian (UH, UTS, UAS) |
| `exams` | Data ujian |
| `exam_scores` | Nilai ujian |
| `remedials` | Ujian remedial |
| `grade_components` | Komponen nilai |
| `student_grades` | Nilai per komponen |
| `final_grades` | Nilai akhir/rapor |

### 9. Finance
| Table | Description |
|-------|-------------|
| `fee_types` | Jenis biaya (SPP, dll) |
| `fee_structures` | Struktur biaya |
| `student_fees` | Tagihan siswa |
| `discounts` | Potongan/beasiswa |
| `student_discounts` | Potongan per siswa |
| `payment_methods` | Metode pembayaran |
| `payments` | Transaksi pembayaran |
| `payment_items` | Detail pembayaran |
| `financial_reports` | Laporan keuangan |

### 10. Library
| Table | Description |
|-------|-------------|
| `book_categories` | Kategori buku |
| `book_shelves` | Rak buku |
| `publishers` | Penerbit |
| `authors` | Penulis |
| `books` | Data buku |
| `book_authors` | Buku-Penulis pivot |
| `book_copies` | Eksemplar buku |
| `library_members` | Anggota perpustakaan |
| `book_loans` | Peminjaman buku |
| `book_reservations` | Reservasi buku |
| `library_settings` | Pengaturan perpus |

### 11. Notifications
| Table | Description |
|-------|-------------|
| `notification_templates` | Template notifikasi |
| `notifications` | Notifikasi in-app |
| `notification_logs` | Log pengiriman |
| `device_tokens` | Token push notification |
| `announcements` | Pengumuman |
| `announcement_reads` | Pembacaan pengumuman |

### 12. Reports
| Table | Description |
|-------|-------------|
| `report_cards` | Rapor siswa |
| `report_card_extracurriculars` | Nilai ekskul |
| `report_card_characters` | Penilaian karakter |
| `generated_reports` | Laporan yang digenerate |

### 13. System
| Table | Description |
|-------|-------------|
| `settings` | Konfigurasi sistem |
| `audit_logs` | Log audit |
| `activity_logs` | Log aktivitas |
| `failed_jobs` | Job yang gagal |
| `jobs` | Queue jobs |
| `job_batches` | Batch jobs |
| `cache` | Cache database |
| `cache_locks` | Cache locks |
| `file_uploads` | File yang diupload |
| `health_check_results` | Health check |

---

## Key Design Decisions

### 1. UUID Primary Keys
Semua tabel utama menggunakan UUID untuk:
- Multi-tenant ready
- Distributed system friendly
- Tidak bisa ditebak (security)

### 2. Multi-Tenant
- Setiap tabel memiliki `tenant_id`
- Foreign key cascade delete
- Index pada `tenant_id` untuk performa

### 3. Soft Deletes
Tabel penting menggunakan soft delete untuk:
- Audit trail
- Recovery data
- Compliance

### 4. Timestamps
Semua tabel memiliki `created_at` dan `updated_at`

### 5. JSON Columns
Digunakan untuk data fleksibel:
- `settings`, `preferences`
- `metadata`, `additional_info`
- `configuration`

---

## Indexes Strategy

```sql
-- Composite indexes for common queries
INDEX (tenant_id, status)
INDEX (tenant_id, created_at)
INDEX (student_id, semester_id)
INDEX (classroom_id, attendance_date)

-- Unique constraints
UNIQUE (tenant_id, code)
UNIQUE (tenant_id, email)
UNIQUE (student_id, academic_year_id)
```

---

## Migration Files

| File | Module |
|------|--------|
| `000001_create_tenants_table` | Tenant |
| `000002_create_users_table` | Auth |
| `000003_create_permission_tables` | Authorization |
| `000004_create_academic_tables` | Academic |
| `000005_create_student_tables` | Students |
| `000006_create_teacher_staff_tables` | Teachers & Staff |
| `000007_create_attendance_tables` | Attendance |
| `000008_create_exam_grade_tables` | Exams & Grades |
| `000009_create_finance_tables` | Finance |
| `000010_create_library_tables` | Library |
| `000011_create_notification_tables` | Notifications |
| `000012_create_report_tables` | Reports |
| `000013_create_system_tables` | System |

---

## Running Migrations

```bash
# Via Docker
make migrate

# Or directly
docker-compose exec php php artisan migrate

# Fresh migration with seeders
make fresh
```
