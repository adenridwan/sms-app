<?php

use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Academic\Major;

/**
 * Regresi 2026-08-09: ClassroomController dan MajorController tidak punya
 * pengecekan izin sama sekali pada store/update/destroy/import — hanya
 * syncTeachers() yang dijaga. Akibatnya guru (yang cuma punya
 * `classrooms.view` / `majors.view`) bisa membuat, mengubah, dan menghapus
 * kelas serta jurusan lewat API, meski tombolnya tidak ada di layar.
 *
 * Izin `classrooms.manage` & `majors.manage` sudah ada di PermissionSeeder dan
 * hanya dipegang admin — controllernya saja yang lupa memakainya.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.otorisasi', 'admin', 'admin');
    $this->guru = makeUser('guru.otorisasi', 'guru', 'teacher');
});

// ── Kelas ────────────────────────────────────────────────────────────────────

test('guru ditolak membuat kelas', function () {
    $this->actingAs($this->guru, 'sanctum')->postJson('/api/v1/academic/classrooms', [
        'name' => 'X C',
        'code' => 'X-C',
        'academic_year_id' => $this->activeYearId,
        'grade_level_id' => $this->gradeLevelId,
        'capacity' => 30,
    ])->assertForbidden();

    expect(Classroom::where('code', 'X-C')->exists())->toBeFalse();
});

test('guru ditolak mengubah dan menghapus kelas', function () {
    $classroom = Classroom::find($this->classroomA);

    $this->actingAs($this->guru, 'sanctum')
        ->putJson("/api/v1/academic/classrooms/{$classroom->id}", ['name' => 'Diubah Guru'])
        ->assertForbidden();

    $this->actingAs($this->guru, 'sanctum')
        ->deleteJson("/api/v1/academic/classrooms/{$classroom->id}")
        ->assertForbidden();

    expect($classroom->fresh()->name)->not->toBe('Diubah Guru');
});

test('admin tetap bisa membuat kelas', function () {
    // `code` sengaja tidak dikirim: ClassroomController mengisinya otomatis
    // (increment per tahun ajaran) — lihat generateNextCode().
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/academic/classrooms', [
        'name' => 'X D',
        'academic_year_id' => $this->activeYearId,
        'grade_level_id' => $this->gradeLevelId,
        'capacity' => 30,
    ])->assertCreated();

    expect(Classroom::where('name', 'X D')->exists())->toBeTrue();
});

test('guru tetap boleh melihat daftar kelas', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/academic/classrooms')
        ->assertOk();
});

// ── Jurusan ──────────────────────────────────────────────────────────────────

test('guru ditolak membuat, mengubah, dan menghapus jurusan', function () {
    $this->actingAs($this->guru, 'sanctum')->postJson('/api/v1/academic/majors', [
        'code' => 'IPA',
        'name' => 'IPA',
    ])->assertForbidden();

    $major = Major::create([
        'tenant_id' => $this->tenantId,
        'code' => 'IPS',
        'name' => 'IPS',
        'is_active' => true,
    ]);

    $this->actingAs($this->guru, 'sanctum')
        ->putJson("/api/v1/academic/majors/{$major->id}", ['name' => 'Diubah Guru'])
        ->assertForbidden();

    $this->actingAs($this->guru, 'sanctum')
        ->deleteJson("/api/v1/academic/majors/{$major->id}")
        ->assertForbidden();

    expect(Major::where('code', 'IPA')->exists())->toBeFalse()
        ->and($major->fresh()->name)->toBe('IPS');
});

// ── Tingkat kelas (sudah dijaga sebelumnya — dikunci agar tidak lepas lagi) ──

test('guru ditolak membuat tingkat kelas', function () {
    $this->actingAs($this->guru, 'sanctum')->postJson('/api/v1/academic/grade-levels', [
        'name' => 'Kelas 11',
        'code' => 'XI',
        'order' => 2,
    ])->assertForbidden();
});

// ── Siswa: halaman form ikut dijaga, bukan hanya endpoint API ───────────────

test('guru ditolak menambah siswa lewat API', function () {
    $this->actingAs($this->guru, 'sanctum')->postJson('/api/v1/students', [
        'first_name' => 'Ahmad',
        'nis' => '2024777',
        'gender' => 'male',
        'entry_year' => 2024,
    ])->assertForbidden();
});

test('guru ditolak membuka halaman form tambah siswa', function () {
    // Tanpa penjagaan di rute web, guru bisa membuka formnya dan baru ditolak
    // setelah menekan Simpan — terbaca seolah ia berwenang.
    $this->actingAs($this->guru)->get('/students/create')->assertForbidden();
});

test('admin tetap bisa membuka halaman form tambah siswa', function () {
    $this->actingAs($this->admin)->get('/students/create')->assertOk();
});
