<?php

use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Menautkan akun yang SUDAH ada ke data master (guru / staf / siswa).
 *
 * Masalah yang diselesaikan: menu Pengguna membaca tabel `users`, sedangkan
 * Daftar Guru membaca `teachers`, Data Staf membaca `staff`, dan Data Siswa
 * membaca `students`. Sebuah akun bisa bertipe `teacher` tanpa pernah punya
 * baris di `teachers` — muncul di menu Pengguna, hilang di Daftar Guru, dan
 * dicari dengan kata apa pun tidak ketemu.
 *
 * Sebelum ini jalan keluarnya buntu: form Pengguna sengaja tidak membuat baris
 * data master (dibuat read-only sejak G4 supaya tidak ada input ganda), dan
 * form data master SELALU membuat akun baru. Satu-satunya cara "memunculkan"
 * orang itu adalah membuat akun kedua yang duplikat.
 */
beforeEach(function () {
    setupSchoolWorld();

    $this->admin = makeUser('admin.tautkan', 'admin');
});

/** Akun tanpa baris data master — persis kondisi guru1/guru2 dari UserSeeder. */
function makeAkunYatim(string $username, string $userType, string $role): object
{
    return makeUser($username, $role, $userType);
}

test('akun guru yang belum terdaftar bisa ditautkan tanpa membuat akun baru', function () {
    $akun = makeAkunYatim('budi.yatim', 'teacher', 'guru');
    $jumlahUserSebelum = DB::table('users')->count();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', [
            'user_id' => $akun->id,
            'nip' => '198501012010011001',
            'join_date' => '2020-01-01',
            'employment_status' => 'permanent',
        ])
        ->assertCreated();

    // Tidak ada akun baru: inilah inti fitur ini.
    expect(DB::table('users')->count())->toBe($jumlahUserSebelum);

    $guru = Teacher::withoutGlobalScopes()->where('user_id', $akun->id)->first();
    expect($guru)->not->toBeNull();
    expect($guru->nip)->toBe('198501012010011001');

    // Tidak ada kredensial baru untuk disampaikan.
    expect($response->json('data.initial_password'))->toBeNull();
    expect($response->json('data.initial_username'))->toBe($akun->username);
});

test('setelah ditautkan, akun itu muncul di daftar guru dan bisa dicari', function () {
    $akun = makeAkunYatim('budi.cari', 'teacher', 'guru');

    // Daftar guru dibungkus paginator, jadi barisnya ada di `data.data`.
    // Sebelum: tidak ada di daftar guru sama sekali — inilah keluhan aslinya.
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/teachers?search=budi.cari')
        ->assertOk()
        ->assertJsonCount(0, 'data.data');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['user_id' => $akun->id])
        ->assertCreated();

    // Sesudah: ketemu.
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/teachers?search=budi.cari')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');
});

test('menautkan tidak mengubah profil maupun password akunnya', function () {
    $akun = makeAkunYatim('budi.profil', 'teacher', 'guru');
    $passwordLama = DB::table('users')->where('id', $akun->id)->value('password');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['user_id' => $akun->id])
        ->assertCreated();

    $akunSesudah = DB::table('users')->where('id', $akun->id)->first();

    expect($akunSesudah->password)->toBe($passwordLama);
    expect($akunSesudah->username)->toBe($akun->username);
    expect($akunSesudah->email)->toBe($akun->email);
});

test('akun yang sudah terdaftar sebagai guru ditolak', function () {
    $akun = makeAkunYatim('budi.dobel', 'teacher', 'guru');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['user_id' => $akun->id])
        ->assertCreated();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['user_id' => $akun->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

test('akun bertipe lain tidak bisa ditautkan sebagai guru', function () {
    $akunStaf = makeAkunYatim('tu.bukanguru', 'staff', 'tata_usaha');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['user_id' => $akunStaf->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

test('tanpa user_id, field akun tetap wajib seperti semula', function () {
    // Jalur "buat akun baru" tidak boleh ikut longgar gara-gara required_without.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['nip' => '123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'gender', 'birth_date']);
});

test('akun staf bisa ditautkan ke Data Staf', function () {
    $akun = makeAkunYatim('hendra.tu', 'staff', 'tata_usaha');
    $jumlahUserSebelum = DB::table('users')->count();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/staff', [
            'user_id' => $akun->id,
            'employee_id' => 'PEG-001',
            'join_date' => '2021-05-01',
        ])
        ->assertCreated();

    expect(DB::table('users')->count())->toBe($jumlahUserSebelum);

    $staf = Staff::withoutGlobalScopes()->where('user_id', $akun->id)->first();
    expect($staf)->not->toBeNull();
    expect($staf->employee_id)->toBe('PEG-001');
});

test('akun siswa bisa ditautkan ke Data Siswa', function () {
    $akun = makeAkunYatim('andi.siswa', 'student', 'siswa');
    $jumlahUserSebelum = DB::table('users')->count();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/students', [
            'user_id' => $akun->id,
            'nis' => '2026001',
            'entry_year' => 2026,
        ])
        ->assertCreated();

    expect(DB::table('users')->count())->toBe($jumlahUserSebelum);

    $siswa = Student::withoutGlobalScopes()->where('user_id', $akun->id)->first();
    expect($siswa)->not->toBeNull();
    expect($siswa->nis)->toBe('2026001');
});

test('daftar akun yang bisa ditautkan hanya berisi yang belum terdaftar', function () {
    $yatim = makeAkunYatim('guru.yatim', 'teacher', 'guru');
    $sudah = makeAkunYatim('guru.sudah', 'teacher', 'guru');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', ['user_id' => $sudah->id])
        ->assertCreated();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/users/linkable?type=teacher')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($yatim->id);
    expect($ids)->not->toContain($sudah->id);
});

test('daftar akun yang bisa ditautkan menolak tipe di luar daftar', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/users/linkable?type=parent')
        ->assertStatus(422);
});
