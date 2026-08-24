<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Services\AttendanceStatusResolver;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Absensi Saya — self-service. Berbeda dari TeacherAttendanceController,
 * SEMUA endpoint di sini SELALU discope ke `auth()->user()->id`, tidak ada
 * parameter user_id/teacher_id yang bisa dipakai untuk melihat data orang
 * lain, jadi tidak butuh permission khusus di luar login (auth:sanctum) —
 * gating menu-nya sendiri dilakukan lewat MenuRegistry (attendance.check-in),
 * bukan di sini.
 */
class MyAttendanceController extends ApiController
{
    private const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private AttendanceStatusResolver $statusResolver
    ) {}

    /**
     * Status kehadiran hari ini + identitas untuk kartu ringkasan di atas
     * halaman (jam masuk/pulang, status, dan info QR presensi milik sendiri).
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['teacher', 'staff']);
        $today = Carbon::today();
        $dateKey = $today->toDateString();

        $record = EmployeeAttendance::getForUserOnDate($user->id, $dateKey);
        $status = $this->resolveDisplayStatus($record, $dateKey, $today, $user->tenant_id);

        return $this->success([
            'date' => $dateKey,
            'date_label' => $this->formatDateLabel($today),
            'status' => $status,
            'check_in_time' => $record?->check_in_time?->format('H:i'),
            'check_out_time' => $record?->check_out_time?->format('H:i'),
            'late_minutes' => $record?->late_minutes ?? 0,
            'identity_number' => $user->teacher?->nip ?? $user->staff?->employee_id,
            'qr' => [
                // QR presensi saat ini hanya tersedia untuk guru (unique_code
                // ada di tabel teachers) — staf non-guru belum punya QR sendiri.
                'available' => (bool) $user->teacher,
                'teacher_id' => $user->teacher?->id,
            ],
            // Ref ID (rfid_code) dipakai untuk scan tap kartu ATAU diketik
            // manual di mode "RFID / Manual" pada halaman Scanner — sama
            // seperti QR, kolomnya cuma ada di tabel teachers, jadi staf
            // non-guru belum bisa (rfid_code selalu null untuk mereka).
            'rfid_code' => $user->teacher?->rfid_code,
        ]);
    }

    /**
     * Riwayat kehadiran sebulan penuh (hari kerja saja yang dihitung ke
     * ringkasan) — hari tanpa record dianggap "Belum Scan" (hari ini/masa
     * depan) atau "Tidak Hadir" (sudah lewat, tidak pernah discan), dan hari
     * bukan hari kerja (weekend/libur) ditandai "Libur".
     */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $user = $request->user();
        [$year, $month] = array_map('intval', explode('-', $data['month']));

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $records = EmployeeAttendance::forUser($user->id)
            ->betweenDates($start->toDateString(), $end->toDateString())
            ->get()
            ->keyBy(fn ($r) => $r->attendance_date->toDateString());

        $history = [];
        $summary = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alfa' => 0, 'telat' => 0];
        $totalWorkingDays = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->toDateString();
            $record = $records->get($dateKey);
            $status = $this->resolveDisplayStatus($record, $dateKey, $today, $user->tenant_id);
            $lateMinutes = $record?->late_minutes ?? 0;

            if ($status !== 'libur') {
                $totalWorkingDays++;
                if (in_array($status, ['present', 'late'], true)) {
                    $summary['hadir']++;
                } elseif ($status === 'sick') {
                    $summary['sakit']++;
                } elseif ($status === 'permitted') {
                    $summary['izin']++;
                } elseif ($status === 'absent') {
                    $summary['alfa']++;
                }
                if ($lateMinutes > 0) {
                    $summary['telat']++;
                }
            }

            $history[] = [
                'date' => $dateKey,
                'date_short' => $date->format('d/m'),
                'day' => self::DAY_NAMES[$date->dayOfWeek],
                'status' => $status,
                'check_in_time' => $record?->check_in_time?->format('H:i'),
                'check_out_time' => $record?->check_out_time?->format('H:i'),
                'late_minutes' => $lateMinutes,
                'notes' => $record?->notes,
            ];
        }

        return $this->success([
            'month' => $data['month'],
            'month_label' => self::MONTH_NAMES[$month] . ' ' . $year,
            'total_working_days' => $totalWorkingDays,
            'summary' => $summary,
            // Terbaru dulu, sama seperti tabel riwayat pada modul lain.
            'history' => array_reverse($history),
        ]);
    }

    /**
     * Status DB asli (present/absent/late/sick/permitted/on_duty/
     * work_from_home) kalau ada record; kalau tidak, resolusi berbasis
     * kalender kerja — vocabulary employee_attendances, BUKAN
     * AttendanceStatus enum siswa (nilainya beda: 'alpha' vs 'absent').
     */
    private function resolveDisplayStatus(?EmployeeAttendance $record, string $dateKey, Carbon $today, ?string $tenantId): string
    {
        if ($record) {
            return $record->status;
        }

        if (! $this->statusResolver->isWorkingDay($dateKey, $tenantId)) {
            return 'libur';
        }

        $date = Carbon::parse($dateKey);
        if ($date->greaterThanOrEqualTo($today)) {
            return 'belum_scan';
        }

        return 'absent';
    }

    private function formatDateLabel(Carbon $date): string
    {
        return self::DAY_NAMES[$date->dayOfWeek] . ', ' . $date->day . ' ' . self::MONTH_NAMES[$date->month] . ' ' . $date->year;
    }
}
