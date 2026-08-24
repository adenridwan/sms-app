<?php

use App\Domain\Setting\Services\MenuVisibilityService;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Absensi Saya" — halaman self-service untuk guru & pegawai (bukan admin
 * yang lihat orang lain). Semua endpoint di MyAttendanceController SELALU
 * discope ke auth()->id(), dan menu "Absensi Saya" hanya boleh tampil untuk
 * role yang benar-benar presensi (attendance.check-in), bukan semua role
 * yang bisa lihat grup Absensi.
 */
function makeTeacherUser(string $username, ?string $rfidCode = null): array
{
    $user = makeUser($username, 'guru', 'teacher');
    $teacher = Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
        'rfid_code' => $rfidCode,
    ]);

    return [$teacher, $user];
}

function makeStaffUser(string $username): array
{
    $user = makeUser($username, 'tata_usaha', 'staff');
    $staff = Staff::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'employee_id' => 'EMP-' . random_int(1000, 9999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);

    return [$staff, $user];
}

function insertEmployeeAttendance(string $userId, string $date, string $status, array $extra = []): void
{
    DB::table('employee_attendances')->insert(array_merge([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'user_id' => $userId,
        'attendance_date' => $date,
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));
}

beforeEach(function () {
    setupSchoolWorld();
});

test('today mengembalikan status, jam, QR, dan Ref ID (rfid_code) guru dari record miliknya sendiri', function () {
    [$teacher, $teacherUser] = makeTeacherUser('guru.me', 'RF-TESTMYATTENDANCE');
    insertEmployeeAttendance($teacherUser->id, now()->toDateString(), 'present', [
        'check_in_time' => '06:50:00',
        'late_minutes' => 0,
    ]);

    $response = $this->actingAs($teacherUser, 'sanctum')
        ->getJson('/api/v1/attendance/me/today')
        ->assertOk();

    $data = $response->json('data');

    expect($data['status'])->toBe('present');
    expect($data['check_in_time'])->toBe('06:50');
    expect($data['identity_number'])->toBe($teacher->nip);
    expect($data['qr']['available'])->toBeTrue();
    expect($data['qr']['teacher_id'])->toBe($teacher->id);
    expect($data['rfid_code'])->toBe('RF-TESTMYATTENDANCE');
});

test('today mengembalikan rfid_code null untuk guru yang belum ditautkan kartu RFID', function () {
    [, $teacherUser] = makeTeacherUser('guru.me.norfid');

    $response = $this->actingAs($teacherUser, 'sanctum')
        ->getJson('/api/v1/attendance/me/today')
        ->assertOk();

    expect($response->json('data.rfid_code'))->toBeNull();
});

test('today mengembalikan rfid_code null untuk staf (belum didukung, sama seperti QR)', function () {
    [, $staffUser] = makeStaffUser('tu.me.norfid');

    $response = $this->actingAs($staffUser, 'sanctum')
        ->getJson('/api/v1/attendance/me/today')
        ->assertOk();

    expect($response->json('data.rfid_code'))->toBeNull();
});

test('today untuk staf tanpa record hari ini menampilkan belum_scan dan QR tidak tersedia', function () {
    // Dipatok ke hari kerja (Senin) supaya tidak bergantung pada hari
    // sungguhan saat test dijalankan — kalau tidak, hasilnya jadi 'libur'
    // ketika kebetulan dijalankan di akhir pekan.
    \Illuminate\Support\Carbon::setTestNow('2026-08-24');

    [$staff, $staffUser] = makeStaffUser('tu.me');

    $response = $this->actingAs($staffUser, 'sanctum')
        ->getJson('/api/v1/attendance/me/today')
        ->assertOk();

    \Illuminate\Support\Carbon::setTestNow();

    $data = $response->json('data');

    expect($data['status'])->toBe('belum_scan');
    expect($data['identity_number'])->toBe($staff->employee_id);
    expect($data['qr']['available'])->toBeFalse();
    expect($data['qr']['teacher_id'])->toBeNull();
});

test('today tidak pernah membocorkan data user lain', function () {
    [, $teacherUserA] = makeTeacherUser('guru.me.a');
    [, $teacherUserB] = makeTeacherUser('guru.me.b');
    insertEmployeeAttendance($teacherUserB->id, now()->toDateString(), 'sick', ['notes' => 'rahasia B']);

    $response = $this->actingAs($teacherUserA, 'sanctum')
        ->getJson('/api/v1/attendance/me/today')
        ->assertOk();

    // A tidak punya record hari ini — tidak boleh ikut kebaca record B.
    expect($response->json('data.status'))->not->toBe('sick');
});

test('history menghitung ringkasan bulan dengan benar dan mengecualikan hari libur', function () {
    [, $teacherUser] = makeTeacherUser('guru.history');

    // Agustus 2026: 1 Sabtu (libur), tanggal 3 Senin present, 4 Selasa sick,
    // 5 Rabu permitted, 6 Kamis terlambat (late_minutes > 0, status present).
    insertEmployeeAttendance($teacherUser->id, '2026-08-03', 'present', ['check_in_time' => '06:50:00']);
    insertEmployeeAttendance($teacherUser->id, '2026-08-04', 'sick');
    insertEmployeeAttendance($teacherUser->id, '2026-08-05', 'permitted');
    insertEmployeeAttendance($teacherUser->id, '2026-08-06', 'present', ['check_in_time' => '08:00:00', 'late_minutes' => 15]);

    $response = $this->actingAs($teacherUser, 'sanctum')
        ->getJson('/api/v1/attendance/me/history?month=2026-08')
        ->assertOk();

    $data = $response->json('data');

    expect($data['summary']['hadir'])->toBe(2);
    expect($data['summary']['sakit'])->toBe(1);
    expect($data['summary']['izin'])->toBe(1);
    expect($data['summary']['telat'])->toBe(1);

    $history = collect($data['history']);
    $aug1 = $history->firstWhere('date', '2026-08-01'); // Sabtu
    expect($aug1['status'])->toBe('libur');

    $aug6 = $history->firstWhere('date', '2026-08-06');
    expect($aug6['late_minutes'])->toBe(15);
});

test('history menolak format bulan yang tidak valid', function () {
    [, $teacherUser] = makeTeacherUser('guru.history.invalid');

    $this->actingAs($teacherUser, 'sanctum')
        ->getJson('/api/v1/attendance/me/history?month=August-2026')
        ->assertStatus(422);
});

test('menu Absensi Saya hanya tampil untuk role yang punya attendance.check-in', function () {
    [, $teacherUser] = makeTeacherUser('guru.menu');
    [, $staffUser] = makeStaffUser('tu.menu');
    $bendahara = makeUser('bendahara.menu', 'bendahara', 'staff');

    $service = app(MenuVisibilityService::class);

    expect($service->visibleKeysFor($teacherUser))->toContain('attendance.me');
    expect($service->visibleKeysFor($staffUser))->toContain('attendance.me');
    expect($service->visibleKeysFor($bendahara))->not->toContain('attendance.me');
});
