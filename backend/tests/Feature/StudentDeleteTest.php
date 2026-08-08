<?php

/**
 * Regresi: halaman Data Siswa dulu menghapus lewat `router.delete('/students/{id}')`
 * (Inertia -> rute web) yang cuma punya GET, jadi selalu MethodNotAllowed.
 * Penghapusan yang benar lewat API di bawah ini.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.hapussiswa', 'admin', 'admin');
});

test('admin bisa menghapus siswa lewat DELETE api students', function () {
    $studentId = $this->amir->id;
    $userId = $this->amirUser->id;

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/students/{$studentId}")
        ->assertOk()
        ->assertJsonPath('message', 'Siswa berhasil dihapus');

    $this->assertSoftDeleted('students', ['id' => $studentId]);
    $this->assertSoftDeleted('users', ['id' => $userId]);
});

test('siswa terhapus tidak lagi muncul di daftar', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/students/{$this->amir->id}")
        ->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/students');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($this->amir->id);
});

test('tanpa izin students.delete ditolak', function () {
    $guru = makeUser('guru.tanpahapus', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->deleteJson("/api/v1/students/{$this->amir->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('students', ['id' => $this->amir->id, 'deleted_at' => null]);
});
