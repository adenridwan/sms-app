<?php

/**
 * routes/auth.php (berisi /logout) tidak pernah dimuat oleh
 * bootstrap/app.php — tombol Keluar di sidebar selalu 404. Ditemukan saat
 * mengerjakan Fase G2 (TEACHER-MODULE-PLAN.md), diperbaiki dengan
 * mendaftarkan /logout langsung di web.php dan menghapus file mati tsb.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->user = makeUser('logout.uji', 'admin', 'admin');
});

test('logout mengakhiri sesi dan mengarah ke login', function () {
    $response = $this->actingAs($this->user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('logout menolak request tanpa sesi login', function () {
    $this->post('/logout')->assertRedirect('/login');
});

test('setelah logout, halaman terproteksi mengarah ke login lagi', function () {
    $this->actingAs($this->user)->post('/logout');

    $this->get('/dashboard')->assertRedirect('/login');
});
