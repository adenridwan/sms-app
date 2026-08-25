<?php

use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Halaman & endpoint back-office absensi (Hari Libur, QR Code, Template
 * Kartu, Pengaturan) tidak pernah punya penjagaan permission sama sekali —
 * siapa pun yang login bisa membukanya. Sekarang dibatasi settings.attendance
 * (permission yang sudah lama menjaga endpoint PUT pengaturan, dipegang
 * persis admin/tata_usaha/super_admin — cocok dengan yang diminta: "hanya
 * untuk role admin, super admin dan TU").
 *
 * qr/students/{id} dan qr/teachers/{id} (GET tunggal) SENGAJA tidak ikut
 * dibatasi — itu dipakai halaman self-service "Absensi Saya" untuk
 * menampilkan QR milik sendiri, harus tetap terbuka untuk semua role.
 *
 * /attendance/qr-codes SENGAJA dibuka juga untuk guru (tab "Siswa" saja,
 * kelas yang diampu saja) — lihat AttendanceQrCodesGuruAccessTest.php untuk
 * cakupan itu. Test di sini fokus ke Hari Libur/Template Kartu/Pengaturan
 * yang tetap murni admin/TU/super_admin.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.backoffice', 'admin', 'admin');
    $this->tu = makeUser('tu.backoffice', 'tata_usaha', 'staff');

    $guruUser = makeUser('guru.backoffice', 'guru', 'teacher');
    $this->teacher = Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $guruUser->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);
    $this->guru = $guruUser;
});

test('halaman hari libur, qr code, template kartu, dan pengaturan bisa diakses admin dan tata_usaha', function () {
    foreach ([$this->admin, $this->tu] as $user) {
        $this->actingAs($user)->get('/attendance/holidays')->assertOk();
        $this->actingAs($user)->get('/attendance/qr-codes')->assertOk();
        $this->actingAs($user)->get('/attendance/card-templates')->assertOk();
        $this->actingAs($user)->get('/attendance/settings')->assertOk();
    }
});

test('halaman hari libur, template kartu, dan pengaturan ditolak untuk guru', function () {
    $this->actingAs($this->guru)->get('/attendance/holidays')->assertForbidden();
    $this->actingAs($this->guru)->get('/attendance/card-templates')->assertForbidden();
    $this->actingAs($this->guru)->get('/attendance/settings')->assertForbidden();
});

test('endpoint holidays/qr-export/card-templates ditolak 403 untuk guru', function () {
    $this->actingAs($this->guru, 'sanctum')->getJson('/api/v1/attendance/holidays')->assertForbidden();
    $this->actingAs($this->guru, 'sanctum')->getJson('/api/v1/attendance/qr/export?type=teacher&format=excel')->assertForbidden();
    $this->actingAs($this->guru, 'sanctum')->getJson('/api/v1/attendance/card-templates/teacher')->assertForbidden();
});

test('guru tetap bisa melihat QR miliknya sendiri (dipakai Absensi Saya)', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson("/api/v1/attendance/qr/teachers/{$this->teacher->id}")
        ->assertOk();
});

test('guru ditolak meregenerasi atau bulk-download QR (aksi admin)', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->postJson("/api/v1/attendance/qr/teachers/{$this->teacher->id}/regenerate")
        ->assertForbidden();

    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/attendance/qr/teachers/bulk')
        ->assertForbidden();
});
