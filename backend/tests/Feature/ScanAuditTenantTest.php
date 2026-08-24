<?php

use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceAuditLog;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Regresi: AttendanceAuditLog::log() mengandalkan BelongsToTenant untuk
 * mengisi tenant_id otomatis dari auth()->user()->tenant_id / header
 * X-Tenant-ID — keduanya kosong untuk super_admin (tenant-agnostic) yang
 * belum memilih tenant aktif. Akibatnya INSERT ke attendance_audit_logs
 * melanggar NOT NULL dan seluruh scan absen (QR/RFID, siswa/guru) gagal
 * dengan 500/exception walau data yang mau disimpan valid — persis kasus
 * "scan absen gagal terus" yang dilaporkan lewat QR guru sungguhan.
 *
 * Ditemukan lewat reproduksi manual: super_admin (tenant_id null) men-scan
 * kode guru real di db_production, INSERT attendance_audit_logs gagal
 * "null value in column tenant_id". Sudah pernah kejadian sekali di
 * SendClassAttendanceRecap (lihat komentar di sana) tapi cuma ditambal di
 * satu tempat, bukan di akarnya — jalur scan tetap kena.
 */
beforeEach(function () {
    setupSchoolWorld();

    // tenant_id null meniru super admin sungguhan (UserSeeder) — lihat pola
    // yang sama di RegisterActivationTest.
    $this->superAdmin = User::create([
        'tenant_id' => null,
        'username' => 'superadmin.scan',
        'email' => 'superadmin.scan@sekolah.test',
        'password' => 'password',
        'status' => 'active',
        'user_type' => 'super_admin',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->teacherUser = makeUser('guru.scan.tenant', 'guru', 'teacher');
    $this->teacher = Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $this->teacherUser->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
        'unique_code' => 'TCH-TESTSCANTENANT',
    ]);
});

test('scan guru oleh super_admin tanpa tenant aktif tetap berhasil dan audit log terisi tenant subjek', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => 'TCH-TESTSCANTENANT',
            'waktu' => 'masuk',
        ])
        ->assertOk();

    $attendance = EmployeeAttendance::where('user_id', $this->teacherUser->id)->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->tenant_id)->toBe(test()->tenantId);

    $log = AttendanceAuditLog::where('aksi', 'scan_masuk_guru')
        ->where('record_id', $attendance->id)
        ->first();
    expect($log)->not->toBeNull();
    // Tenant di audit log HARUS tenant si guru (subjek scan), bukan null
    // dan bukan tenant si pemindai (super_admin tidak punya tenant sama sekali).
    expect($log->tenant_id)->toBe(test()->tenantId);
});

test('scan siswa oleh super_admin tanpa tenant aktif tetap berhasil dan audit log terisi tenant subjek', function () {
    test()->amir->update(['unique_code' => 'STU-TESTSCANTENANT']);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => 'STU-TESTSCANTENANT',
            'waktu' => 'masuk',
        ])
        ->assertOk();

    $attendance = StudentAttendance::where('student_id', test()->amir->id)->first();
    expect($attendance)->not->toBeNull();

    $log = AttendanceAuditLog::where('aksi', 'scan_masuk_siswa')
        ->where('record_id', $attendance->id)
        ->first();
    expect($log)->not->toBeNull();
    expect($log->tenant_id)->toBe(test()->tenantId);
});
