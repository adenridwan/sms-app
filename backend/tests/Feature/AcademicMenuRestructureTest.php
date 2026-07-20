<?php

use App\Infrastructure\Persistence\Eloquent\Academic\GradeLevel;
use App\Infrastructure\Persistence\Eloquent\Academic\Major;

/**
 * Penyesuaian menu Kelas/Jurusan (Pengaturan -> Akademik) dan master data
 * Tingkat Kelas (grade_levels.is_active) — permintaan pengguna setelah
 * Fase G3, sebelum G4 (TEACHER-MODULE-PLAN.md).
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.akademik', 'admin', 'admin');
});

// ---------- master data tingkat kelas: is_active ----------

test('daftar tingkat kelas default menyertakan semua status', function () {
    GradeLevel::where('id', $this->gradeLevelId)->update(['is_active' => true]);
    $nonaktif = GradeLevel::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kelas 12 Lama', 'code' => 'XII-LAMA', 'order' => 3, 'is_active' => false,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/academic/grade-levels');

    $response->assertOk();
    $codes = collect($response->json('data.data'))->pluck('code');
    expect($codes)->toContain('X')->toContain('XII-LAMA');
});

test('filter is_active=1 pada tingkat kelas menyembunyikan yang nonaktif', function () {
    GradeLevel::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kelas 12 Lama', 'code' => 'XII-LAMA', 'order' => 3, 'is_active' => false,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/academic/grade-levels?is_active=1');

    $response->assertOk();
    $codes = collect($response->json('data.data'))->pluck('code');
    expect($codes)->toContain('X')->not->toContain('XII-LAMA');
});

test('tingkat kelas baru default aktif dan bisa dinonaktifkan', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/grade-levels', [
            'name' => 'Kelas 11', 'code' => 'XI', 'order' => 2,
        ]);
    $response->assertCreated()->assertJsonPath('data.is_active', true);

    $id = $response->json('data.id');
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/grade-levels/{$id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

test('filter is_active=1 pada jurusan menyembunyikan yang nonaktif (dipakai popup kelas)', function () {
    Major::create(['tenant_id' => $this->tenantId, 'name' => 'IPA', 'code' => 'IPA', 'is_active' => true]);
    Major::create(['tenant_id' => $this->tenantId, 'name' => 'IPS Lama', 'code' => 'IPS-LAMA', 'is_active' => false]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/academic/majors?is_active=1');

    $response->assertOk();
    $codes = collect($response->json('data.data'))->pluck('code');
    expect($codes)->toContain('IPA')->not->toContain('IPS-LAMA');
});

// ---------- rute web: kelas & jurusan pindah ke akademik ----------

test('halaman /academic/classrooms merender 200', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get('/academic/classrooms');
    $response->assertOk();
});

test('halaman /academic/majors merender 200', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get('/academic/majors');
    $response->assertOk();
});

test('halaman /academic/grade-levels merender 200', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get('/academic/grade-levels');
    $response->assertOk();
});

test('rute lama /settings/class-rooms redirect ke /academic/classrooms', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get('/settings/class-rooms');
    $response->assertRedirect('/academic/classrooms');
});

test('rute lama /settings/majors redirect ke /academic/majors', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get('/settings/majors');
    $response->assertRedirect('/academic/majors');
});

test('guru tanpa izin grade-levels.manage ditolak membuat tingkat baru', function () {
    $guru = makeUser('guru.noperm.gradelevel', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->postJson('/api/v1/academic/grade-levels', ['name' => 'Kelas 13', 'code' => 'XIII'])
        ->assertForbidden();
});
