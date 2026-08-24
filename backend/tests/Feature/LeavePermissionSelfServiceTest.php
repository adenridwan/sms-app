<?php

use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * LeavePermissionController tidak pernah punya scoping sama sekali —
 * guru/siswa bisa lihat, ajukan atas nama, setujui, dan hapus perizinan
 * siapa saja. Sekaligus dua bug ikut ditemukan lewat halaman
 * /attendance/permissions yang blank di browser:
 *
 * 1. index()/store()/show()/update() eager-load `student.user`/`teacher.user`
 *    tanpa `.profile` — User::full_name (dan first_name/last_name di
 *    baliknya) dirakit dari relasi profile, jadi begitu ada baris
 *    perizinan dengan siswa/guru bertaut user, serialisasi JSON-nya
 *    melempar LazyLoadingViolationException (pola yang sama seperti
 *    StudentAttendanceController, lihat CLAUDE.md soal LoginLogService).
 * 2. leavePermissionApi.list() ditipekan sebagai PaginatedResponse<T> logis
 *    response.data.data = T[] — padahal ApiController::success() selalu
 *    membungkus data dengan {success,message,data}, jadi response.data.data
 *    sungguhnya adalah OBJEK paginator (bukan array). permissions.map()
 *    di Index.tsx lalu melempar TypeError saat render → halaman blank,
 *    independen dari ada/tidaknya baris data.
 */
function makeTeacherWithUser(string $username): array
{
    $user = makeUser($username, 'guru', 'teacher');
    $teacher = Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);

    return [$teacher, $user];
}

beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.izin', 'admin', 'admin');
});

test('index mengembalikan paginator utuh (bukan array kosong) dan tidak error dengan data yang bertaut user', function () {
    [$teacher, $teacherUser] = makeTeacherWithUser('guru.izin.index');
    LeavePermission::create([
        'tenant_id' => test()->tenantId,
        'teacher_id' => $teacher->id,
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/permissions')
        ->assertOk();

    // Bentuk envelope: {success,message,data:{data:[...],current_page,...}}
    expect($response->json('data.data'))->toBeArray();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.teacher.user.full_name'))->not->toBeNull();
});

test('guru hanya melihat pengajuan izinnya sendiri di index', function () {
    [$teacherA, $teacherUserA] = makeTeacherWithUser('guru.izin.a');
    [$teacherB, $teacherUserB] = makeTeacherWithUser('guru.izin.b');

    LeavePermission::create([
        'tenant_id' => test()->tenantId, 'teacher_id' => $teacherA->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit', 'status' => 'pending',
    ]);
    LeavePermission::create([
        'tenant_id' => test()->tenantId, 'teacher_id' => $teacherB->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'izin', 'status' => 'pending',
    ]);

    $response = $this->actingAs($teacherUserA, 'sanctum')
        ->getJson('/api/v1/attendance/permissions')
        ->assertOk();

    $teacherIds = collect($response->json('data.data'))->pluck('teacher_id');
    expect($teacherIds)->toContain($teacherA->id)
        ->and($teacherIds)->not->toContain($teacherB->id);
});

test('siswa hanya melihat pengajuan izinnya sendiri di index', function () {
    LeavePermission::create([
        'tenant_id' => test()->tenantId, 'student_id' => test()->amir->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit', 'status' => 'pending',
    ]);
    [$otherStudent] = makeStudent('siswa.izin.lain', test()->classroomA);
    LeavePermission::create([
        'tenant_id' => test()->tenantId, 'student_id' => $otherStudent->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'izin', 'status' => 'pending',
    ]);

    $response = $this->actingAs(test()->amirUser, 'sanctum')
        ->getJson('/api/v1/attendance/permissions')
        ->assertOk();

    $studentIds = collect($response->json('data.data'))->pluck('student_id');
    expect($studentIds)->toContain(test()->amir->id)
        ->and($studentIds)->not->toContain($otherStudent->id);
});

test('guru mengajukan izin: teacher_id kiriman diabaikan, diganti identitas login sendiri', function () {
    [, $teacherUserA] = makeTeacherWithUser('guru.izin.store.a');
    [$teacherB] = makeTeacherWithUser('guru.izin.store.b');

    $response = $this->actingAs($teacherUserA, 'sanctum')
        ->postJson('/api/v1/attendance/permissions', [
            // Mencoba mengajukan izin ATAS NAMA guru lain.
            'teacher_id' => $teacherB->id,
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'tipe_izin' => 'sakit',
            'alasan' => 'Demam',
        ])
        ->assertCreated();

    $ownTeacherId = $teacherUserA->fresh()->teacher->id;
    expect($response->json('data.teacher_id'))->toBe($ownTeacherId);
    expect($response->json('data.teacher_id'))->not->toBe($teacherB->id);
});

test('siswa mengajukan izin untuk dirinya sendiri berhasil tanpa mengirim student_id', function () {
    $response = $this->actingAs(test()->amirUser, 'sanctum')
        ->postJson('/api/v1/attendance/permissions', [
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'tipe_izin' => 'izin',
            'alasan' => 'Acara keluarga',
        ])
        ->assertCreated();

    expect($response->json('data.student_id'))->toBe(test()->amir->id);
});

test('guru tidak bisa menyetujui perizinan (miliknya sendiri ataupun orang lain)', function () {
    [$teacher, $teacherUser] = makeTeacherWithUser('guru.izin.approve');
    $permission = LeavePermission::create([
        'tenant_id' => test()->tenantId, 'teacher_id' => $teacher->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit', 'status' => 'pending',
    ]);

    $this->actingAs($teacherUser, 'sanctum')
        ->postJson("/api/v1/attendance/permissions/{$permission->id}/approve")
        ->assertForbidden();

    expect($permission->fresh()->status->value)->toBe('pending');
});

test('admin tetap bisa menyetujui perizinan siapa saja', function () {
    [$teacher] = makeTeacherWithUser('guru.izin.approve.admin');
    $permission = LeavePermission::create([
        'tenant_id' => test()->tenantId, 'teacher_id' => $teacher->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit', 'status' => 'pending',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/attendance/permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->status->value)->toBe('approved');
});

test('guru tidak bisa melihat atau menghapus perizinan guru lain', function () {
    [, $teacherUserA] = makeTeacherWithUser('guru.izin.owner.a');
    [$teacherB] = makeTeacherWithUser('guru.izin.owner.b');
    $permissionB = LeavePermission::create([
        'tenant_id' => test()->tenantId, 'teacher_id' => $teacherB->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit', 'status' => 'pending',
    ]);

    $this->actingAs($teacherUserA, 'sanctum')
        ->getJson("/api/v1/attendance/permissions/{$permissionB->id}")
        ->assertForbidden();

    $this->actingAs($teacherUserA, 'sanctum')
        ->deleteJson("/api/v1/attendance/permissions/{$permissionB->id}")
        ->assertForbidden();

    expect(LeavePermission::find($permissionB->id))->not->toBeNull();
});

test('guru bisa menghapus pengajuan izinnya sendiri selama masih pending', function () {
    [$teacher, $teacherUser] = makeTeacherWithUser('guru.izin.owner.delete');
    $permission = LeavePermission::create([
        'tenant_id' => test()->tenantId, 'teacher_id' => $teacher->id,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
        'tipe_izin' => 'sakit', 'status' => 'pending',
    ]);

    $this->actingAs($teacherUser, 'sanctum')
        ->deleteJson("/api/v1/attendance/permissions/{$permission->id}")
        ->assertOk();

    expect(LeavePermission::find($permission->id))->toBeNull();
});
