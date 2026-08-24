<?php

use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Sebelumnya /scanner (halaman) dan /api/v1/scan* (endpoint pemroses scan)
 * tidak punya penjagaan permission SAMA SEKALI — siapa pun yang login
 * (guru, siswa, orang_tua, bendahara, pustakawan) bisa membuka halaman
 * scanner dan memproses absen siapa saja. Sekarang dibatasi permission baru
 * attendance.scanner-operate, yang cuma dipegang admin/tata_usaha/
 * super_admin (lihat PermissionSeeder & RoleSeeder) — mengoperasikan mesin
 * scan untuk memproses absen ORANG LAIN beda dari attendance.check-in
 * (absen diri sendiri) yang tetap dipegang guru/siswa.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.scanaccess', 'admin', 'admin');
    $this->tu = makeUser('tu.scanaccess', 'tata_usaha', 'staff');

    $guruUser = makeUser('guru.scanaccess', 'guru', 'teacher');
    Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $guruUser->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
        'unique_code' => 'TCH-SCANACCESSTEST',
    ]);
    $this->guru = $guruUser;
});

test('halaman scanner bisa diakses admin, tata_usaha, dan super_admin', function () {
    $this->actingAs($this->admin)->get('/scanner')->assertOk();
    $this->actingAs($this->tu)->get('/scanner')->assertOk();
});

test('halaman scanner ditolak untuk guru', function () {
    $this->actingAs($this->guru)->get('/scanner')->assertForbidden();
});

test('halaman scanner ditolak untuk siswa', function () {
    $this->actingAs(test()->amirUser)->get('/scanner')->assertForbidden();
});

test('endpoint scan diproses untuk admin dan tata_usaha', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'TCH-SCANACCESSTEST', 'waktu' => 'masuk'])
        ->assertOk();

    $this->actingAs($this->tu, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'TCH-SCANACCESSTEST', 'waktu' => 'pulang'])
        ->assertOk();
});

test('endpoint scan ditolak 403 untuk guru meski attendance.check-in tetap dipegang', function () {
    expect($this->guru->can('attendance.check-in'))->toBeTrue();
    expect($this->guru->can('attendance.scanner-operate'))->toBeFalse();

    $this->actingAs($this->guru, 'sanctum')
        ->postJson('/api/v1/scan', ['unique_code' => 'TCH-SCANACCESSTEST', 'waktu' => 'masuk'])
        ->assertForbidden();
});

test('endpoint scan/bootstrap ditolak untuk siswa', function () {
    $this->actingAs(test()->amirUser, 'sanctum')
        ->getJson('/api/v1/scan/bootstrap')
        ->assertForbidden();
});
