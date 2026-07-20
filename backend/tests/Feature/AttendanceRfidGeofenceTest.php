<?php

use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceSetting;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Regression coverage for ATTENDANCE-PLAN.md Fase 2:
 * - A6: rfid_code harus unik lintas tabel students+teachers.
 * - Geofencing: AttendanceSetting.require_location/school_latitude/
 *   school_longitude/location_radius diaktifkan untuk memvalidasi scan.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.rfid', 'admin', 'admin');

    // Guru untuk skenario cross-table clash.
    $this->guruUser = makeUser('guru.rfid', 'guru', 'teacher');
    $this->guru = Teacher::create([
        'tenant_id' => $this->tenantId,
        'user_id' => $this->guruUser->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);
});

test('assign rfid_code baru ke siswa berhasil', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/rfid/students/{$this->amir->id}", [
            'rfid_code' => 'CARD-0001',
        ])
        ->assertOk()
        ->assertJsonPath('data.rfid_code', 'CARD-0001');

    expect($this->amir->fresh()->rfid_code)->toBe('CARD-0001');
});

test('rfid_code yang sudah dipakai siswa lain ditolak', function () {
    $this->amir->update(['rfid_code' => 'CARD-DUP']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/rfid/students/{$this->budi->id}", [
            'rfid_code' => 'CARD-DUP',
        ])
        ->assertStatus(422);

    expect($this->budi->fresh()->rfid_code)->toBeNull();
});

test('rfid_code milik guru tidak boleh dipakai siswa (lintas tabel)', function () {
    $this->guru->update(['rfid_code' => 'CARD-GURU']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/rfid/students/{$this->amir->id}", [
            'rfid_code' => 'CARD-GURU',
        ])
        ->assertStatus(422);
});

test('rfid_code milik siswa tidak boleh dipakai guru (lintas tabel)', function () {
    $this->amir->update(['rfid_code' => 'CARD-SISWA']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/rfid/teachers/{$this->guru->id}", [
            'rfid_code' => 'CARD-SISWA',
        ])
        ->assertStatus(422);
});

test('menyimpan ulang rfid_code miliknya sendiri tidak dianggap konflik', function () {
    $this->amir->update(['rfid_code' => 'CARD-SENDIRI']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/rfid/students/{$this->amir->id}", [
            'rfid_code' => 'CARD-SENDIRI',
        ])
        ->assertOk();
});

test('rfid_code bisa dikosongkan (lepas kartu)', function () {
    $this->amir->update(['rfid_code' => 'CARD-LAMA']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/attendance/rfid/students/{$this->amir->id}", [
            'rfid_code' => null,
        ])
        ->assertOk();

    expect($this->amir->fresh()->rfid_code)->toBeNull();
});

test('scan check-in tanpa require_location tetap sukses tanpa lokasi (default, no regression)', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => $this->amir->unique_code,
            'waktu' => 'masuk',
        ])
        ->assertOk();

    expect(StudentAttendance::where('student_id', $this->amir->id)->exists())->toBeTrue();
});

test('require_location aktif tanpa lokasi ditolak', function () {
    AttendanceSetting::getForTenant($this->tenantId)->update([
        'require_location' => true,
        'school_latitude' => -6.200000,
        'school_longitude' => 106.816666,
        'location_radius' => 100,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => $this->amir->unique_code,
            'waktu' => 'masuk',
        ])
        ->assertStatus(422);

    expect(StudentAttendance::where('student_id', $this->amir->id)->exists())->toBeFalse();
});

test('require_location aktif dengan lokasi dalam radius berhasil', function () {
    AttendanceSetting::getForTenant($this->tenantId)->update([
        'require_location' => true,
        'school_latitude' => -6.200000,
        'school_longitude' => 106.816666,
        'location_radius' => 100,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => $this->amir->unique_code,
            'waktu' => 'masuk',
            'latitude' => -6.200010,
            'longitude' => 106.816670,
        ])
        ->assertOk();

    expect(StudentAttendance::where('student_id', $this->amir->id)->value('status'))->toBe('present');
});

test('require_location aktif dengan lokasi di luar radius ditolak', function () {
    AttendanceSetting::getForTenant($this->tenantId)->update([
        'require_location' => true,
        'school_latitude' => -6.200000,
        'school_longitude' => 106.816666,
        'location_radius' => 100,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', [
            'unique_code' => $this->amir->unique_code,
            'waktu' => 'masuk',
            'latitude' => -6.210000,
            'longitude' => 106.826666,
        ])
        ->assertStatus(422);

    expect($response->json('message'))->toContain('radius');
    expect(StudentAttendance::where('student_id', $this->amir->id)->exists())->toBeFalse();
});
