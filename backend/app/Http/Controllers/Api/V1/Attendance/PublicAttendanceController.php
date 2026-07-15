<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Services\AttendanceStatusResolver;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAttendanceController extends ApiController
{
    public function __construct(
        private AttendanceStatusResolver $statusResolver
    ) {}

    /**
     * Check attendance status for a student (public portal)
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nis' => ['required', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $data['date'] ?? now()->toDateString();

        $student = Student::with('user')
            ->where('nis', $data['nis'])
            ->first();

        if (!$student) {
            return $this->error('Siswa tidak ditemukan', 404);
        }

        $attendance = StudentAttendance::getForStudentOnDate($student->id, $date);

        if ($attendance) {
            $status = $attendance->status;
            $statusLabel = \App\Domain\Attendance\Enums\AttendanceStatus::tryFrom($status)?->label() ?? $status;
        } else {
            $resolvedStatus = $this->statusResolver->resolveStudentStatus($student->id, $date);
            $status = $resolvedStatus->value;
            $statusLabel = $resolvedStatus->label();
        }

        return $this->success([
            'student' => [
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
            ],
            'date' => $date,
            'status' => $status,
            'status_label' => $statusLabel,
            'check_in_time' => $attendance?->check_in_time?->format('H:i'),
            'check_out_time' => $attendance?->check_out_time?->format('H:i'),
            'menit_keterlambatan' => $attendance?->menit_keterlambatan ?? 0,
        ]);
    }

    /**
     * Get attendance history for a student (public portal)
     */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nis' => ['required', 'string', 'max:50'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ]);

        $month = $data['month'] ?? now()->month;
        $year = $data['year'] ?? now()->year;

        $student = Student::with('user')
            ->where('nis', $data['nis'])
            ->first();

        if (!$student) {
            return $this->error('Siswa tidak ditemukan', 404);
        }

        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = StudentAttendance::forStudent($student->id)
            ->betweenDates($startDate->toDateString(), $endDate->toDateString())
            ->orderBy('attendance_date', 'asc')
            ->get();

        $history = $attendances->map(fn($att) => [
            'date' => $att->attendance_date->format('d/m/Y'),
            'day' => $att->attendance_date->translatedFormat('l'),
            'status' => $att->status,
            'status_label' => \App\Domain\Attendance\Enums\AttendanceStatus::tryFrom($att->status)?->label() ?? $att->status,
            'check_in_time' => $att->check_in_time?->format('H:i'),
            'check_out_time' => $att->check_out_time?->format('H:i'),
        ]);

        // Calculate summary
        $summary = [
            'total' => $attendances->count(),
            'hadir' => $attendances->where('status', 'hadir')->count(),
            'sakit' => $attendances->where('status', 'sakit')->count(),
            'izin' => $attendances->where('status', 'izin')->count(),
            'alfa' => $attendances->whereIn('status', ['alfa', 'tanpa_keterangan'])->count(),
        ];

        return $this->success([
            'student' => [
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'month_name' => $startDate->translatedFormat('F Y'),
            ],
            'history' => $history,
            'summary' => $summary,
        ]);
    }
}
