<?php

use App\Domain\Attendance\Enums\LeaveStatus;
use App\Domain\Attendance\Enums\LeaveType;
use App\Domain\Attendance\Services\LeaveApprovalService;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;

/**
 * Regression coverage for ATTENDANCE-PLAN.md finding A1: `student_attendances.status`
 * has a Postgres CHECK constraint restricted to English values, but the write paths
 * below used to submit/validate Indonesian literals — these should have failed with
 * a constraint violation before the fix. Nothing previously exercised these paths
 * (finding A9), so these tests run against the real Postgres test DB (phpunit.xml)
 * to genuinely prove the constraint is satisfied, not just sqlite-permissive.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.absensi', 'admin', 'admin');
});

test('storeBulk menerima slug Indonesia dan menyimpan nilai DB Inggris', function (string $slug, string $dbValue) {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/bulk', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
            'attendances' => [
                ['student_id' => $this->amir->id, 'status' => $slug],
            ],
        ])
        ->assertOk();

    expect(StudentAttendance::where('student_id', $this->amir->id)
        ->whereDate('attendance_date', now()->toDateString())
        ->value('status'))->toBe($dbValue);
})->with([
    ['hadir', 'present'],
    ['sakit', 'sick'],
    ['izin', 'permitted'],
    ['tanpa_keterangan', 'absent'],
    ['alfa', 'alpha'],
]);

test('storeBulk menolak nilai DB mentah (bahasa Inggris) di request', function () {
    // Kontrak wire memakai slug Indonesia; nilai Inggris hanya hidup di DB.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/bulk', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
            'attendances' => [
                ['student_id' => $this->amir->id, 'status' => 'present'],
            ],
        ])
        ->assertStatus(422);

    expect(StudentAttendance::where('student_id', $this->amir->id)->exists())->toBeFalse();
});

test('update menerima slug Indonesia dan menyimpan nilai DB Inggris', function () {
    recordAttendanceToday($this->amir, $this->classroomA, 'present');
    $attendance = StudentAttendance::where('student_id', $this->amir->id)->first();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/students/{$attendance->id}", [
            'status' => 'sakit',
        ])
        ->assertOk();

    expect($attendance->fresh()->status)->toBe('sick');
});

test('scan check-in menulis status present', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => $this->amir->unique_code,
            'waktu' => 'masuk',
        ])
        ->assertOk();

    expect(StudentAttendance::where('student_id', $this->amir->id)
        ->whereDate('attendance_date', now()->toDateString())
        ->value('status'))->toBe('present');
});

test('LeaveApprovalService::approve mengisi status sick/permitted untuk siswa', function () {
    // AttendanceAuditLog::log() mengisi tenant_id lewat auth()->user() (trait
    // BelongsToTenant) — perlu autentikasi aktif walau service dipanggil langsung.
    $this->actingAs($this->admin, 'sanctum');

    $service = app(LeaveApprovalService::class);

    $sickPermission = LeavePermission::create([
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => LeaveType::Sakit,
        'alasan' => 'Demam',
        'status' => LeaveStatus::Pending,
    ]);

    $result = $service->approve($sickPermission, $this->admin->id);

    expect($result['success'])->toBeTrue()
        ->and(StudentAttendance::where('student_id', $this->amir->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->value('status'))->toBe('sick');

    $izinPermission = LeavePermission::create([
        'tenant_id' => $this->tenantId,
        'student_id' => $this->budi->id,
        'tanggal_mulai' => now()->addDay()->toDateString(),
        'tanggal_selesai' => now()->addDay()->toDateString(),
        'tipe_izin' => LeaveType::Izin,
        'alasan' => 'Acara keluarga',
        'status' => LeaveStatus::Pending,
    ]);

    $result = $service->approve($izinPermission, $this->admin->id);

    expect($result['success'])->toBeTrue()
        ->and(StudentAttendance::where('student_id', $this->budi->id)
            ->whereDate('attendance_date', now()->addDay()->toDateString())
            ->value('status'))->toBe('permitted');
});
