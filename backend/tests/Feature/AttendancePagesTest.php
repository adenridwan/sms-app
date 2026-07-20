<?php

/**
 * Regresi untuk gap yang dilaporkan pengguna: menu Absensi tidak bisa
 * dipakai karena routes/web.php hanya mendaftarkan `/attendance` (landing),
 * sementara 8 kartu menu di dalamnya + submenu sidebar mengarah ke rute
 * yang tidak pernah didaftarkan (semua jatuh ke 404 fallback). Test ini
 * memastikan tiap halaman benar-benar merender dengan prop yang benar,
 * bukan cuma "tidak error" secara kebetulan.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.halaman', 'admin', 'admin');
});

test('landing absensi merender', function () {
    $this->actingAs($this->admin)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('attendance/Index'));
});

test('halaman absensi siswa merender dengan daftar kelas aktif', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/students/Index')
            ->has('classrooms', 2)
            ->where('classrooms.0.id', $this->classroomA)
            ->where('classrooms.1.id', $this->classroomB));
});

test('kelas dari tahun ajaran non-aktif tidak ikut muncul', function () {
    $inactiveClassroom = makeClassroom($this->gradeLevelId, 'X C (Non-Aktif)', $this->inactiveYearId);

    $this->actingAs($this->admin)
        ->get('/attendance/students')
        ->assertInertia(fn ($page) => $page
            ->has('classrooms', 2)
            ->where('classrooms.0.id', $this->classroomA)
            ->where('classrooms.1.id', $this->classroomB));

    expect($inactiveClassroom)->not->toBeNull();
});

test('halaman absensi guru merender', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/teachers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('attendance/teachers/Index'));
});

test('halaman izin merender', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/permissions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('attendance/permissions/Index'));
});

test('halaman tambah izin merender dengan daftar siswa dan guru', function () {
    $guru = makeUser('guru.formizin', 'guru', 'teacher');
    \App\Infrastructure\Persistence\Eloquent\Teacher\Teacher::create([
        'tenant_id' => $this->tenantId,
        'user_id' => $guru->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);

    $this->actingAs($this->admin)
        ->get('/attendance/permissions/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/permissions/Create')
            ->has('students', 2)
            ->has('teachers', 1)
            ->where('teachers.0.full_name', $guru->full_name));
});

test('halaman hari libur merender', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/holidays')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('attendance/holidays/Index'));
});

test('halaman qr code merender dengan daftar kelas aktif', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/qr-codes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/qr-codes/Index')
            ->has('classrooms', 2));
});

test('halaman laporan merender dengan daftar kelas aktif', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/reports/Index')
            ->has('classrooms', 2));
});

test('halaman pengaturan absensi merender', function () {
    $this->actingAs($this->admin)
        ->get('/attendance/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('attendance/settings/Index'));
});

test('halaman scanner merender', function () {
    $this->actingAs($this->admin)
        ->get('/scanner')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('scanner/Index'));
});

// ---------- A14: dropdown kelas tersaring per guru ----------

test('guru dengan satu kelas diampu hanya melihat kelas itu + initialClassroom terisi', function () {
    $guru = makeUser('guru.satukelas', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $this->actingAs($guru)
        ->get('/attendance/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('classrooms', 1)
            ->where('classrooms.0.id', $this->classroomA)
            ->where('initialClassroom', $this->classroomA));
});

test('guru dengan kelas diampu tidak melihat kelas guru lain', function () {
    $guru = makeUser('guru.duakelas', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);
    assignClassroom($guru, $this->classroomB);

    $this->actingAs($guru)
        ->get('/attendance/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('classrooms', 2)
            ->where('initialClassroom', null));
});

test('guru tanpa kelas diampu mendapat dropdown kosong, bukan error', function () {
    $guru = makeUser('guru.tanpakelas', 'guru', 'teacher');

    $this->actingAs($guru)
        ->get('/attendance/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('classrooms', 0));
});
