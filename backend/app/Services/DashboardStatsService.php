<?php

namespace App\Services;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Sumber tunggal statistik dashboard per role (R5, ROLE-ACCESS-PLAN.md).
 *
 * Dipakai oleh API DashboardController dan PageController@dashboard sehingga
 * angka web dan API tidak pernah berbeda. Semua data mengikuti scope R3/R4;
 * tidak ada angka dummy (R6) — bila data tertaut tidak ada, kembalikan
 * flag `linked: false` agar frontend menampilkan pesan jujur.
 */
class DashboardStatsService
{
    /**
     * Urutan prioritas role (R5): paket ditentukan role tertinggi user.
     *
     * @var array<string, string> role => paket
     */
    private const ROLE_PACKAGE = [
        'super_admin' => 'admin',
        'admin' => 'admin',
        'kepala_sekolah' => 'principal',
        'wakil_kepala_sekolah' => 'principal',
        'bendahara' => 'finance',
        'wali_kelas' => 'teacher',
        'guru' => 'teacher',
        'tata_usaha' => 'operational',
        'pustakawan' => 'operational',
        'siswa' => 'student',
        'orang_tua' => 'parent',
    ];

    public function resolvePackage(User $user): string
    {
        $roles = $user->getRoleNames();

        foreach (self::ROLE_PACKAGE as $role => $package) {
            if ($roles->contains($role)) {
                return $package;
            }
        }

        return 'none';
    }

    /**
     * @return array<string, mixed> selalu memuat kunci 'package'
     */
    public function statsFor(User $user): array
    {
        $package = $this->resolvePackage($user);

        $stats = match ($package) {
            'admin' => $this->schoolStats(withFinance: true),
            'principal' => $this->schoolStats(withFinance: false),
            'finance' => $this->financeStats(),
            'operational' => $this->schoolStats(withFinance: false),
            'teacher' => $this->teacherStats($user),
            'student' => $this->studentStats($user),
            'parent' => $this->parentStats($user),
            default => [],
        };

        return ['package' => $package] + $stats;
    }

    // ---------- paket sekolah (admin / kepsek / operasional) ----------

    private function schoolStats(bool $withFinance): array
    {
        $stats = [
            'total_students' => Student::where('status', 'active')->count(),
            'total_teachers' => Teacher::where('status', 'active')->count(),
            'total_staff' => DB::table('staff')->whereNull('deleted_at')->where('status', 'active')->count(),
            'total_classrooms' => Classroom::where('is_active', true)->count(),
            'active_academic_year' => AcademicYear::where('is_active', true)->value('name'),
            'attendance_today' => $this->attendanceToday(),
        ];

        if ($withFinance) {
            $stats['finance_summary'] = $this->financeSummary();
        }

        return $stats;
    }

    private function financeStats(): array
    {
        return [
            'total_students' => Student::where('status', 'active')->count(),
            'finance_summary' => $this->financeSummary(),
            'recent_payments' => DB::table('payments')
                ->join('students', 'students.id', '=', 'payments.student_id')
                ->join('users', 'users.id', '=', 'students.user_id')
                ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
                ->where('payments.status', 'completed')
                ->orderByDesc('payments.paid_at')
                ->limit(5)
                ->get([
                    'payments.id',
                    'payments.invoice_number',
                    'payments.grand_total',
                    'payments.paid_at',
                    DB::raw("TRIM(CONCAT(COALESCE(user_profiles.first_name,''),' ',COALESCE(user_profiles.last_name,''))) as student_name"),
                ]),
        ];
    }

    // ---------- paket guru / wali kelas ----------

    private function teacherStats(User $user): array
    {
        $classroomIds = $user->teachingClassroomIds();

        if ($classroomIds === []) {
            return ['linked' => false, 'my_classes' => []];
        }

        $classes = Classroom::whereIn('id', $classroomIds)
            ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'students_count' => $c->students_count,
            ]);

        // Kelas perwalian menjadi pilihan default pada dropdown (R5)
        $homeroomId = Classroom::where('homeroom_teacher_id', $user->id)
            ->whereIn('id', $classroomIds)
            ->value('id');

        return [
            'linked' => true,
            'my_classes' => $classes,
            'total_classes' => $classes->count(),
            'total_students' => Student::visibleTo($user)->where('status', 'active')->count(),
            'attendance_today' => $this->attendanceToday($classroomIds),
            'homeroom_classroom_id' => $homeroomId,
        ];
    }

    /**
     * Statistik satu kelas untuk dashboard guru (Fase 3): rekap absensi
     * hari ini, jadwal mengajar guru di kelas itu, dan siswa paling
     * sering alfa bulan ini. Penjagaan akses dilakukan pemanggil.
     */
    public function classStats(User $user, string $classroomId): array
    {
        $studentsCount = DB::table('student_enrollments')
            ->where('classroom_id', $classroomId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        $schedules = DB::table('schedules')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->join('time_slots', 'time_slots.id', '=', 'schedules.time_slot_id')
            ->where('schedules.classroom_id', $classroomId)
            ->where('schedules.teacher_id', $user->id)
            ->where('schedules.is_active', true)
            ->whereNull('schedules.deleted_at')
            ->orderBy('schedules.day_of_week')
            ->orderBy('time_slots.order')
            ->get([
                'schedules.day_of_week',
                'subjects.name as subject',
                'time_slots.start_time',
                'time_slots.end_time',
            ])
            ->map(fn ($s) => [
                'day_of_week' => (int) $s->day_of_week,
                'day_name' => ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][(int) $s->day_of_week] ?? '',
                'subject' => $s->subject,
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
            ]);

        $monthStart = now()->startOfMonth()->toDateString();
        $topAbsent = StudentAttendance::query()
            ->join('students', 'students.id', '=', 'student_attendances.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->where('student_attendances.classroom_id', $classroomId)
            ->whereIn('student_attendances.status', ['absent', 'alpha'])
            ->where('student_attendances.attendance_date', '>=', $monthStart)
            ->groupBy('students.id', 'students.nis', 'user_profiles.first_name', 'user_profiles.last_name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->get([
                'students.id as student_id',
                'students.nis',
                DB::raw("TRIM(CONCAT(COALESCE(user_profiles.first_name,''),' ',COALESCE(user_profiles.last_name,''))) as name"),
                DB::raw('COUNT(*) as absent_days'),
            ])
            ->map(fn ($r) => [
                'student_id' => $r->student_id,
                'nis' => $r->nis,
                'name' => $r->name,
                'absent_days' => (int) $r->absent_days,
            ]);

        return [
            'classroom_id' => $classroomId,
            'students_count' => $studentsCount,
            'attendance_today' => $this->attendanceToday([$classroomId]),
            'my_schedules' => $schedules,
            'top_absent' => $topAbsent,
        ];
    }

    // ---------- paket siswa ----------

    private function studentStats(User $user): array
    {
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return ['linked' => false];
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $byStatus = StudentAttendance::where('student_id', $student->id)
            ->where('attendance_date', '>=', $monthStart)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = $this->mapStatuses($byStatus);
        $recorded = array_sum($summary);

        $today = StudentAttendance::where('student_id', $student->id)
            ->where('attendance_date', now()->toDateString())
            ->value('status');

        return [
            'linked' => true,
            'class_name' => $student->currentClass?->name,
            'attendance_month' => $summary,
            'attendance_percentage' => $recorded > 0
                ? round($summary['hadir'] / $recorded * 100, 1)
                : null,
            'today_status' => $today,
            'unpaid_fees' => (float) DB::table('student_fees')
                ->whereNull('deleted_at')
                ->where('student_id', $student->id)
                ->sum('remaining_amount'),
        ];
    }

    // ---------- paket orang tua ----------

    private function parentStats(User $user): array
    {
        $children = Student::visibleTo($user)
            ->with(['user.profile', 'currentClass'])
            ->get()
            ->map(function (Student $s) {
                $monthStart = now()->startOfMonth()->toDateString();
                $byStatus = StudentAttendance::where('student_id', $s->id)
                    ->where('attendance_date', '>=', $monthStart)
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status');
                $summary = $this->mapStatuses($byStatus);
                $recorded = array_sum($summary);

                return [
                    'id' => $s->id,
                    'name' => $s->user?->full_name,
                    'nis' => $s->nis,
                    'class_name' => $s->currentClass?->name,
                    'attendance_month' => $summary,
                    'attendance_percentage' => $recorded > 0
                        ? round($summary['hadir'] / $recorded * 100, 1)
                        : null,
                    'today_status' => StudentAttendance::where('student_id', $s->id)
                        ->where('attendance_date', now()->toDateString())
                        ->value('status'),
                    'unpaid_fees' => (float) DB::table('student_fees')
                        ->whereNull('deleted_at')
                        ->where('student_id', $s->id)
                        ->sum('remaining_amount'),
                ];
            })
            ->values();

        return [
            'linked' => $children->isNotEmpty(),
            'children' => $children,
        ];
    }

    // ---------- blok bersama ----------

    /**
     * Rekap absensi hari ini; bila $classroomIds diberikan, dibatasi
     * kelas tersebut (paket guru, R3).
     */
    private function attendanceToday(?array $classroomIds = null): array
    {
        $byStatus = StudentAttendance::where('attendance_date', now()->toDateString())
            ->when($classroomIds !== null, fn ($q) => $q->whereIn('classroom_id', $classroomIds))
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = $this->mapStatuses($byStatus);

        $activeStudents = Student::where('status', 'active')
            ->when($classroomIds !== null, fn ($q) => $q->whereHas(
                'enrollments',
                fn ($e) => $e->where('status', 'active')->whereIn('classroom_id', $classroomIds)
            ))
            ->count();

        $summary['belum_scan'] = max(0, $activeStudents - array_sum($summary));

        return [
            'date' => now()->toDateString(),
            'summary' => $summary,
            'percentage' => $activeStudents > 0
                ? round($summary['hadir'] / $activeStudents * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Peta status DB (bahasa Inggris) ke label aplikasi (bahasa Indonesia).
     * Keterlambatan tetap dihitung hadir. Logika kanonik ada di
     * AttendanceStatus::summaryFromRaw() agar seluruh modul absensi
     * memakai satu interpretasi kolom `status` yang sama.
     */
    private function mapStatuses($byStatus): array
    {
        return AttendanceStatus::summaryFromRaw($byStatus);
    }

    private function financeSummary(): array
    {
        $totals = DB::table('student_fees')
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(total_amount),0) as billed, COALESCE(SUM(paid_amount),0) as collected, COALESCE(SUM(remaining_amount),0) as outstanding')
            ->first();

        return [
            'total_billed' => (float) $totals->billed,
            'total_collected' => (float) $totals->collected,
            'total_outstanding' => (float) $totals->outstanding,
        ];
    }
}
