<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 1 ROLE-ACCESS-PLAN.md: scope visibleTo (R3+R4) & StudentPolicy (R7).
 * Dunia uji dibangun oleh setupSchoolWorld() di tests/WorldHelpers.php.
 */
beforeEach(fn () => setupSchoolWorld());

// ---------- R4: cakupan daftar ----------

test('admin melihat semua siswa', function () {
    $admin = makeUser('admin.uji', 'admin', 'admin');

    expect(visibleNisFor($admin))->toBe(collect([$this->amir->nis, $this->budi->nis])->sort()->values()->all());
});

test('guru tanpa penugasan tidak melihat siswa sama sekali', function () {
    $guru = makeUser('guru.kosong', 'guru', 'teacher');

    expect(visibleNisFor($guru))->toBe([]);
});

test('guru dengan penugasan manual hanya melihat siswa kelasnya', function () {
    $guru = makeUser('guru.manual', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    expect(visibleNisFor($guru))->toBe([$this->amir->nis]);
});

test('wali kelas melihat siswa kelas perwaliannya', function () {
    $wali = makeUser('wali.a', 'wali_kelas', 'teacher');
    DB::table('classrooms')->where('id', $this->classroomA)
        ->update(['homeroom_teacher_id' => $wali->id]);

    expect(visibleNisFor($wali))->toBe([$this->amir->nis]);
});

test('guru dengan jadwal aktif melihat siswa kelas yang dijadwalkan', function () {
    $guru = makeUser('guru.jadwal', 'guru', 'teacher');
    makeScheduleFor($guru, $this->classroomB, $this->activeYearId);

    expect(visibleNisFor($guru))->toBe([$this->budi->nis]);
});

test('jadwal pada tahun ajaran non-aktif tidak memberi akses', function () {
    $guru = makeUser('guru.lama', 'guru', 'teacher');
    makeScheduleFor($guru, $this->classroomB, $this->inactiveYearId);

    expect(visibleNisFor($guru))->toBe([]);
});

test('penugasan manual pada tahun ajaran non-aktif tidak memberi akses', function () {
    $guru = makeUser('guru.arsip', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomB, $this->inactiveYearId);

    expect(visibleNisFor($guru))->toBe([]);
});

test('siswa hanya melihat dirinya sendiri', function () {
    expect(visibleNisFor($this->amirUser))->toBe([$this->amir->nis]);
});

test('orang tua hanya melihat anaknya', function () {
    $ortu = makeUser('ortu.amir', 'orang_tua', 'parent');
    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'user_id' => $ortu->id,
        'relationship' => 'father',
        'name' => 'Ayah Amir',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(visibleNisFor($ortu))->toBe([$this->amir->nis]);
});

// ---------- API index: cakupan lewat HTTP ----------

test('API daftar siswa untuk guru hanya berisi kelas diampu', function () {
    $guru = makeUser('guru.api', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $response = $this->actingAs($guru, 'sanctum')->getJson('/api/v1/students');

    $response->assertOk();
    $nisList = collect($response->json('data.data') ?? $response->json('data'))->pluck('nis');
    expect($nisList->all())->toBe([$this->amir->nis]);
});

// ---------- R7: policy detail ----------

test('guru dibolehkan melihat detail siswa kelas diampu', function () {
    $guru = makeUser('guru.detail', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    expect($guru->can('view', $this->amir))->toBeTrue();
});

test('guru ditolak melihat detail siswa kelas lain', function () {
    $guru = makeUser('guru.tolak', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    expect($guru->can('view', $this->budi))->toBeFalse();
});

test('API detail siswa kelas lain mengembalikan 403', function () {
    $guru = makeUser('guru.403', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $this->actingAs($guru, 'sanctum')
        ->getJson('/api/v1/students/' . $this->budi->id)
        ->assertForbidden();
});

test('siswa ditolak melihat detail siswa lain via API', function () {
    $this->actingAs($this->amirUser, 'sanctum')
        ->getJson('/api/v1/students/' . $this->budi->id)
        ->assertForbidden();
});

test('guru tidak boleh mengubah siswa meski dalam kelasnya (tanpa permission update)', function () {
    $guru = makeUser('guru.edit', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    expect($guru->can('update', $this->amir))->toBeFalse();
});

test('wali kelas boleh mengubah siswa perwaliannya tapi tidak siswa kelas lain', function () {
    $wali = makeUser('wali.edit', 'wali_kelas', 'teacher');
    DB::table('classrooms')->where('id', $this->classroomA)
        ->update(['homeroom_teacher_id' => $wali->id]);

    expect($wali->can('update', $this->amir))->toBeTrue()
        ->and($wali->can('update', $this->budi))->toBeFalse();
});

// ---------- Absensi ----------

test('daftar absensi untuk guru hanya berisi siswa kelas diampu', function () {
    $guru = makeUser('guru.absen', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    recordAttendanceToday($this->amir, $this->classroomA);
    recordAttendanceToday($this->budi, $this->classroomB);

    $response = $this->actingAs($guru, 'sanctum')->getJson('/api/v1/attendance/students');

    $response->assertOk();
    $studentIds = collect($response->json('data.data'))->pluck('student_id')->unique()->values();
    expect($studentIds->all())->toBe([$this->amir->id]);
});

test('roster harian kelas yang tidak diampu ditolak 403', function () {
    $guru = makeUser('guru.roster', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $this->actingAs($guru, 'sanctum')
        ->getJson('/api/v1/attendance/students/daily?classroom_id=' . $this->classroomB . '&date=' . now()->toDateString())
        ->assertForbidden();
});
