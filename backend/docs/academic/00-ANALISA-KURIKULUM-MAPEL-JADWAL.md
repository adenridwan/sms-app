# Analisa Menu Akademik: Kurikulum, Mata Pelajaran, Jadwal

Status per 2026-08-02. Ditulis sebelum implementasi, sebagai dasar keputusan desain & urutan pengerjaan.

## 1. Arsitektur

Laravel + Inertia/React (bukan Filament). Pola per-entity: Model (`app/Infrastructure/Persistence/Eloquent/...`) + API Controller (`app/Http/Controllers/Api/V1/Academic/...`) + halaman React (`resources/js/pages/academic/...`) + route web (`routes/web.php`).

## 2. Status implementasi per sub-menu Akademik

| Sub-menu | Model | Migration | API Controller | Halaman React | Status |
|---|---|---|---|---|---|
| Tahun Ajaran | `AcademicYear` | `academic_years` | full CRUD | `Years.tsx` | Jalan |
| Tingkat Kelas | `GradeLevel` | `grade_levels` | full CRUD | `GradeLevels.tsx` | Jalan |
| Jurusan | `Major` | `majors` | full CRUD | `Majors.tsx` | Jalan |
| Kelas | `Classroom` | `classrooms` | full CRUD | `ClassRooms.tsx` | Jalan |
| **Kurikulum** | tidak ada | `curricula` (+seed 2 baris) | stub 501 | tidak ada | **404** |
| **Mata Pelajaran** | tidak ada | `subjects` (+seed 19 baris) | 2 controller, keduanya rusak/tak terhubung | tidak ada | **404** |
| **Jadwal** | tidak ada | `schedules`+`time_slots` (skema solid, tanpa seed) | fatal error (model tak ada + kolom validasi salah) | tidak ada | **Crash** |

## 3. Alur bisnis proses (berdasarkan skema DB)

```
Tahun Ajaran ─┬─→ Semester
              └─→ Kelas (Classroom) ←── Tingkat Kelas + Jurusan + Wali Kelas(User)

Kurikulum ──→ Mata Pelajaran ──→ subject_grade_levels (credit_hours, KKM per Tingkat Kelas+Jurusan)
                    │
                    └──→ teacher_subjects (Guru ↔ Mapel, relasi Eloquent rusak)

Jadwal = Kelas + Mata Pelajaran + Guru(User) + Slot Waktu + Semester + Hari
   → dipakai untuk: absensi per jam pelajaran, dashboard/detail guru, kontrol akses kelas
```

Urutan dependensi: **Kurikulum** harus ada dulu (karena `subjects.curriculum_id`) → baru **Mata Pelajaran** bisa dibuat → baru **Jadwal** bisa disusun (karena `schedules.subject_id` wajib valid).

## 4. Keterkaitan ke bagian lain sistem

- `Teacher::subjects()` (`app/Infrastructure/Persistence/Eloquent/Teacher/Teacher.php:132-135`) — relasi ke model `TeacherSubject` yang tidak pernah dibuat. `TeacherAssignmentService.php` sudah mengakalinya pakai raw query ke tabel `teacher_subjects`.
- `User::teachingClassroomIds()` (`app/Infrastructure/Persistence/Eloquent/Auth/User.php:223-263`) — salah satu dari 3 sumber datanya adalah tabel `schedules` (raw query), dipakai untuk kontrol akses kelas per guru.
- `ChecksReferentialUsage` — memakai tabel `schedules` sebagai guard hapus Tahun Ajaran/Semester.
- Halaman detail Guru (`TeacherAssignmentService.php`) — menampilkan jadwal mengajar mingguan via raw query ke `schedules`+`subjects`+`time_slots`.
- Sidebar (`MainLayout.tsx`) vs `routes/web.php` tidak sinkron — link "Kurikulum"/"Mata Pelajaran" mengarah ke path yang belum terdaftar.

## 5. Bug spesifik yang ditemukan

1. `app/Http/Controllers/Api/V1/MasterData/SubjectController.php` — referensi `App\Models\MasterData\Subject` yang tidak eksis, tidak terpasang di route manapun (dead code).
2. `app/Http/Controllers/Api/V1/Academic/ScheduleController.php` — import `App\Models\Academic\Schedule` (tidak eksis) + validasi pakai kolom lama (`class_room_id`, `day`, `start_time`, `end_time`) yang tidak cocok skema aktual (`classroom_id`, `day_of_week`, `time_slot_id`).
3. `Teacher::subjects()` merujuk model `TeacherSubject` yang tidak pernah dibuat.

## 6. Urutan pengerjaan

1. **Kurikulum** — model, controller CRUD, route, halaman React (lihat [01-UIUX-KURIKULUM.md](01-UIUX-KURIKULUM.md)). ✅ **Selesai.**
2. **Mata Pelajaran** — dikerjakan menyatu dengan Kurikulum (relasi 1→banyak lewat `curriculum_id`), termasuk perbaikan `TeacherSubject`. ✅ **Selesai.**
3. **Jadwal** — menyusul setelah Kurikulum & Mata Pelajaran jalan, karena `schedules.subject_id` butuh data valid. Perlu perbaikan `ScheduleController` yang salah kolom. ✅ **Selesai** — lihat [02-ANALISA-UIUX-JADWAL.md](02-ANALISA-UIUX-JADWAL.md).

## 7. Status implementasi (update)

Kurikulum & Mata Pelajaran sudah diimplementasikan penuh:

- Model: `Curriculum`, `Subject` (`app/Infrastructure/Persistence/Eloquent/Academic/`), plus `TeacherSubject` (`app/Infrastructure/Persistence/Eloquent/Teacher/`) yang memperbaiki relasi `Teacher::subjects()` yang sebelumnya rusak.
- Controller: `CurriculumController`, `SubjectController` (`app/Http/Controllers/Api/V1/Academic/`) — full CRUD dengan guard permission (`curricula.manage`, `subjects.manage`) dan guard referensial (kurikulum tak bisa dihapus bila masih punya mapel; mapel tak bisa dihapus bila masih diampu guru).
- Route web: `/academic/curricula`, `/academic/subjects` terdaftar di `routes/web.php`; `/master/subjects` (rute lama, dead code) di-redirect ke `/academic/subjects`.
- Halaman React: `resources/js/pages/academic/Curricula.tsx`, `Subjects.tsx` — flat CRUD sesuai keputusan final di [01-UIUX-KURIKULUM.md](01-UIUX-KURIKULUM.md) §"Keputusan final".
- Test: `tests/Feature/AcademicCurriculumSubjectTest.php` (12 test: rute web, CRUD API, validasi, guard hapus, permission guru vs admin) — semua lulus, dan suite penuh (207 test) tidak regresi.

Item lama yang **masih dead code, sengaja tidak disentuh** (di luar scope Kurikulum/Mapel): `app/Http/Controllers/Api/V1/MasterData/SubjectController.php` (tidak dirouting, referensi model yang tak eksis) dan `app/Http/Resources/SubjectResource.php` (top-level, bukan yang di `Academic/`).
