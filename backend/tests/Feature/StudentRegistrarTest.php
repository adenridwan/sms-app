<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Services\StudentRegistrar;

/**
 * StudentRegistrar::create() — lihat catatan yang sama di TeacherModuleTest
 * ("TeacherRegistrar coba ulang otomatis..."): username dibuat lewat
 * cek-dulu-baru-insert yang tidak atomik, jadi rawan race condition saat
 * import massal (siswa & guru bisa diimpor bersamaan). Insiden nyata:
 * import siswa gagal dengan SQLSTATE 23505 mentah karena username hasil
 * generate sempat diambil proses lain persis sebelum insert.
 */
beforeEach(function () {
    setupSchoolWorld();
});

test('StudentRegistrar coba ulang otomatis kalau username bentrok tepat saat insert (race condition)', function () {
    User::create([
        'tenant_id' => $this->tenantId,
        'username' => 'diserobot.proses.lain',
        'email' => 'lain@sekolah.test',
        'password' => bcrypt('x'),
        'status' => 'active',
        'user_type' => 'staff',
    ]);

    $registrar = Mockery::mock(StudentRegistrar::class)->makePartial();
    $registrar->shouldReceive('uniqueUsername')
        ->twice()
        ->andReturn('diserobot.proses.lain', 'diserobot.proses.lain2');

    $result = $registrar->create([
        'first_name' => 'Siti',
        'last_name' => 'Aminah',
        'email' => 'siti.race.' . uniqid() . '@sekolah.test',
        'gender' => 'female',
        'birth_date' => '2008-05-05',
        'nis' => (string) random_int(10000000, 99999999),
    ], $this->tenantId);

    expect($result['username'])->toBe('diserobot.proses.lain2');
});

test('StudentRegistrar melempar error asli kalau bentroknya bukan soal username', function () {
    // Pastikan catch di create() tidak diam-diam menelan error lain (mis.
    // NIS duplikat) sebagai "coba lagi username" — cuma unique violation
    // pada kolom username yang boleh dicoba ulang.
    $existing = $registrar = app(StudentRegistrar::class);
    $registrar->create([
        'first_name' => 'Nis',
        'last_name' => 'Kembar',
        'email' => 'nis1.' . uniqid() . '@sekolah.test',
        'gender' => 'male',
        'birth_date' => '2008-01-01',
        'nis' => '99999999',
    ], $this->tenantId);

    expect(fn () => $registrar->create([
        'first_name' => 'Nis',
        'last_name' => 'Kembar2',
        'email' => 'nis2.' . uniqid() . '@sekolah.test',
        'gender' => 'male',
        'birth_date' => '2008-01-01',
        'nis' => '99999999',
    ], $this->tenantId))->toThrow(\Illuminate\Database\QueryException::class);
});
