<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan penugasan guru — READ ONLY (TEACHER-MODULE-PLAN.md §2 & §4).
 *
 * Sengaja terpisah dari TeacherResource: data ini hasil join lintas tabel
 * (kelas, jadwal, mapel) yang mahal bila dijalankan untuk setiap baris pada
 * daftar guru, jadi hanya dipanggil oleh halaman Detail Guru satu-per-satu.
 * Semua di sini hanya untuk ditampilkan; pengelolaannya ada di menu Kelas
 * (wali & pengampu) dan menu Mata Pelajaran/Jadwal (kompetensi & jadwal).
 */
class TeacherAssignmentService
{
    private const DAY_NAMES = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public function overview(Teacher $teacher): array
    {
        $activeYearId = AcademicYear::where('tenant_id', $teacher->tenant_id)
            ->where('is_active', true)
            ->value('id');

        if (! $activeYearId) {
            return [
                'active_academic_year' => null,
                'classrooms' => [],
                'homeroom_classroom' => null,
                'subjects' => [],
                'schedules' => [],
            ];
        }

        $classroomIds = $teacher->user?->teachingClassroomIds() ?? [];

        return [
            'active_academic_year' => AcademicYear::where('id', $activeYearId)->value('name'),
            'classrooms' => Classroom::whereIn('id', $classroomIds)
                ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'students_count' => $c->students_count])
                ->all(),
            'homeroom_classroom' => Classroom::where('homeroom_teacher_id', $teacher->user_id)
                ->where('academic_year_id', $activeYearId)
                ->first(['id', 'name']),
            'subjects' => $this->competencySubjects($teacher),
            'schedules' => $this->schedules($teacher, $activeYearId),
        ];
    }

    /**
     * Kompetensi mapel (teacher_subjects) — query langsung tanpa lewat
     * relasi Eloquent Teacher::subjects(), karena relasi itu merujuk model
     * TeacherSubject yang belum pernah dibuat (bug lama, di luar lingkup G2;
     * dicatat untuk menu Mata Pelajaran nanti).
     */
    private function competencySubjects(Teacher $teacher): array
    {
        return DB::table('teacher_subjects')
            ->join('subjects', 'subjects.id', '=', 'teacher_subjects.subject_id')
            ->where('teacher_subjects.teacher_id', $teacher->id)
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name', 'subjects.code', 'teacher_subjects.is_primary'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'is_primary' => (bool) $s->is_primary,
            ])
            ->all();
    }

    private function schedules(Teacher $teacher, string $activeYearId): array
    {
        return DB::table('schedules')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->join('classrooms', 'classrooms.id', '=', 'schedules.classroom_id')
            ->join('time_slots', 'time_slots.id', '=', 'schedules.time_slot_id')
            ->where('schedules.teacher_id', $teacher->user_id)
            ->where('schedules.academic_year_id', $activeYearId)
            ->where('schedules.is_active', true)
            ->whereNull('schedules.deleted_at')
            ->orderBy('schedules.day_of_week')
            ->orderBy('time_slots.order')
            ->get([
                'schedules.day_of_week',
                'subjects.name as subject',
                'classrooms.name as classroom',
                'time_slots.start_time',
                'time_slots.end_time',
            ])
            ->map(fn ($s) => [
                'day_of_week' => (int) $s->day_of_week,
                'day_name' => self::DAY_NAMES[(int) $s->day_of_week] ?? '',
                'subject' => $s->subject,
                'classroom' => $s->classroom,
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
            ])
            ->all();
    }
}
