<?php

namespace App\Domain\Payroll\Services;

use App\Infrastructure\Persistence\Eloquent\Academic\Schedule;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service untuk menghitung jam mengajar guru dari jadwal pelajaran.
 *
 * Digunakan untuk komponen gaji dengan calculation_type = 'per_hour'
 * yang berbasis jam mengajar (bukan lembur/telat).
 */
class TeachingHoursService
{
    /**
     * Hitung total jam mengajar guru dalam periode tertentu.
     *
     * @param string $userId User ID guru (dari teachers.user_id)
     * @param Carbon $startDate Tanggal mulai periode
     * @param Carbon $endDate Tanggal akhir periode
     * @param string|null $tenantId Tenant ID (opsional, default dari auth)
     * @return array{
     *     total_minutes: int,
     *     total_hours: float,
     *     weekly_minutes: int,
     *     weekly_hours: float,
     *     weeks_in_period: int,
     *     schedules_count: int,
     *     details: array
     * }
     */
    public function calculateTeachingHours(
        string $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $tenantId = null
    ): array {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        // Cari semester aktif yang overlap dengan periode
        $semester = $this->findActiveSemester($tenantId, $startDate, $endDate);

        if (!$semester) {
            return $this->emptyResult();
        }

        // Ambil semua jadwal guru di semester ini
        $schedules = Schedule::with('timeSlot')
            ->where('tenant_id', $tenantId)
            ->where('teacher_id', $userId)
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->get();

        if ($schedules->isEmpty()) {
            return $this->emptyResult();
        }

        // Hitung menit per minggu dari jadwal
        $weeklyMinutes = 0;
        $details = [];

        foreach ($schedules as $schedule) {
            if (!$schedule->timeSlot) {
                continue;
            }

            $duration = $this->calculateSlotDuration($schedule->timeSlot);
            $weeklyMinutes += $duration;

            $details[] = [
                'day' => Schedule::DAY_NAMES[$schedule->day_of_week] ?? $schedule->day_of_week,
                'time_slot' => $schedule->timeSlot->name,
                'start_time' => $schedule->timeSlot->start_time,
                'end_time' => $schedule->timeSlot->end_time,
                'duration_minutes' => $duration,
                'classroom' => $schedule->classroom?->name,
                'subject' => $schedule->subject?->name,
            ];
        }

        // Hitung jumlah minggu dalam periode
        $weeksInPeriod = $this->countWeeksInPeriod($startDate, $endDate);

        // Total menit = menit per minggu × jumlah minggu
        $totalMinutes = $weeklyMinutes * $weeksInPeriod;

        return [
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
            'weekly_minutes' => $weeklyMinutes,
            'weekly_hours' => round($weeklyMinutes / 60, 2),
            'weeks_in_period' => $weeksInPeriod,
            'schedules_count' => $schedules->count(),
            'details' => $details,
        ];
    }

    /**
     * Hitung jam mengajar untuk banyak guru sekaligus (batch).
     *
     * @param array $userIds Array of user IDs
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string|null $tenantId
     * @return array<string, array> Keyed by user_id
     */
    public function calculateTeachingHoursBatch(
        array $userIds,
        Carbon $startDate,
        Carbon $endDate,
        ?string $tenantId = null
    ): array {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $semester = $this->findActiveSemester($tenantId, $startDate, $endDate);

        if (!$semester) {
            return array_fill_keys($userIds, $this->emptyResult());
        }

        // Ambil semua jadwal untuk semua guru sekaligus (optimasi N+1)
        $allSchedules = Schedule::with('timeSlot')
            ->where('tenant_id', $tenantId)
            ->whereIn('teacher_id', $userIds)
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->get()
            ->groupBy('teacher_id');

        $weeksInPeriod = $this->countWeeksInPeriod($startDate, $endDate);
        $results = [];

        foreach ($userIds as $userId) {
            $schedules = $allSchedules->get($userId, collect());

            if ($schedules->isEmpty()) {
                $results[$userId] = $this->emptyResult();
                continue;
            }

            $weeklyMinutes = 0;
            $details = [];

            foreach ($schedules as $schedule) {
                if (!$schedule->timeSlot) {
                    continue;
                }

                $duration = $this->calculateSlotDuration($schedule->timeSlot);
                $weeklyMinutes += $duration;

                $details[] = [
                    'day' => Schedule::DAY_NAMES[$schedule->day_of_week] ?? $schedule->day_of_week,
                    'time_slot' => $schedule->timeSlot->name,
                    'duration_minutes' => $duration,
                ];
            }

            $totalMinutes = $weeklyMinutes * $weeksInPeriod;

            $results[$userId] = [
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'weekly_minutes' => $weeklyMinutes,
                'weekly_hours' => round($weeklyMinutes / 60, 2),
                'weeks_in_period' => $weeksInPeriod,
                'schedules_count' => $schedules->count(),
                'details' => $details,
            ];
        }

        return $results;
    }

    /**
     * Cari semester yang aktif dan overlap dengan periode.
     */
    protected function findActiveSemester(string $tenantId, Carbon $startDate, Carbon $endDate): ?Semester
    {
        return Semester::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($startDate, $endDate) {
                // Semester yang overlap dengan periode payroll
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $endDate->toDateString())
                      ->where('end_date', '>=', $startDate->toDateString());
                });
            })
            ->first();
    }

    /**
     * Hitung durasi time slot dalam menit.
     */
    protected function calculateSlotDuration($timeSlot): int
    {
        try {
            $start = Carbon::createFromFormat('H:i:s', $timeSlot->start_time);
            $end = Carbon::createFromFormat('H:i:s', $timeSlot->end_time);

            // Handle jika format tanpa detik
            if (!$start) {
                $start = Carbon::createFromFormat('H:i', $timeSlot->start_time);
            }
            if (!$end) {
                $end = Carbon::createFromFormat('H:i', $timeSlot->end_time);
            }

            return $start && $end ? $end->diffInMinutes($start) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Hitung jumlah minggu dalam periode.
     */
    protected function countWeeksInPeriod(Carbon $startDate, Carbon $endDate): int
    {
        $days = $startDate->diffInDays($endDate) + 1;
        return (int) ceil($days / 7);
    }

    /**
     * Return empty result structure.
     */
    protected function emptyResult(): array
    {
        return [
            'total_minutes' => 0,
            'total_hours' => 0.0,
            'weekly_minutes' => 0,
            'weekly_hours' => 0.0,
            'weeks_in_period' => 0,
            'schedules_count' => 0,
            'details' => [],
        ];
    }
}
