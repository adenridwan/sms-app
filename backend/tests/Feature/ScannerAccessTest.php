<?php

use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Scanner dipecah jadi dua permission berdasarkan JENIS yang discan (bukan
 * satu attendance.scanner-operate generik untuk semuanya):
 * - attendance.scan-students: admin, tata_usaha, super_admin, DAN guru.
 * - attendance.scan-staff: admin, tata_usaha, super_admin SAJA.
 *
 * Guru butuh Scanner untuk presensi siswa di kelasnya, tapi sengaja TIDAK
 * diberi scan-staff supaya tidak bisa memindai kehadiran guru/pegawai lain
 * — termasuk mencegah guru memindai kode dirinya sendiri lewat Scanner.
 * Halaman /scanner & route group /api/v1/scan* hanya menyaring "boleh scan
 * sesuatu" (salah satu permission); jenis yang boleh discan dicek per-kode
 * di dalam AttendanceScanService::processScan() karena baru diketahui
 * SETELAH kode di-lookup, bukan saat routing.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.scanaccess', 'admin', 'admin');
    $this->tu = makeUser('tu.scanaccess', 'tata_usaha', 'staff');

    $guruUser = makeUser('guru.scanaccess', 'guru', 'teacher');
    $this->guruTeacher = Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $guruUser->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
        'unique_code' => 'TCH-SCANACCESSTEST',
    ]);
    $this->guru = $guruUser;

    test()->amir->update(['unique_code' => 'STU-SCANACCESSTEST']);
});

test('halaman scanner bisa diakses admin, tata_usaha, super_admin, dan guru', function () {
    $this->actingAs($this->admin)->get('/scanner')->assertOk();
    $this->actingAs($this->tu)->get('/scanner')->assertOk();
    $this->actingAs($this->guru)->get('/scanner')->assertOk();
});

test('halaman scanner ditolak untuk siswa (tidak punya scan-students maupun scan-staff)', function () {
    $this->actingAs(test()->amirUser)->get('/scanner')->assertForbidden();
});

test('guru bisa memindai kehadiran siswa', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'STU-SCANACCESSTEST', 'waktu' => 'masuk'])
        ->assertOk();
});

test('guru ditolak memindai kehadiran guru lain (atau dirinya sendiri)', function () {
    expect($this->guru->can('attendance.scan-students'))->toBeTrue();
    expect($this->guru->can('attendance.scan-staff'))->toBeFalse();

    // Kode milik guru lain.
    $response = $this->actingAs($this->guru, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'TCH-SCANACCESSTEST', 'waktu' => 'masuk'])
        ->assertStatus(422);
    expect($response->json('message'))->toContain('tidak memiliki akses');

    // Guru mencoba memindai kode DIRINYA SENDIRI — juga ditolak, sama
    // seperti kode guru lain (tidak ada pengecualian "kalau punya sendiri").
    $this->actingAs($this->guru, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => $this->guruTeacher->unique_code, 'waktu' => 'masuk'])
        ->assertStatus(422);

    // Tidak ada attendance yang tercatat sama sekali dari kedua percobaan itu.
    expect(App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance::where('user_id', $this->guru->id)->exists())->toBeFalse();
});

test('endpoint scan diproses untuk admin dan tata_usaha, baik siswa maupun guru', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'STU-SCANACCESSTEST', 'waktu' => 'masuk'])
        ->assertOk();

    $this->actingAs($this->tu, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'TCH-SCANACCESSTEST', 'waktu' => 'masuk'])
        ->assertOk();
});

test('endpoint scan/bootstrap ditolak untuk siswa', function () {
    $this->actingAs(test()->amirUser, 'sanctum')
        ->getJson('/api/v1/scan/bootstrap')
        ->assertForbidden();
});
