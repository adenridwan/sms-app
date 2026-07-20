<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase G3 TEACHER-MODULE-PLAN.md: wali kelas & guru pengampu ditata dari
 * menu Kelas (bukan Data Guru), supaya tabel `teachers` tidak berubah tiap
 * pergantian tahun ajaran. Efeknya harus langsung terasa di scope R4
 * (ROLE-ACCESS-PLAN.md) — guru yang ditugaskan otomatis melihat siswa
 * kelas itu.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.kelas', 'admin', 'admin');
});

/**
 * Guru dengan record `teachers` sungguhan — dibutuhkan karena
 * syncTeachers() memvalidasi teacher_ids terhadap teachers.user_id
 * (konsisten dengan pemilih di frontend, yang hanya menawarkan guru
 * sungguhan dari teachersApi.list()).
 */
function makeTeacherWithRecord(string $username, string $role): User
{
    $user = makeUser($username, $role, 'teacher');

    DB::table('teachers')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'join_date' => '2020-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

// ---------- wali kelas: eager loading & validasi dobel ----------

test('daftar kelas memuat wali kelas tanpa error lazy-loading', function () {
    $wali = makeTeacherWithRecord('wali.eager', 'wali_kelas');
    DB::table('classrooms')->where('id', $this->classroomA)
        ->update(['homeroom_teacher_id' => $wali->id]);

    $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/academic/classrooms');

    $response->assertOk();
    $names = collect($response->json('data.data'))->pluck('homeroom_teacher.name')->filter();
    expect($names)->not->toBeEmpty();
});

test('menetapkan wali kelas via update berhasil', function () {
    $wali = makeTeacherWithRecord('wali.set', 'wali_kelas');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}", [
            'homeroom_teacher_id' => $wali->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.homeroom_teacher_id', $wali->id);
});

test('guru yang sudah wali kelas lain di tahun ajaran sama ditolak 422', function () {
    $wali = makeTeacherWithRecord('wali.dobel', 'wali_kelas');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}", ['homeroom_teacher_id' => $wali->id])
        ->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomB}", ['homeroom_teacher_id' => $wali->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('homeroom_teacher_id');
});

test('guru yang jadi wali di tahun ajaran BERBEDA tidak dianggap bentrok', function () {
    $wali = makeTeacherWithRecord('wali.tahunlain', 'wali_kelas');
    $classroomLama = makeClassroom($this->gradeLevelId, 'X Lama', $this->inactiveYearId);

    DB::table('classrooms')->where('id', $classroomLama)
        ->update(['homeroom_teacher_id' => $wali->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}", ['homeroom_teacher_id' => $wali->id])
        ->assertOk();
});

test('menetapkan wali kelas langsung membuka scope R4 untuk guru itu', function () {
    $wali = makeTeacherWithRecord('wali.scope', 'wali_kelas');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}", ['homeroom_teacher_id' => $wali->id])
        ->assertOk();

    expect(visibleNisFor($wali->fresh()))->toBe([$this->amir->nis]);
});

// ---------- guru pengampu: sinkronisasi ----------

test('sinkron guru pengampu menambahkan penugasan baru', function () {
    $guru = makeTeacherWithRecord('guru.pengampu1', 'guru');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", [
            'teacher_ids' => [$guru->id],
        ]);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$guru->id]);
    expect(DB::table('teacher_classrooms')
        ->where('classroom_id', $this->classroomA)
        ->where('teacher_id', $guru->id)
        ->exists())->toBeTrue();
});

test('sinkron guru pengampu langsung membuka scope R4', function () {
    $guru = makeTeacherWithRecord('guru.pengampu2', 'guru');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", ['teacher_ids' => [$guru->id]])
        ->assertOk();

    expect(visibleNisFor($guru->fresh()))->toBe([$this->amir->nis]);
});

test('menghapus guru dari daftar pengampu menghilangkan akses', function () {
    $guru = makeTeacherWithRecord('guru.pengampu3', 'guru');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", ['teacher_ids' => [$guru->id]])
        ->assertOk();
    expect(visibleNisFor($guru->fresh()))->toBe([$this->amir->nis]);

    // sinkron ulang tanpa guru ini -> harus lepas
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", ['teacher_ids' => []])
        ->assertOk();

    expect(visibleNisFor($guru->fresh()))->toBe([]);
    expect(DB::table('teacher_classrooms')
        ->where('classroom_id', $this->classroomA)
        ->where('teacher_id', $guru->id)
        ->exists())->toBeFalse();
});

test('GET guru pengampu mengembalikan daftar yang sudah ditugaskan', function () {
    $guruA = makeTeacherWithRecord('guru.getA', 'guru');
    $guruB = makeTeacherWithRecord('guru.getB', 'guru');

    $this->actingAs($this->admin, 'sanctum')->putJson(
        "/api/v1/academic/classrooms/{$this->classroomA}/teachers",
        ['teacher_ids' => [$guruA->id, $guruB->id]]
    )->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers");

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$guruA->id, $guruB->id])->sort()->values()->all());
});

test('sinkron pengampu hanya berlaku untuk tahun ajaran kelas itu (tidak menyentuh tahun lain)', function () {
    $guru = makeTeacherWithRecord('guru.tahunscope', 'guru');
    assignClassroom($guru, $this->classroomA, $this->inactiveYearId);

    $this->actingAs($this->admin, 'sanctum')->putJson(
        "/api/v1/academic/classrooms/{$this->classroomA}/teachers",
        ['teacher_ids' => []]
    )->assertOk();

    // penugasan di tahun non-aktif tetap ada, tidak ikut dihapus
    expect(DB::table('teacher_classrooms')
        ->where('classroom_id', $this->classroomA)
        ->where('academic_year_id', $this->inactiveYearId)
        ->where('teacher_id', $guru->id)
        ->exists())->toBeTrue();
});

test('guru tanpa izin classrooms.manage ditolak mengubah pengampu', function () {
    $guru = makeUser('guru.noperm', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", ['teacher_ids' => []])
        ->assertForbidden();
});

test('id yang bukan guru ditolak 422 saat sinkron pengampu', function () {
    $siswa = $this->amirUser;

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", ['teacher_ids' => [$siswa->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('teacher_ids.0');
});

/**
 * Ditemukan lewat pengujian manual: seorang guru berhasil masuk
 * teacher_classrooms lewat jalur lama (sebelum ada record `teachers`
 * sungguhan — persis pola demo guru1/guru2 di ROLE-ACCESS-PLAN.md R6).
 * Baris "yatim" itu tetap terkirim balik utuh oleh GET .../teachers,
 * membuat PUT berikutnya SELALU gagal 422 "teacher_ids.0 is invalid"
 * walau admin cuma menambah/melepas guru lain yang valid — dialog Kelola
 * Pengampu jadi buntu tanpa cara memperbaikinya dari UI.
 */
test('penugasan yatim (tanpa record teachers) tidak ikut disodorkan balik dan tidak menghalangi sinkronisasi', function () {
    $yatim = makeUser('guru.yatim', 'guru', 'teacher'); // sengaja TANPA makeTeacherWithRecord()
    DB::table('teacher_classrooms')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'teacher_id' => $yatim->id,
        'classroom_id' => $this->classroomA,
        'academic_year_id' => $this->activeYearId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers");
    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($yatim->id);

    $guruBaru = makeTeacherWithRecord('guru.pengganti.yatim', 'guru');
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$this->classroomA}/teachers", ['teacher_ids' => [$guruBaru->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $guruBaru->id);

    // baris yatim ikut tersapu oleh sinkronisasi penuh (whereNotIn)
    expect(DB::table('teacher_classrooms')
        ->where('classroom_id', $this->classroomA)
        ->where('teacher_id', $yatim->id)
        ->exists())->toBeFalse();
});
