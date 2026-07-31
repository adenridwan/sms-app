<?php

namespace App\Exports\Attendance;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Rekap bulanan absensi siswa (Fase 4 ATTENDANCE-PLAN.md §7). Menerima
 * data yang SUDAH dihitung oleh
 * AttendanceReportController::generateStudentReportData()['students']
 * — bukan Eloquent collection, jadi FromArray (bukan FromCollection).
 */
class StudentAttendanceMonthlyExport implements FromArray, WithHeadings
{
    public function __construct(private array $students) {}

    public function array(): array
    {
        return array_map(fn (array $student) => [
            $student['nis'],
            $student['name'],
            $student['stats']['hadir'],
            $student['stats']['sakit'],
            $student['stats']['izin'],
            $student['stats']['alfa'],
            $student['stats']['total_late_minutes'],
            $student['attendance_rate'],
        ], $this->students);
    }

    public function headings(): array
    {
        return ['NIS', 'Nama', 'Hadir', 'Sakit', 'Izin', 'Alfa', 'Terlambat (menit)', '% Kehadiran'];
    }
}
