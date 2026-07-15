<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\Holiday;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendanceReportController extends ApiController
{
    /**
     * Get monthly attendance report
     */
    public function monthly(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
            'type' => ['in:student,teacher'],
        ]);

        $month = $data['month'];
        $year = $data['year'];
        $type = $data['type'] ?? 'student';

        // Get working days in month (excluding holidays)
        $workingDays = $this->getWorkingDaysInMonth($month, $year);

        if ($type === 'student') {
            return $this->getStudentMonthlyReport($month, $year, $workingDays, $data['classroom_id'] ?? null);
        }

        return $this->getTeacherMonthlyReport($month, $year, $workingDays);
    }

    /**
     * Download monthly report as PDF
     */
    public function downloadPdf(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
            'type' => ['in:student,teacher'],
        ]);

        $month = $data['month'];
        $year = $data['year'];
        $type = $data['type'] ?? 'student';

        $workingDays = $this->getWorkingDaysInMonth($month, $year);

        if ($type === 'student') {
            $reportData = $this->generateStudentReportData($month, $year, $workingDays, $data['classroom_id'] ?? null);
            $pdf = Pdf::loadView('reports.attendance.student-monthly', $reportData);
        } else {
            $reportData = $this->generateTeacherReportData($month, $year, $workingDays);
            $pdf = Pdf::loadView('reports.attendance.teacher-monthly', $reportData);
        }

        $filename = "laporan-absensi-{$type}-{$month}-{$year}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Get 7-day trend data
     */
    public function weeklyTrend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
            'type' => ['in:student,teacher'],
        ]);

        $type = $data['type'] ?? 'student';
        $endDate = now()->toDateString();
        $startDate = now()->subDays(6)->toDateString();

        $dates = [];
        $current = Carbon::parse($startDate);
        while ($current <= Carbon::parse($endDate)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        if ($type === 'student') {
            $trend = $this->getStudentTrend($dates, $data['classroom_id'] ?? null);
        } else {
            $trend = $this->getTeacherTrend($dates);
        }

        return $this->success([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'trend' => $trend,
        ]);
    }

    /**
     * Get working days in a month (excluding holidays)
     */
    private function getWorkingDaysInMonth(int $month, int $year): array
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        $holidays = Holiday::betweenDates($startDate->toDateString(), $endDate->toDateString())
            ->pluck('tanggal')
            ->map(fn($d) => $d->toDateString())
            ->toArray();

        $workingDays = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            // Skip weekends and holidays
            if (!$current->isWeekend() && !in_array($current->toDateString(), $holidays)) {
                $workingDays[] = $current->toDateString();
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Get student monthly report
     */
    private function getStudentMonthlyReport(int $month, int $year, array $workingDays, ?string $classroomId): JsonResponse
    {
        $reportData = $this->generateStudentReportData($month, $year, $workingDays, $classroomId);

        return $this->success($reportData);
    }

    /**
     * Generate student report data
     */
    private function generateStudentReportData(int $month, int $year, array $workingDays, ?string $classroomId): array
    {
        $startDate = Carbon::create($year, $month, 1)->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // Get students
        $query = StudentEnrollment::with(['student.user'])
            ->where('status', 'active');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $enrollments = $query->get();

        // Get attendances
        $attendances = StudentAttendance::whereBetween('attendance_date', [$startDate, $endDate])
            ->when($classroomId, fn($q) => $q->where('classroom_id', $classroomId))
            ->get()
            ->groupBy(['student_id', fn($a) => $a->attendance_date->toDateString()]);

        $students = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $studentAttendances = $attendances->get($student->id) ?? collect();

            $stats = [
                'hadir' => 0,
                'sakit' => 0,
                'izin' => 0,
                'alfa' => 0,
                'tanpa_keterangan' => 0,
                'total_late_minutes' => 0,
            ];

            foreach ($workingDays as $day) {
                $dayAttendances = $studentAttendances->get($day);
                if ($dayAttendances && $dayAttendances->count() > 0) {
                    $att = $dayAttendances->first();
                    $status = $att->status;
                    if (isset($stats[$status])) {
                        $stats[$status]++;
                    }
                    $stats['total_late_minutes'] += $att->menit_keterlambatan ?? 0;
                } else {
                    // No record = Alfa
                    $stats['alfa']++;
                }
            }

            $totalDays = count($workingDays);
            $presentDays = $stats['hadir'];

            $students[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
                'stats' => $stats,
                'attendance_rate' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0,
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'month_name' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'total_working_days' => count($workingDays),
            'classroom_id' => $classroomId,
            'students' => $students,
        ];
    }

    /**
     * Get teacher monthly report
     */
    private function getTeacherMonthlyReport(int $month, int $year, array $workingDays): JsonResponse
    {
        $reportData = $this->generateTeacherReportData($month, $year, $workingDays);

        return $this->success($reportData);
    }

    /**
     * Generate teacher report data
     */
    private function generateTeacherReportData(int $month, int $year, array $workingDays): array
    {
        $startDate = Carbon::create($year, $month, 1)->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // Get attendances
        $attendances = EmployeeAttendance::with(['user.profile'])
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get()
            ->groupBy(['user_id', fn($a) => $a->attendance_date->toDateString()]);

        $teachers = [];
        foreach ($attendances as $userId => $userAttendances) {
            $firstAttendance = $userAttendances->flatten()->first();

            $stats = [
                'present' => 0,
                'absent' => 0,
                'sick' => 0,
                'permitted' => 0,
                'total_late_minutes' => 0,
            ];

            foreach ($workingDays as $day) {
                $dayAttendances = $userAttendances->get($day);
                if ($dayAttendances && $dayAttendances->count() > 0) {
                    $att = $dayAttendances->first();
                    $status = $att->status;
                    if (isset($stats[$status])) {
                        $stats[$status]++;
                    }
                    $stats['total_late_minutes'] += $att->late_minutes ?? 0;
                } else {
                    $stats['absent']++;
                }
            }

            $totalDays = count($workingDays);
            $presentDays = $stats['present'];

            $teachers[] = [
                'user_id' => $userId,
                'name' => $firstAttendance->user?->full_name,
                'stats' => $stats,
                'attendance_rate' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0,
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'month_name' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'total_working_days' => count($workingDays),
            'teachers' => $teachers,
        ];
    }

    /**
     * Get student trend data
     */
    private function getStudentTrend(array $dates, ?string $classroomId): array
    {
        $trend = [];

        foreach ($dates as $date) {
            $query = StudentAttendance::forDate($date)
                ->when($classroomId, fn($q) => $q->where('classroom_id', $classroomId));

            $stats = $query->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $trend[] = [
                'date' => $date,
                'day_name' => Carbon::parse($date)->translatedFormat('l'),
                'hadir' => $stats['hadir'] ?? 0,
                'sakit' => $stats['sakit'] ?? 0,
                'izin' => $stats['izin'] ?? 0,
                'alfa' => ($stats['alfa'] ?? 0) + ($stats['tanpa_keterangan'] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Get teacher trend data
     */
    private function getTeacherTrend(array $dates): array
    {
        $trend = [];

        foreach ($dates as $date) {
            $stats = EmployeeAttendance::forDate($date)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $trend[] = [
                'date' => $date,
                'day_name' => Carbon::parse($date)->translatedFormat('l'),
                'present' => $stats['present'] ?? 0,
                'absent' => $stats['absent'] ?? 0,
                'sick' => $stats['sick'] ?? 0,
                'permitted' => $stats['permitted'] ?? 0,
            ];
        }

        return $trend;
    }
}
