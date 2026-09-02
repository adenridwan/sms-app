<?php

namespace App\Domain\Payroll\Services;

use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Payroll\EmployeeSalary;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlipItem;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendancePayrollService
{
    /**
     * Status kehadiran yang dianggap hadir
     */
    public const PRESENT_STATUSES = ['present', 'late'];

    /**
     * Status kehadiran yang dianggap absen
     */
    public const ABSENT_STATUSES = ['absent', 'alpha'];

    /**
     * Status kehadiran yang dianggap izin/cuti
     */
    public const LEAVE_STATUSES = ['sick', 'permit', 'leave', 'cuti', 'izin', 'sakit'];

    /**
     * Hitung statistik kehadiran untuk satu karyawan dalam periode tertentu.
     *
     * @param string $userId User ID karyawan (dari teachers.user_id atau staff.user_id)
     * @param Carbon $startDate Tanggal mulai periode
     * @param Carbon $endDate Tanggal akhir periode
     * @param int|null $workingDaysOverride Override jumlah hari kerja (jika tidak dihitung otomatis)
     * @return array{
     *     working_days: int,
     *     days_present: int,
     *     days_absent: int,
     *     days_late: int,
     *     days_leave: int,
     *     total_late_minutes: int,
     *     total_early_leave_minutes: int,
     *     total_overtime_minutes: int
     * }
     */
    public function calculateAttendanceStats(
        string $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $workingDaysOverride = null
    ): array {
        // Ambil semua data kehadiran dalam periode
        $attendances = EmployeeAttendance::where('user_id', $userId)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Hitung hari kerja (exclude weekend, atau gunakan override)
        $workingDays = $workingDaysOverride ?? $this->countWorkingDays($startDate, $endDate);

        // Hitung berdasarkan status
        $daysPresent = $attendances->filter(fn($a) => in_array($a->status, self::PRESENT_STATUSES))->count();
        $daysAbsent = $attendances->filter(fn($a) => in_array($a->status, self::ABSENT_STATUSES))->count();
        $daysLate = $attendances->filter(fn($a) => $a->status === 'late')->count();
        $daysLeave = $attendances->filter(fn($a) => in_array($a->status, self::LEAVE_STATUSES))->count();

        // Hitung total menit
        $totalLateMinutes = $attendances->sum('late_minutes') ?? 0;
        $totalEarlyLeaveMinutes = $attendances->sum('early_leave_minutes') ?? 0;
        $totalOvertimeMinutes = $attendances->sum('overtime_minutes') ?? 0;

        // Jika tidak ada data kehadiran, asumsi absen = hari kerja - cuti
        if ($attendances->isEmpty()) {
            $daysAbsent = $workingDays;
        }

        return [
            'working_days' => $workingDays,
            'days_present' => $daysPresent,
            'days_absent' => $daysAbsent,
            'days_late' => $daysLate,
            'days_leave' => $daysLeave,
            'total_late_minutes' => $totalLateMinutes,
            'total_early_leave_minutes' => $totalEarlyLeaveMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
        ];
    }

    /**
     * Hitung jumlah hari kerja (exclude weekend: Sabtu & Minggu)
     */
    public function countWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $count = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            // Skip weekend (Sabtu = 6, Minggu = 0)
            if (!$current->isWeekend()) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Hitung potongan/tunjangan kehadiran berdasarkan komponen gaji.
     *
     * @param array $attendanceStats Hasil dari calculateAttendanceStats()
     * @param Collection<SalaryComponent> $components Komponen gaji yang berlaku
     * @return array{
     *     items: array<int, array{component_id: string, code: string, name: string, type: string, category: string, amount: float, quantity: int, rate: float}>,
     *     total_earnings: float,
     *     total_deductions: float,
     *     attendance_deduction: float
     * }
     */
    public function calculateAttendanceComponents(array $attendanceStats, Collection $components): array
    {
        $items = [];
        $totalEarnings = 0;
        $totalDeductions = 0;

        foreach ($components as $component) {
            if (!$component->is_active) {
                continue;
            }

            // Hanya proses komponen dengan tipe perhitungan per_day atau per_hour
            if (!in_array($component->calculation_type, ['per_day', 'per_hour'])) {
                continue;
            }

            $amount = 0;
            $quantity = 0;
            $rate = (float) $component->default_value;

            switch ($component->calculation_type) {
                case 'per_day':
                    // Tentukan quantity berdasarkan kode komponen
                    $quantity = $this->getQuantityForComponent($component->code, $attendanceStats);
                    $amount = $quantity * $rate;
                    break;

                case 'per_hour':
                    // Untuk per_hour, gunakan overtime_minutes atau late_minutes
                    $minutes = $this->getMinutesForComponent($component->code, $attendanceStats);
                    $quantity = $minutes; // dalam menit
                    $amount = ($minutes / 60) * $rate; // konversi ke jam
                    break;
            }

            if ($amount > 0) {
                $items[] = [
                    'component_id' => $component->id,
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'category' => 'attendance',
                    'amount' => round($amount, 2),
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'is_taxable' => $component->is_taxable,
                ];

                if ($component->type === 'earning') {
                    $totalEarnings += $amount;
                } else {
                    $totalDeductions += $amount;
                }
            }
        }

        return [
            'items' => $items,
            'total_earnings' => round($totalEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'attendance_deduction' => round($totalDeductions, 2),
        ];
    }

    /**
     * Tentukan quantity berdasarkan kode komponen.
     */
    protected function getQuantityForComponent(string $code, array $stats): int
    {
        $code = strtoupper($code);

        // Komponen berbasis hari hadir
        if (str_contains($code, 'HADIR') || str_contains($code, 'PRESENT') || str_contains($code, 'MAKAN')) {
            return $stats['days_present'];
        }

        // Komponen berbasis hari absen
        if (str_contains($code, 'ABSEN') || str_contains($code, 'ABSENT') || str_contains($code, 'ALPHA')) {
            return $stats['days_absent'];
        }

        // Komponen berbasis keterlambatan
        if (str_contains($code, 'TELAT') || str_contains($code, 'LATE')) {
            return $stats['days_late'];
        }

        // Komponen berbasis cuti/izin
        if (str_contains($code, 'CUTI') || str_contains($code, 'LEAVE') || str_contains($code, 'IZIN')) {
            return $stats['days_leave'];
        }

        // Default: gunakan hari hadir
        return $stats['days_present'];
    }

    /**
     * Tentukan menit berdasarkan kode komponen.
     */
    protected function getMinutesForComponent(string $code, array $stats): int
    {
        $code = strtoupper($code);

        // Komponen berbasis keterlambatan (menit)
        if (str_contains($code, 'TELAT') || str_contains($code, 'LATE')) {
            return $stats['total_late_minutes'];
        }

        // Komponen berbasis pulang awal (menit)
        if (str_contains($code, 'EARLY') || str_contains($code, 'AWAL')) {
            return $stats['total_early_leave_minutes'];
        }

        // Komponen berbasis lembur (menit)
        if (str_contains($code, 'LEMBUR') || str_contains($code, 'OVERTIME')) {
            return $stats['total_overtime_minutes'];
        }

        return 0;
    }

    /**
     * Apply statistik kehadiran ke PayrollSlip.
     */
    public function applyToSlip(PayrollSlip $slip, array $attendanceStats): void
    {
        $slip->update([
            'working_days' => $attendanceStats['working_days'],
            'days_present' => $attendanceStats['days_present'],
            'days_absent' => $attendanceStats['days_absent'],
            'days_late' => $attendanceStats['days_late'],
            'days_leave' => $attendanceStats['days_leave'],
        ]);
    }

    /**
     * Tambahkan item kehadiran ke PayrollSlip.
     *
     * @param PayrollSlip $slip
     * @param array $attendanceItems Hasil dari calculateAttendanceComponents()['items']
     */
    public function addAttendanceItemsToSlip(PayrollSlip $slip, array $attendanceItems): void
    {
        foreach ($attendanceItems as $item) {
            // Cek apakah item sudah ada (untuk update, bukan duplikat)
            $existingItem = $slip->items()
                ->where('salary_component_id', $item['component_id'])
                ->where('category', 'attendance')
                ->first();

            if ($existingItem) {
                // Update item yang sudah ada
                $existingItem->update([
                    'amount' => $item['amount'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'is_auto_calculated' => true,
                ]);
            } else {
                // Buat item baru
                $slip->items()->create([
                    'salary_component_id' => $item['component_id'],
                    'component_code' => $item['code'],
                    'component_name' => $item['name'],
                    'type' => $item['type'],
                    'category' => 'attendance',
                    'amount' => $item['amount'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'is_taxable' => $item['is_taxable'],
                    'is_auto_calculated' => true,
                ]);
            }
        }

        // Update attendance_deduction di slip
        $totalDeduction = collect($attendanceItems)
            ->where('type', 'deduction')
            ->sum('amount');

        $slip->update(['attendance_deduction' => $totalDeduction]);
    }

    /**
     * Proses kehadiran untuk semua slip dalam satu periode.
     *
     * @param string $tenantId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param Collection<PayrollSlip> $slips
     * @return array{processed: int, errors: array}
     */
    public function processAttendanceForPeriod(
        string $tenantId,
        Carbon $startDate,
        Carbon $endDate,
        Collection $slips
    ): array {
        $processed = 0;
        $errors = [];

        // Ambil semua komponen gaji bertipe per_day atau per_hour
        $attendanceComponents = SalaryComponent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('calculation_type', ['per_day', 'per_hour'])
            ->get();

        foreach ($slips as $slip) {
            try {
                // Dapatkan user_id dari employee (Teacher atau Staff)
                $employee = $slip->getEmployee();
                if (!$employee || !$employee->user_id) {
                    $errors[] = [
                        'slip_id' => $slip->id,
                        'error' => 'Employee tidak memiliki user_id',
                    ];
                    continue;
                }

                // Hitung statistik kehadiran
                $stats = $this->calculateAttendanceStats($employee->user_id, $startDate, $endDate);

                // Apply ke slip
                $this->applyToSlip($slip, $stats);

                // Hitung dan tambahkan komponen kehadiran
                if ($attendanceComponents->isNotEmpty()) {
                    $attendanceResult = $this->calculateAttendanceComponents($stats, $attendanceComponents);
                    $this->addAttendanceItemsToSlip($slip, $attendanceResult['items']);
                }

                $processed++;
            } catch (\Exception $e) {
                $errors[] = [
                    'slip_id' => $slip->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
        ];
    }
}
