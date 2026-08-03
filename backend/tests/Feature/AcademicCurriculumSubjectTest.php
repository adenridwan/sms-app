<?php

use App\Infrastructure\Persistence\Eloquent\Academic\Curriculum;
use App\Infrastructure\Persistence\Eloquent\Academic\Subject;

/**
 * Kurikulum & Mata Pelajaran sebelumnya cuma skema+seed tanpa model/controller/
 * halaman (lihat docs/academic/00-ANALISA-KURIKULUM-MAPEL-JADWAL.md). Test ini
 * menutup jalur web + API yang baru diimplementasikan.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.kurikulum', 'admin', 'admin');
});

// ---------- rute web ----------

test('halaman /academic/curricula merender 200', function () {
    $this->actingAs($this->admin, 'sanctum')->get('/academic/curricula')->assertOk();
});

test('halaman /academic/subjects merender 200', function () {
    $this->actingAs($this->admin, 'sanctum')->get('/academic/subjects')->assertOk();
});

test('rute lama /master/subjects redirect ke /academic/subjects', function () {
    $this->actingAs($this->admin, 'sanctum')->get('/master/subjects')->assertRedirect('/academic/subjects');
});

// ---------- API: kurikulum ----------

test('kurikulum baru bisa dibuat dan tampil di daftar', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/curricula', ['name' => 'Kurikulum Merdeka', 'code' => 'KM']);

    $response->assertCreated()->assertJsonPath('data.name', 'Kurikulum Merdeka');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/academic/curricula')
        ->assertOk()
        ->assertJsonFragment(['code' => 'KM']);
});

test('kode kurikulum ganda ditolak 422', function () {
    Curriculum::create(['tenant_id' => $this->tenantId, 'name' => 'K13', 'code' => 'K13']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/curricula', ['name' => 'K13 Revisi', 'code' => 'K13'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('kurikulum tidak bisa dihapus bila masih punya mata pelajaran', function () {
    $curriculum = Curriculum::create(['tenant_id' => $this->tenantId, 'name' => 'Kurikulum Merdeka', 'code' => 'KM']);
    Subject::create([
        'tenant_id' => $this->tenantId,
        'curriculum_id' => $curriculum->id,
        'name' => 'Matematika', 'code' => 'MTK', 'category' => 'Wajib',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/curricula/{$curriculum->id}")
        ->assertStatus(422);
});

test('kurikulum tanpa mata pelajaran bisa dihapus', function () {
    $curriculum = Curriculum::create(['tenant_id' => $this->tenantId, 'name' => 'Kurikulum Lama', 'code' => 'LAMA']);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/curricula/{$curriculum->id}")
        ->assertOk();

    expect(Curriculum::find($curriculum->id))->toBeNull();
});

// ---------- API: mata pelajaran ----------

test('mata pelajaran baru tertaut ke kurikulum yang dipilih', function () {
    $curriculum = Curriculum::create(['tenant_id' => $this->tenantId, 'name' => 'Kurikulum Merdeka', 'code' => 'KM']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/subjects', [
            'curriculum_id' => $curriculum->id,
            'name' => 'Matematika',
            'code' => 'MTK',
            'category' => 'Wajib',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.curriculum.id', $curriculum->id)
        ->assertJsonPath('data.category', 'Wajib');
});

test('kategori mata pelajaran di luar daftar yang diizinkan ditolak 422', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/subjects', [
            'name' => 'Matematika', 'code' => 'MTK', 'category' => 'Kategori Ngasal',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category');
});

test('kode mata pelajaran ganda ditolak 422', function () {
    Subject::create(['tenant_id' => $this->tenantId, 'name' => 'Matematika', 'code' => 'MTK', 'category' => 'Wajib']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/subjects', ['name' => 'Matematika Lanjut', 'code' => 'MTK', 'category' => 'Wajib'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('filter mata pelajaran berdasarkan curriculum_id', function () {
    $km = Curriculum::create(['tenant_id' => $this->tenantId, 'name' => 'Kurikulum Merdeka', 'code' => 'KM']);
    $k13 = Curriculum::create(['tenant_id' => $this->tenantId, 'name' => 'K13', 'code' => 'K13']);
    Subject::create(['tenant_id' => $this->tenantId, 'curriculum_id' => $km->id, 'name' => 'Matematika KM', 'code' => 'MTK-KM', 'category' => 'Wajib']);
    Subject::create(['tenant_id' => $this->tenantId, 'curriculum_id' => $k13->id, 'name' => 'Matematika K13', 'code' => 'MTK-K13', 'category' => 'Wajib']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/subjects?curriculum_id={$km->id}")
        ->assertOk();

    $codes = collect($response->json('data.data'))->pluck('code');
    expect($codes)->toContain('MTK-KM')->not->toContain('MTK-K13');
});

test('guru tanpa izin subjects.manage ditolak membuat mata pelajaran', function () {
    $guru = makeUser('guru.noperm.subject', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->postJson('/api/v1/academic/subjects', ['name' => 'Matematika', 'code' => 'MTK', 'category' => 'Wajib'])
        ->assertForbidden();
});
