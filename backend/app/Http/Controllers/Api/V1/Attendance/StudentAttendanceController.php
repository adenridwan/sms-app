<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Jobs\SendClassAttendanceRecap;
use App\Domain\Attendance\Services\AttendanceStatusResolver;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAttendanceController extends ApiController
{
    public function __construct(
        private AttendanceStatusResolver $statusResolver
    ) {}

    /**
     * List student attendances
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentAttendance::with(['student.user', 'classroom'])
            ->whereHas('student', fn($q) => $q->visibleTo($request->user()))
            ->when($request->date, fn($q, $date) => $q->forDate($date))
            ->when($request->classroom_id, fn($q, $id) => $q->forClassroom($id))
            ->when($request->student_id, fn($q, $id) => $q->forStudent($id))
            ->when($request->status, fn($q, $status) => $this->applyStatusFilter($q, $status))
            ->when($request->from_date, fn($q, $date) => $q->where('attendance_date', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->where('attendance_date', '<=', $date));

        $sortField = $request->get('sort', 'attendance_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);

        return $this->success($query->paginate($perPage));
    }

    /**
     * Get daily attendance for a classroom
     * Shows all students with their status (including "Belum Scan")
     */
    public function daily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
            'date' => ['required', 'date'],
        ]);

        $date = $data['date'];
        $classroomId = $data['classroom_id'];

        $this->authorizeClassroomAccess($request->user(), $classroomId);

        // Get all active students in classroom
        $enrollments = StudentEnrollment::with(['student.user'])
            ->where('classroom_id', $classroomId)
            ->where('status', 'active')
            ->get();

        // Get existing attendances
        $attendances = StudentAttendance::where('classroom_id', $classroomId)
            ->forDate($date)
            ->get()
            ->keyBy('student_id');

        $results = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $attendance = $attendances->get($student->id);

            if ($attendance) {
                $status = AttendanceStatus::tryFrom($attendance->status) ?? AttendanceStatus::Hadir;
            } else {
                $status = $this->statusResolver->resolveStudentStatus($student->id, $date);
            }

            $results[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
                'student_number_in_class' => $enrollment->student_number_in_class,
                'attendance_id' => $attendance?->id,
                'status' => $status->slug(),
                'status_label' => $status->label(),
                'status_color' => $status->color(),
                'check_in_time' => $attendance?->check_in_time?->format('H:i'),
                'check_out_time' => $attendance?->check_out_time?->format('H:i'),
                'menit_keterlambatan' => $attendance?->menit_keterlambatan ?? 0,
                'notes' => $attendance?->notes,
            ];
        }

        // Sort by student number
        usort($results, fn($a, $b) => ($a['student_number_in_class'] ?? 999) <=> ($b['student_number_in_class'] ?? 999));

        return $this->success([
            'date' => $date,
            'classroom_id' => $classroomId,
            'students' => $results,
            'summary' => $this->calculateSummary($results),
        ]);
    }

    /**
     * Store bulk attendance
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
            'date' => ['required', 'date'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'attendances.*.status' => ['required', 'in:' . self::storableStatusValues()],
            'attendances.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->authorizeClassroomAccess($request->user(), $data['classroom_id']);

        $enrollment = StudentEnrollment::where('classroom_id', $data['classroom_id'])
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return $this->error('Kelas tidak memiliki siswa aktif', 422);
        }

        try {
            DB::beginTransaction();

            $created = 0;
            $updated = 0;

            foreach ($data['attendances'] as $att) {
                $dbStatus = AttendanceStatus::fromSlug($att['status'])->value;

                $existing = StudentAttendance::where('student_id', $att['student_id'])
                    ->forDate($data['date'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'status' => $dbStatus,
                        'notes' => $att['notes'] ?? $existing->notes,
                    ]);
                    $updated++;
                } else {
                    // Get student enrollment
                    $studentEnrollment = StudentEnrollment::where('student_id', $att['student_id'])
                        ->where('classroom_id', $data['classroom_id'])
                        ->where('status', 'active')
                        ->first();

                    StudentAttendance::create([
                        'tenant_id' => $request->user()->tenant_id,
                        'student_id' => $att['student_id'],
                        'classroom_id' => $data['classroom_id'],
                        'academic_year_id' => $studentEnrollment?->academic_year_id,
                        'semester_id' => $studentEnrollment?->academicYear?->activeSemester?->id,
                        'attendance_date' => $data['date'],
                        'status' => $dbStatus,
                        'notes' => $att['notes'] ?? null,
                        'recorded_by' => $request->user()->id,
                    ]);
                    $created++;
                }
            }

            DB::commit();

            return $this->success([
                'created' => $created,
                'updated' => $updated,
            ], "Absensi berhasil disimpan: {$created} baru, {$updated} diperbarui");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal menyimpan absensi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kirim notifikasi rekap harian satu kelas ke wali murid (mode batch,
     * Fase 3 ATTENDANCE-PLAN.md §5). Pengiriman berjalan di queue karena
     * berisi puluhan panggilan HTTP ke provider WA.
     */
    public function notifyDaily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
            'date' => ['required', 'date'],
        ]);

        $this->authorizeClassroomAccess($request->user(), $data['classroom_id']);

        $tenantId = $request->user()->tenant_id;
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->isWhatsAppConfigured() && !$settings->isTelegramConfigured()) {
            return $this->error('Belum ada kanal notifikasi (WhatsApp/Telegram) yang dikonfigurasi.', 422);
        }

        $recipients = StudentEnrollment::where('classroom_id', $data['classroom_id'])
            ->where('status', 'active')
            ->whereHas('student.guardians', fn($q) => $q->where('is_primary_contact', true)->whereNotNull('phone'))
            ->count();

        SendClassAttendanceRecap::dispatch(
            $tenantId,
            $data['classroom_id'],
            $data['date'],
            $request->user()->id
        );

        return $this->success(
            ['recipients' => $recipients],
            "Notifikasi rekap sedang dikirim untuk {$recipients} siswa dengan kontak wali."
        );
    }

    /**
     * Update a single attendance
     */
    public function update(Request $request, StudentAttendance $attendance): JsonResponse
    {
        // Hanya boleh mengubah absensi siswa yang berada dalam cakupannya (R4)
        $isVisible = Student::visibleTo($request->user())
            ->whereKey($attendance->student_id)
            ->exists();

        if (! $isVisible) {
            return $this->forbidden('Anda tidak memiliki akses ke absensi siswa ini.');
        }

        $data = $request->validate([
            'status' => ['sometimes', 'in:' . self::storableStatusValues()],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
            'menit_keterlambatan' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($data['status'])) {
            $data['status'] = AttendanceStatus::fromSlug($data['status'])->value;
        }

        $attendance->update($data);
        $attendance->load(['student.user', 'classroom']);

        return $this->success($attendance, 'Absensi berhasil diperbarui');
    }

    /**
     * Get attendance summary statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
        ]);

        $query = StudentAttendance::query()
            ->whereHas('student', fn($q) => $q->visibleTo($request->user()))
            ->betweenDates($data['from_date'], $data['to_date'])
            ->when($data['classroom_id'] ?? null, fn($q, $id) => $q->forClassroom($id))
            ->when($data['student_id'] ?? null, fn($q, $id) => $q->forStudent($id));

        $stats = $query->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $lateStats = $query->where('menit_keterlambatan', '>', 0)
            ->select(
                DB::raw('COUNT(*) as total_late'),
                DB::raw('SUM(menit_keterlambatan) as total_minutes'),
                DB::raw('AVG(menit_keterlambatan) as avg_minutes')
            )
            ->first();

        $total = array_sum($stats);
        $byStatus = AttendanceStatus::summaryFromRaw($stats);

        return $this->success([
            'period' => [
                'from' => $data['from_date'],
                'to' => $data['to_date'],
            ],
            'total_records' => $total,
            'by_status' => [
                'hadir' => $byStatus['hadir'],
                'sakit' => $byStatus['sakit'],
                'izin' => $byStatus['izin'],
                'tanpa_keterangan' => $byStatus['alfa'],
                'alfa' => $byStatus['alfa'],
            ],
            'percentages' => [
                'hadir' => $total > 0 ? round($byStatus['hadir'] / $total * 100, 2) : 0,
                'tidak_hadir' => $total > 0 ? round($byStatus['alfa'] / $total * 100, 2) : 0,
            ],
            'lateness' => [
                'total_late' => $lateStats->total_late ?? 0,
                'total_minutes' => $lateStats->total_minutes ?? 0,
                'avg_minutes' => round($lateStats->avg_minutes ?? 0, 1),
            ],
        ]);
    }

    /**
     * Get students with consecutive absences
     */
    public function consecutiveAbsences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_days' => ['integer', 'min:2', 'max:30'],
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
        ]);

        $minDays = $data['min_days'] ?? 3;

        // Get students with alfa status in recent days
        $recentDate = now()->subDays($minDays + 7)->toDateString();

        $query = StudentAttendance::with(['student.user', 'classroom'])
            ->whereHas('student', fn($q) => $q->visibleTo($request->user()))
            ->whereIn('status', [AttendanceStatus::Alfa->value, AttendanceStatus::TanpaKeterangan->value])
            ->where('attendance_date', '>=', $recentDate)
            ->when($data['classroom_id'] ?? null, fn($q, $id) => $q->forClassroom($id));

        $absences = $query->orderBy('student_id')
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('student_id');

        $results = [];

        foreach ($absences as $studentId => $studentAbsences) {
            // Check for consecutive days
            $consecutive = $this->findConsecutiveDays($studentAbsences);

            if ($consecutive >= $minDays) {
                $firstAbsence = $studentAbsences->first();
                $results[] = [
                    'student_id' => $studentId,
                    'nis' => $firstAbsence->student?->nis,
                    'name' => $firstAbsence->student?->user?->full_name,
                    'classroom' => $firstAbsence->classroom?->name,
                    'consecutive_days' => $consecutive,
                    'last_attendance' => $studentAbsences->last()->attendance_date,
                ];
            }
        }

        // Sort by consecutive days descending
        usort($results, fn($a, $b) => $b['consecutive_days'] <=> $a['consecutive_days']);

        return $this->success([
            'min_days' => $minDays,
            'count' => count($results),
            'students' => $results,
        ]);
    }

    /**
     * Get top late students
     */
    public function topLate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['integer', 'min:5', 'max:50'],
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
        ]);

        $limit = $data['limit'] ?? 10;

        $students = Student::visibleTo($request->user())
            ->with('user')
            ->select('students.*')
            ->when($data['classroom_id'] ?? null, function ($q, $classroomId) {
                $q->whereHas('enrollments', function ($eq) use ($classroomId) {
                    $eq->where('classroom_id', $classroomId)->where('status', 'active');
                });
            })
            ->orderByDesc('poin_pelanggaran')
            ->limit($limit)
            ->get();

        $results = $students->map(fn($student) => [
            'student_id' => $student->id,
            'nis' => $student->nis,
            'name' => $student->user?->full_name,
            'poin_pelanggaran' => $student->poin_pelanggaran,
        ]);

        return $this->success($results);
    }

    /**
     * Calculate summary from results array
     */
    /**
     * Pastikan user boleh mengakses roster kelas ini (R3/R4).
     *
     * Role administratif bebas; guru/wali_kelas hanya kelas diampu;
     * role lain (siswa, orang tua) ditolak karena roster kelas bukan
     * cakupan mereka.
     */
    private function authorizeClassroomAccess($user, string $classroomId): void
    {
        $roles = $user->getRoleNames();

        if ($roles->intersect(Student::ALL_ACCESS_ROLES)->isNotEmpty()) {
            return;
        }

        if ($roles->intersect(['guru', 'wali_kelas'])->isNotEmpty()
            && in_array($classroomId, $user->teachingClassroomIds(), true)) {
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke kelas ini.');
    }

    private function calculateSummary(array $results): array
    {
        $rawCounts = [];
        $belumScan = 0;

        foreach ($results as $result) {
            $status = $result['status'];
            if ($status === AttendanceStatus::BelumScan->value) {
                $belumScan++;
                continue;
            }
            $rawCounts[$status] = ($rawCounts[$status] ?? 0) + 1;
        }

        $summary = AttendanceStatus::summaryFromRaw($rawCounts);
        $summary['total'] = count($results);
        $summary['belum_scan'] = $belumScan;

        return $summary;
    }

    /**
     * Query param `status` datang dalam kosakata Indonesia (dropdown filter
     * frontend); terjemahkan ke nilai DB (Inggris) sebelum difilter.
     */
    private function applyStatusFilter($query, string $status)
    {
        $dbValues = match ($status) {
            'hadir' => [AttendanceStatus::Hadir->value, 'late'],
            'sakit' => [AttendanceStatus::Sakit->value],
            'izin' => [AttendanceStatus::Izin->value],
            'tanpa_keterangan' => [AttendanceStatus::TanpaKeterangan->value],
            'alfa' => [AttendanceStatus::Alfa->value],
            default => [$status],
        };

        return $query->whereIn('status', $dbValues);
    }

    /**
     * Comma-separated allow-list untuk validasi `status` di request —
     * memakai slug Indonesia (kontrak wire dua arah: daily() mengeluarkan
     * slug, storeBulk/update menerima slug), diturunkan dari enum supaya
     * tidak bisa menyimpang lagi. Terjemahan ke nilai DB (Inggris) terjadi
     * lewat AttendanceStatus::fromSlug() sebelum ditulis.
     */
    private static function storableStatusValues(): string
    {
        return implode(',', AttendanceStatus::storableSlugs());
    }

    /**
     * Find consecutive absence days
     */
    private function findConsecutiveDays($absences): int
    {
        if ($absences->isEmpty()) {
            return 0;
        }

        $dates = $absences->pluck('attendance_date')->map(fn($d) => $d->toDateString())->sort()->values();

        $maxConsecutive = 1;
        $currentConsecutive = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            $prevDate = \Carbon\Carbon::parse($dates[$i - 1]);
            $currDate = \Carbon\Carbon::parse($dates[$i]);

            if ($prevDate->addDay()->equalTo($currDate)) {
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                $currentConsecutive = 1;
            }
        }

        return $maxConsecutive;
    }
}
