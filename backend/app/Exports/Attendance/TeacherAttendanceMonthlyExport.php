<?php

namespace App\Exports\Attendance;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Rekap bulanan absensi guru (Fase 4 ATTENDANCE-PLAN.md §7). Menerima
 * data yang SUDAH dihitung oleh
 * AttendanceReportController::generateTeacherReportData()['teachers'].
 */
class TeacherAttendanceMonthlyExport implements FromArray, WithHeadings
{
    public function __construct(private array $teachers) {}

    public function array(): array
    {
        return array_map(fn (array $teacher) => [
            $teacher['name'],
            $teacher['stats']['present'],
            $teacher['stats']['sick'],
            $teacher['stats']['permitted'],
            $teacher['stats']['absent'],
            $teacher['stats']['total_late_minutes'],
            $teacher['attendance_rate'],
        ], $this->teachers);
    }

    public function headings(): array
    {
        return ['Nama', 'Hadir', 'Sakit', 'Izin', 'Tidak Hadir', 'Terlambat (menit)', '% Kehadiran'];
    }
}
