<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\System\Setting;
use App\Services\EmailGenerator;
use App\Services\StudentRegistrar;
use App\Services\TeacherRegistrar;

/**
 * Email otomatis untuk akun siswa & guru — docs/EMAIL-OTOMATIS-AKUN.md.
 *
 * `users.email` adalah identitas login (boleh sintetis, tidak pernah dikirimi
 * surat); `users.contact_email` alamat surat sungguhan yang SENGAJA tidak unik.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.emailotomatis', 'admin', 'admin');

    Setting::setForTenant(
        $this->tenantId,
        EmailGenerator::SETTING_GROUP,
        EmailGenerator::SETTING_KEY,
        'almuawanah.school',
    );
});

function setDomain(?string $domain): void
{
    Setting::setForTenant(
        test()->tenantId,
        EmailGenerator::SETTING_GROUP,
        EmailGenerator::SETTING_KEY,
        $domain,
    );
}

// ── Bentuk local part ────────────────────────────────────────────────────────

test('hanya kata sebelum spasi pertama yang dipakai', function () {
    $generator = app(EmailGenerator::class);

    // "Ahmad Ridwan" + nama belakang "Hidayat" → cukup "ahmad".
    expect($generator->namePart('Ahmad Ridwan'))->toBe('ahmad')
        ->and($generator->namePart('Muhammad Zaidan Arkananta'))->toBe('muhammad');
});

test('nama dibersihkan jadi ASCII huruf kecil tanpa tanda baca', function () {
    $generator = app(EmailGenerator::class);

    expect($generator->namePart("Nur 'Aini"))->toBe('nur')
        ->and($generator->namePart('Al-Fatih'))->toBe('alfatih')
        ->and($generator->namePart('  Éko  '))->toBe('eko');
});

test('nama satu kata yang sangat panjang dipotong 20 karakter', function () {
    $part = app(EmailGenerator::class)->namePart('Abdurrahmanshidiqullah');

    expect($part)->toBe('abdurrahmanshidiqull')
        ->and(strlen($part))->toBeLessThanOrEqual(20);
});

test('nama tanpa huruf latin jatuh ke fallback', function () {
    $generator = app(EmailGenerator::class);

    expect($generator->namePart('王'))->toBe('siswa')
        ->and($generator->namePart('???', 'guru'))->toBe('guru');
});

test('email siswa dirakit dari nama depan + nis + domain', function () {
    $email = app(EmailGenerator::class)->forStudent($this->tenantId, 'Ahmad Ridwan', '2024001');

    expect($email)->toBe('ahmad.2024001@almuawanah.school');
});

test('alamat yang sudah dipakai akun lain diberi sufiks angka', function () {
    User::create([
        'tenant_id' => $this->tenantId,
        'username' => 'penghuni.lama',
        'email' => 'ahmad.2024001@almuawanah.school',
        'password' => 'password',
        'status' => 'active',
        'user_type' => 'student',
    ]);

    $email = app(EmailGenerator::class)->forStudent($this->tenantId, 'Ahmad', '2024001');

    expect($email)->toBe('ahmad.20240012@almuawanah.school');
});

// ── Registrar ────────────────────────────────────────────────────────────────

test('siswa tanpa email mendapat email otomatis dan ditandai generated', function () {
    $result = app(StudentRegistrar::class)->create([
        'first_name' => 'Ahmad Ridwan',
        'last_name' => 'Hidayat',
        'nis' => '2024001',
        'gender' => 'male',
        'birth_date' => '2012-07-14',
    ], $this->tenantId);

    $user = $result['student']->user;

    expect($result['email'])->toBe('ahmad.2024001@almuawanah.school')
        ->and($user->email)->toBe('ahmad.2024001@almuawanah.school')
        ->and($user->email_is_generated)->toBeTrue()
        ->and($user->contact_email)->toBeNull();
});

test('email yang diisi manual tidak ditimpa generator', function () {
    $result = app(StudentRegistrar::class)->create([
        'first_name' => 'Ahmad',
        'nis' => '2024002',
        'gender' => 'male',
        'birth_date' => '2012-07-14',
        'email' => 'ahmad.asli@gmail.com',
    ], $this->tenantId);

    expect($result['student']->user->email)->toBe('ahmad.asli@gmail.com')
        ->and($result['student']->user->email_is_generated)->toBeFalse();
});

test('email guru dirakit dari username', function () {
    $result = app(TeacherRegistrar::class)->create([
        'first_name' => 'Budi',
        'last_name' => 'Santoso',
        'gender' => 'male',
        'birth_date' => '1985-01-01',
        'nip' => '198501012010011001',
    ], $this->tenantId);

    expect($result['email'])->toBe('budi.santoso@almuawanah.school')
        ->and($result['teacher']->user->email_is_generated)->toBeTrue();
});

test('guru yang mengisi email asli memakainya juga sebagai email kontak', function () {
    $result = app(TeacherRegistrar::class)->create([
        'first_name' => 'Siti',
        'gender' => 'female',
        'birth_date' => '1990-03-15',
        'email' => 'siti@gmail.com',
    ], $this->tenantId);

    $user = $result['teacher']->user;

    expect($user->email)->toBe('siti@gmail.com')
        ->and($user->contact_email)->toBe('siti@gmail.com');
});

test('registrar menolak bila domain sekolah belum diatur', function () {
    setDomain(null);

    expect(fn () => app(StudentRegistrar::class)->create([
        'first_name' => 'Ahmad',
        'nis' => '2024003',
        'gender' => 'male',
        'birth_date' => '2012-07-14',
    ], $this->tenantId))->toThrow(RuntimeException::class);
});

// ── contact_email boleh dipakai beberapa akun ────────────────────────────────

test('satu email orang tua bisa dipakai beberapa anak', function () {
    $registrar = app(StudentRegistrar::class);

    foreach (['2024010' => 'Ahmad', '2024011' => 'Fatimah', '2024012' => 'Zaid'] as $nis => $nama) {
        $registrar->create([
            'first_name' => $nama,
            'nis' => $nis,
            'gender' => 'male',
            'birth_date' => '2012-07-14',
            'contact_email' => 'orangtua@gmail.com',
        ], $this->tenantId);
    }

    expect(User::withoutTenant()->where('contact_email', 'orangtua@gmail.com')->count())->toBe(3);
});

// ── Jalur form (API) ─────────────────────────────────────────────────────────

test('form tambah siswa boleh mengosongkan email', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/students', [
        'first_name' => 'Ahmad Ridwan',
        'last_name' => 'Hidayat',
        'nis' => '2024100',
        'gender' => 'male',
        'entry_year' => 2024,
        'contact_email' => 'orangtua@gmail.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ahmad.2024100@almuawanah.school')
        ->assertJsonPath('data.contact_email', 'orangtua@gmail.com')
        ->assertJsonPath('data.email_is_generated', true);
});

test('form tambah siswa tanpa email ditolak rapi bila domain belum diatur', function () {
    setDomain(null);

    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/students', [
        'first_name' => 'Ahmad',
        'nis' => '2024101',
        'gender' => 'male',
        'entry_year' => 2024,
    ])->assertStatus(422)
        ->assertJsonPath('message', EmailGenerator::domainMissingMessage());
});

// ── Profil sekolah ───────────────────────────────────────────────────────────

test('domain email tersimpan lewat profil sekolah', function () {
    $superAdmin = makeUser('super.emaildomain', 'super_admin', 'super_admin');

    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/settings/school', [
        'name' => 'Sekolah Uji',
        'email' => 'uji@sekolah.test',
        'email_domain' => 'Sekolah.Baru.SCH.ID',
    ])->assertOk()->assertJsonPath('data.email_domain', 'sekolah.baru.sch.id');
});

test('domain dengan format tidak wajar ditolak', function () {
    $superAdmin = makeUser('super.emaildomain2', 'super_admin', 'super_admin');

    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/settings/school', [
        'name' => 'Sekolah Uji',
        'email' => 'uji@sekolah.test',
        'email_domain' => '@sekolah baru',
    ])->assertStatus(422);
});

// ── Bentrok dengan akun yang sudah dihapus (soft delete) ─────────────────────

/**
 * Regresi produksi 2026-08-09: import gagal "users_username_unique" karena
 * siswa dengan username itu sudah dihapus. Index UNIQUE Postgres ikut
 * menghitung baris ter-soft-delete, sedangkan global scope SoftDeletes
 * menyembunyikannya — uniqueUsername() mengembalikan nama yang sama terus
 * sehingga seluruh percobaan ulang menabrak tembok yang sama.
 */
test('username milik siswa yang sudah dihapus tidak dipakai ulang', function () {
    $registrar = app(StudentRegistrar::class);

    $pertama = $registrar->create([
        'first_name' => 'Apeva',
        'last_name' => 'Affshen Meysya',
        'nis' => '25260024',
        'gender' => 'female',
        'birth_date' => '2012-07-14',
    ], $this->tenantId);

    $usernameLama = $pertama['username'];
    $pertama['student']->user->delete();
    $pertama['student']->delete();

    // Nama sama persis, NIS berbeda — persis pola import ulang di produksi.
    $kedua = $registrar->create([
        'first_name' => 'Apeva',
        'last_name' => 'Affshen Meysya',
        'nis' => '25260025',
        'gender' => 'female',
        'birth_date' => '2012-07-14',
    ], $this->tenantId);

    expect($kedua['username'])->not->toBe($usernameLama);
});

test('email milik akun yang sudah dihapus tidak dipakai ulang', function () {
    $pemilikLama = User::create([
        'tenant_id' => $this->tenantId,
        'username' => 'pemilik.lama',
        'email' => 'apeva.25260024@almuawanah.school',
        'password' => 'password',
        'status' => 'active',
        'user_type' => 'student',
    ]);
    $pemilikLama->delete();

    // Alamat itu tetap memenuhi index UNIQUE meski barisnya ter-soft-delete.
    $email = app(EmailGenerator::class)->forStudent($this->tenantId, 'Apeva', '25260024');

    expect($email)->toBe('apeva.252600242@almuawanah.school');
});
