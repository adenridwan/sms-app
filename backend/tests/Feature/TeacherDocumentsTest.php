<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fase G2 TEACHER-MODULE-PLAN.md: medialibrary untuk foto & dokumen
 * pemberkasan guru (semua opsional), serta ringkasan penugasan read-only
 * untuk halaman Detail Guru.
 */

/**
 * UploadedFile::fake()->create() mengisi file dengan byte kosong/null —
 * medialibrary mendeteksi mime dari ISI file (finfo), bukan dari mimeType
 * yang diklaim, sehingga file "pdf" palsu terdeteksi application/x-empty
 * dan ditolak acceptsMimeTypes(). Beri byte awal %PDF- agar terdeteksi benar.
 */
function fakePdf(string $name, int $kb = 100): UploadedFile
{
    $content = "%PDF-1.4\n" . str_repeat('A', max(0, $kb * 1024 - 10));

    return UploadedFile::fake()->createWithContent($name, $content);
}

beforeEach(function () {
    setupSchoolWorld();
    Storage::fake('public');

    $this->admin = makeUser('admin.doc', 'admin', 'admin');

    $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/teachers', [
        'first_name' => 'Dian',
        'last_name' => 'Pemberkasan',
        'email' => 'dian.pemberkasan.' . uniqid() . '@sekolah.test',
        'gender' => 'female',
        'birth_date' => '1991-02-14',
    ])->json('data');

    $this->teacher = Teacher::find($created['id']);
});

// ---------- foto ----------

test('upload foto guru menyimpan ke users.avatar', function () {
    $file = UploadedFile::fake()->image('foto.jpg', 200, 200)->size(500);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/teachers/{$this->teacher->id}/photo", ['photo' => $file]);

    $response->assertOk();
    $avatarPath = $this->teacher->user->fresh()->avatar;
    expect($avatarPath)->not->toBeNull();
    Storage::disk('public')->assertExists($avatarPath);
});

test('hapus foto guru mengosongkan avatar dan file', function () {
    $file = UploadedFile::fake()->image('foto.jpg')->size(300);
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/teachers/{$this->teacher->id}/photo", ['photo' => $file]);
    $avatarPath = $this->teacher->user->fresh()->avatar;

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/teachers/{$this->teacher->id}/photo")
        ->assertOk();

    expect($this->teacher->user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($avatarPath);
});

// ---------- dokumen: upload & validasi ----------

test('upload dokumen ijazah (multi-file) berhasil dan tampil di documentsSummary', function () {
    $file = fakePdf('ijazah-s1.pdf', 200);

    $response = $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ijazah', 'file' => $file]
    );

    $response->assertOk();
    expect($response->json('data.ijazah'))->toHaveCount(1)
        ->and($response->json('data.ijazah.0.name'))->toBe('ijazah-s1');
});

test('koleksi tidak dikenal ditolak 422', function () {
    $file = fakePdf('x.pdf', 100);

    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'rapor', 'file' => $file]
    )->assertStatus(422)->assertJsonValidationErrors('collection');
});

test('file lebih dari 5MB ditolak 422', function () {
    $file = fakePdf('besar.pdf', 6000);

    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ijazah', 'file' => $file]
    )->assertStatus(422)->assertJsonValidationErrors('file');
});

test('tipe file di luar pdf/jpg/png ditolak 422', function () {
    $file = UploadedFile::fake()->create('catatan.txt', 50, 'text/plain');

    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'lainnya', 'file' => $file]
    )->assertStatus(422)->assertJsonValidationErrors('file');
});

// ---------- singleFile vs multi-file ----------

test('KTP (singleFile) unggahan baru menimpa yang lama', function () {
    $first = UploadedFile::fake()->image('ktp-lama.jpg', 300, 300);
    $second = UploadedFile::fake()->image('ktp-baru.jpg', 300, 300);

    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ktp', 'file' => $first]
    )->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ktp', 'file' => $second]
    );

    $response->assertOk();
    expect($response->json('data.ktp'))->toHaveCount(1)
        ->and($response->json('data.ktp.0.name'))->toBe('ktp-baru');
});

test('ijazah (multi-file) unggahan kedua menambah, bukan menimpa', function () {
    $first = fakePdf('ijazah-s1.pdf', 100);
    $second = fakePdf('ijazah-s2.pdf', 100);

    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ijazah', 'file' => $first]
    )->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ijazah', 'file' => $second]
    );

    $response->assertOk();
    expect($response->json('data.ijazah'))->toHaveCount(2);
});

// ---------- hapus dokumen ----------

test('hapus dokumen menghilangkannya dari documentsSummary', function () {
    $file = fakePdf('sertifikat.pdf', 100);
    $upload = $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'sertifikat_pendidik', 'file' => $file]
    );
    $mediaId = $upload->json('data.sertifikat_pendidik.0.id');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/teachers/{$this->teacher->id}/documents/{$mediaId}");

    $response->assertOk();
    expect($response->json('data.sertifikat_pendidik'))->toHaveCount(0);
});

test('menghapus dokumen milik guru lain ditolak 404', function () {
    $otherCreated = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/teachers', [
        'first_name' => 'Guru', 'last_name' => 'Lain',
        'email' => 'guru.lain.' . uniqid() . '@sekolah.test',
        'gender' => 'male', 'birth_date' => '1990-01-01',
    ])->json('data');
    $otherTeacher = Teacher::find($otherCreated['id']);

    $file = fakePdf('doc.pdf', 100);
    $upload = $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$otherTeacher->id}/documents",
        ['collection' => 'lainnya', 'file' => $file]
    );
    $mediaId = $upload->json('data.lainnya.0.id');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/teachers/{$this->teacher->id}/documents/{$mediaId}")
        ->assertNotFound();
});

// ---------- otorisasi ----------

test('guru tanpa izin teachers.update ditolak upload dokumen', function () {
    $guru = makeUser('guru.noupload', 'guru', 'teacher');
    $file = fakePdf('doc.pdf', 100);

    $this->actingAs($guru, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'lainnya', 'file' => $file]
    )->assertForbidden();
});

// ---------- soft delete guru: dokumen tetap ada (reversibel) ----------

test('soft delete guru tidak menghapus dokumen (reversibel, konsisten dengan G1)', function () {
    $file = fakePdf('ijazah.pdf', 100);
    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/teachers/{$this->teacher->id}/documents",
        ['collection' => 'ijazah', 'file' => $file]
    )->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/teachers/{$this->teacher->id}")
        ->assertOk();

    expect(DB::table('media')
        ->where('model_type', Teacher::class)
        ->where('model_id', $this->teacher->id)
        ->count())->toBe(1);
});

// ---------- ringkasan penugasan (read-only) ----------

test('endpoint assignment memuat kelas, wali, mapel, dan jadwal', function () {
    $teacherUser = User::find($this->teacher->user_id);
    assignClassroom($teacherUser, $this->classroomA);
    DB::table('classrooms')->where('id', $this->classroomB)
        ->update(['homeroom_teacher_id' => $teacherUser->id]);
    assignClassroom($teacherUser, $this->classroomB);
    makeScheduleFor($teacherUser, $this->classroomA, $this->activeYearId);

    $subjectId = DB::table('subjects')->value('id')
        ?? tap(Str::uuid()->toString(), fn ($id) => DB::table('subjects')->insert([
            'id' => $id, 'tenant_id' => $this->tenantId, 'name' => 'Fisika', 'code' => 'FIS',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    DB::table('teacher_subjects')->insert([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $subjectId,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/teachers/{$this->teacher->id}/assignment");

    $response->assertOk();
    expect($response->json('data.classrooms'))->toHaveCount(2)
        ->and($response->json('data.homeroom_classroom.id'))->toBe($this->classroomB)
        ->and($response->json('data.subjects'))->toHaveCount(1)
        ->and($response->json('data.schedules'))->not->toBeEmpty();
});

test('guru tanpa penugasan menghasilkan assignment kosong yang jujur', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/teachers/{$this->teacher->id}/assignment");

    $response->assertOk()
        ->assertJsonPath('data.classrooms', [])
        ->assertJsonPath('data.homeroom_classroom', null)
        ->assertJsonPath('data.subjects', []);
});

// ---------- halaman web ----------

test('halaman detail guru merender dengan teacher dan assignment', function () {
    $this->withoutVite()
        ->actingAs($this->admin)
        ->get("/teachers/{$this->teacher->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teachers/Show')
            ->where('teacher.id', $this->teacher->id)
            ->has('assignment'));
});
