<?php

use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A13 (ATTENDANCE-PLAN.md): TeacherAttendanceController tidak punya scoping
 * sama sekali sebelum ini — guru biasa bisa lihat & edit absensi guru lain.
 * Regresi memastikan guru non-admin hanya melihat/mengedit barisnya
 * sendiri, sementara role admin-tier (Student::ALL_ACCESS_ROLES) tetap
 * bebas penuh.
 */
function makeTeacherWithAttendance(string $username, string $status = 'present'): array
{
    $user = makeUser($username, 'guru', 'teacher');
    $teacher = Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);

    DB::table('employee_attendances')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'attendance_date' => now()->toDateString(),
        'status' => $status,
        'check_in_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$teacher, $user];
}

beforeEach(function () {
    setupSchoolWorld();
    [$this->teacherA, $this->teacherAUser] = makeTeacherWithAttendance('guru.a');
    [$this->teacherB, $this->teacherBUser] = makeTeacherWithAttendance('guru.b');
});

test('guru non-admin hanya melihat barisnya sendiri di daily()', function () {
    $response = $this->actingAs($this->teacherAUser, 'sanctum')
        ->getJson('/api/v1/attendance/teachers/daily?date=' . now()->toDateString())
        ->assertOk();

    $names = collect($response->json('data.teachers'))->pluck('user_id');
    expect($names)->toContain($this->teacherAUser->id)
        ->and($names)->not->toContain($this->teacherBUser->id);
});

test('admin tetap melihat semua guru di daily()', function () {
    $admin = makeUser('admin.scope', 'admin', 'admin');

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/attendance/teachers/daily?date=' . now()->toDateString())
        ->assertOk();

    $userIds = collect($response->json('data.teachers'))->pluck('user_id');
    expect($userIds)->toContain($this->teacherAUser->id)
        ->and($userIds)->toContain($this->teacherBUser->id);
});

test('guru tidak bisa mengedit absensi guru lain', function () {
    $attendanceB = DB::table('employee_attendances')->where('user_id', $this->teacherBUser->id)->first();

    $this->actingAs($this->teacherAUser, 'sanctum')
        ->putJson("/api/v1/attendance/teachers/{$attendanceB->id}", ['notes' => 'diubah paksa'])
        ->assertForbidden();

    expect(DB::table('employee_attendances')->where('id', $attendanceB->id)->value('notes'))->toBeNull();
});

test('guru bisa mengedit absensinya sendiri', function () {
    $attendanceA = DB::table('employee_attendances')->where('user_id', $this->teacherAUser->id)->first();

    $this->actingAs($this->teacherAUser, 'sanctum')
        ->putJson("/api/v1/attendance/teachers/{$attendanceA->id}", ['notes' => 'catatan sendiri'])
        ->assertOk();

    expect(DB::table('employee_attendances')->where('id', $attendanceA->id)->value('notes'))->toBe('catatan sendiri');
});

test('tata_usaha bisa mengedit absensi guru manapun', function () {
    $tu = makeUser('tu.scope', 'tata_usaha', 'staff');
    $attendanceB = DB::table('employee_attendances')->where('user_id', $this->teacherBUser->id)->first();

    $this->actingAs($tu, 'sanctum')
        ->putJson("/api/v1/attendance/teachers/{$attendanceB->id}", ['notes' => 'diperbarui TU'])
        ->assertOk();

    expect(DB::table('employee_attendances')->where('id', $attendanceB->id)->value('notes'))->toBe('diperbarui TU');
});

test('guru tidak bisa memaksa lihat guru lain lewat parameter user_id di index()', function () {
    $response = $this->actingAs($this->teacherAUser, 'sanctum')
        ->getJson('/api/v1/attendance/teachers?user_id=' . $this->teacherBUser->id)
        ->assertOk();

    expect($response->json('data.data'))->toBeEmpty();
});
