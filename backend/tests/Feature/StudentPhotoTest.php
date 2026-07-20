<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fase 3a ATTENDANCE-PLAN.md: foto profil siswa disimpan ke users.avatar,
 * mirip persis pola foto guru (TeacherDocumentsTest.php) — dipakai kartu ID
 * (Fase 2a) untuk menampilkan foto asli.
 */
beforeEach(function () {
    setupSchoolWorld();
    Storage::fake('public');
    $this->admin = makeUser('admin.fotosiswa', 'admin', 'admin');
});

test('upload foto siswa menyimpan ke users.avatar', function () {
    $file = UploadedFile::fake()->image('foto.jpg', 200, 200)->size(500);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $file]);

    $response->assertOk();
    $avatarPath = $this->amirUser->fresh()->avatar;
    expect($avatarPath)->not->toBeNull();
    Storage::disk('public')->assertExists($avatarPath);
});

test('upload kedua menimpa file lama', function () {
    $first = UploadedFile::fake()->image('pertama.jpg')->size(300);
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $first]);
    $firstPath = $this->amirUser->fresh()->avatar;

    $second = UploadedFile::fake()->image('kedua.jpg')->size(300);
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $second])
        ->assertOk();

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($this->amirUser->fresh()->avatar);
});

test('hapus foto siswa mengosongkan avatar dan file', function () {
    $file = UploadedFile::fake()->image('foto.jpg')->size(300);
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $file]);
    $avatarPath = $this->amirUser->fresh()->avatar;

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/students/{$this->amir->id}/photo")
        ->assertOk();

    expect($this->amirUser->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($avatarPath);
});

test('tanpa izin students.update ditolak', function () {
    $guru = makeUser('guru.tanpaizin', 'guru', 'teacher');
    $file = UploadedFile::fake()->image('foto.jpg')->size(300);

    $this->actingAs($guru, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $file])
        ->assertForbidden();
});

test('file bukan gambar ditolak 422', function () {
    $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $file])
        ->assertStatus(422);
});

test('file lebih dari 2MB ditolak 422', function () {
    $file = UploadedFile::fake()->image('besar.jpg')->size(3000);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/students/{$this->amir->id}/photo", ['photo' => $file])
        ->assertStatus(422);
});
