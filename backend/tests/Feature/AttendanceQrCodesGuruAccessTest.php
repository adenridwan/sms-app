<?php

use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * /attendance/qr-codes ("QR Code") dulu murni admin/TU/super_admin. Guru
 * butuh tab "Siswa"-nya untuk mencetak/melihat QR kelas yang diampu — jadi
 * dibuka untuk attendance.scan-students juga, TAPI:
 * - daftar kelas yang dikirim ke halaman dibatasi ke kelas yang diampu
 *   sendiri (scopeToTeacher, sama seperti Absensi Siswa).
 * - endpoint qr/students/bulk menolak classroom_id di luar kelas yang
 *   diampu, bahkan kalau dikirim manual lewat request langsung (bukan cuma
 *   disembunyikan di UI).
 * - tab "Guru" dan seluruh aksi admin (export, regenerate, qr/teachers/bulk)
 *   tetap murni admin/TU/super_admin.
 */
beforeEach(function () {
    setupSchoolWorld();

    $guruUser = makeUser('guru.qrcodes', 'guru', 'teacher');
    Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $guruUser->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);
    assignClassroom($guruUser, test()->classroomA);
    $this->guru = $guruUser;
});

test('guru bisa membuka halaman QR Code dan hanya menerima kelas yang diampunya', function () {
    $response = $this->actingAs($this->guru)
        ->get('/attendance/qr-codes')
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('attendance/qr-codes/Index')
        ->has('classrooms', 1)
        ->where('classrooms.0.id', test()->classroomA));
});

test('guru bisa memuat QR massal untuk kelas yang diampunya', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/attendance/qr/students/bulk?classroom_id=' . test()->classroomA)
        ->assertOk();
});

test('guru ditolak memuat QR massal untuk kelas yang bukan diampunya, walau classroom_id dikirim manual', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/attendance/qr/students/bulk?classroom_id=' . test()->classroomB)
        ->assertForbidden();
});

test('guru tetap tidak bisa memuat QR massal guru/pegawai', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/attendance/qr/teachers/bulk')
        ->assertForbidden();
});
