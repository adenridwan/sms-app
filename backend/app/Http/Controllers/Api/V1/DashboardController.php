<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Academic\AcademicYear;
use App\Models\Finance\Payment;
use App\Models\MasterData\ClassRoom;
use App\Models\Student\Student;
use App\Models\Teacher\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    /**
     * Get dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->roles->first()?->name;

        $stats = match ($role) {
            'super_admin', 'admin' => $this->getAdminStats(),
            'kepala_sekolah' => $this->getPrincipalStats(),
            'guru' => $this->getTeacherStats($user),
            'siswa' => $this->getStudentStats($user),
            'wali_murid' => $this->getParentStats($user),
            'bendahara' => $this->getFinanceStats(),
            default => $this->getBasicStats(),
        };

        return $this->success($stats, 'Dashboard statistics retrieved successfully');
    }

    /**
     * Get admin dashboard statistics.
     */
    private function getAdminStats(): array
    {
        return [
            'total_students' => Student::where('status', 'active')->count(),
            'total_teachers' => Teacher::where('employment_status', 'active')->count(),
            'total_classes' => ClassRoom::where('is_active', true)->count(),
            'active_academic_year' => AcademicYear::where('is_active', true)->first()?->name,
            'recent_payments' => Payment::with('student:id,nis,user_id', 'student.user:id,first_name,last_name')
                ->where('status', 'paid')
                ->latest('paid_at')
                ->take(5)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'student_name' => $p->student?->user?->full_name,
                    'amount' => $p->amount,
                    'paid_at' => $p->paid_at?->toISOString(),
                ]),
            'student_by_gender' => Student::select('gender', DB::raw('count(*) as total'))
                ->where('status', 'active')
                ->groupBy('gender')
                ->pluck('total', 'gender'),
            'attendance_today' => $this->getTodayAttendance(),
        ];
    }

    /**
     * Get principal dashboard statistics.
     */
    private function getPrincipalStats(): array
    {
        return [
            'total_students' => Student::where('status', 'active')->count(),
            'total_teachers' => Teacher::where('employment_status', 'active')->count(),
            'total_classes' => ClassRoom::where('is_active', true)->count(),
            'teacher_attendance_today' => $this->getTeacherAttendanceToday(),
            'student_attendance_today' => $this->getTodayAttendance(),
        ];
    }

    /**
     * Get teacher dashboard statistics.
     */
    private function getTeacherStats($user): array
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return $this->getBasicStats();
        }

        return [
            'my_classes' => $teacher->classRooms()->count(),
            'my_subjects' => $teacher->subjects()->count(),
            'total_students' => $this->getTeacherStudentCount($teacher),
            'upcoming_schedules' => $this->getUpcomingSchedules($teacher),
        ];
    }

    /**
     * Get student dashboard statistics.
     */
    private function getStudentStats($user): array
    {
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return $this->getBasicStats();
        }

        return [
            'attendance_percentage' => $this->getStudentAttendancePercentage($student),
            'unpaid_fees' => Payment::where('student_id', $student->id)
                ->where('status', 'pending')
                ->sum('amount'),
            'recent_grades' => [],
            'upcoming_exams' => [],
        ];
    }

    /**
     * Get parent dashboard statistics.
     */
    private function getParentStats($user): array
    {
        return [
            'children' => Student::whereHas('parents', fn($q) => $q->where('user_id', $user->id))
                ->with('user:id,first_name,last_name')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->user?->full_name,
                    'nis' => $s->nis,
                    'class' => $s->currentClass?->name,
                ]),
        ];
    }

    /**
     * Get finance dashboard statistics.
     */
    private function getFinanceStats(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        return [
            'total_revenue_this_month' => Payment::where('status', 'paid')
                ->whereMonth('paid_at', $currentMonth)
                ->whereYear('paid_at', $currentYear)
                ->sum('amount'),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'pending_amount' => Payment::where('status', 'pending')->sum('amount'),
            'recent_transactions' => Payment::with('student:id,nis,user_id', 'student.user:id,first_name,last_name')
                ->where('status', 'paid')
                ->latest('paid_at')
                ->take(10)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'student_name' => $p->student?->user?->full_name,
                    'amount' => $p->amount,
                    'type' => $p->feeType?->name,
                    'paid_at' => $p->paid_at?->toISOString(),
                ]),
        ];
    }

    /**
     * Get basic statistics.
     */
    private function getBasicStats(): array
    {
        return [
            'message' => 'Welcome to SMS Enterprise',
        ];
    }

    /**
     * Get today's attendance statistics.
     */
    private function getTodayAttendance(): array
    {
        // Placeholder - implement when attendance module is ready
        return [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'sick' => 0,
            'permission' => 0,
        ];
    }

    /**
     * Get teacher attendance for today.
     */
    private function getTeacherAttendanceToday(): array
    {
        return [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
        ];
    }

    /**
     * Get total students for a teacher.
     */
    private function getTeacherStudentCount(Teacher $teacher): int
    {
        // Count students in classes taught by this teacher
        return 0;
    }

    /**
     * Get upcoming schedules for a teacher.
     */
    private function getUpcomingSchedules(Teacher $teacher): array
    {
        return [];
    }

    /**
     * Get student attendance percentage.
     */
    private function getStudentAttendancePercentage(Student $student): float
    {
        return 0.0;
    }
}
