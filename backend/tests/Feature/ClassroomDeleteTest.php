<?php

/**
 * Hapus kelas: klik konfirmasi dobel (atau baris yang sudah dihapus dari tab
 * lain) mengirim DELETE kedua untuk id yang sama. Balasannya harus 404 dengan
 * pesan yang layak dibaca pengguna — bukan pesan bawaan Laravel yang
 * membocorkan FQCN model ("No query results for model [App\...\Classroom]").
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.hapus.kelas', 'admin', 'admin');
    // classroomA/B punya siswa (lihat setupSchoolWorld) sehingga ditolak 422;
    // kelas kosong ini yang benar-benar bisa dihapus.
    $this->kelasKosong = makeClassroom($this->gradeLevelId, 'X C', $this->activeYearId);
});

test('hapus kelas kosong berhasil', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/classrooms/{$this->kelasKosong}")
        ->assertOk()
        ->assertJsonPath('message', 'Kelas berhasil dihapus');
});

test('hapus kelas yang masih punya siswa ditolak 422', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/classrooms/{$this->classroomA}")
        ->assertStatus(422);
});

test('hapus kedua untuk kelas yang sama balas 404 tanpa membocorkan nama kelas model', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/classrooms/{$this->kelasKosong}")
        ->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/classrooms/{$this->kelasKosong}");

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Data tidak ditemukan atau sudah dihapus.');

    expect($response->json('message'))->not->toContain('No query results');
});
