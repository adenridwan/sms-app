<?php

use App\Domain\Auth\Services\OtpService;
use App\Infrastructure\Persistence\Eloquent\Auth\AuthLoginLog;
use App\Infrastructure\Persistence\Eloquent\Auth\User;

/**
 * POST /api/v1/auth/login-otp — jalur "lupa password" aplikasi ini (tidak
 * ada reset password via email, lihat routes/api_v1.php). Endpoint ini
 * sudah ada sejak lama tapi TIDAK PERNAH punya test maupun konsumen
 * frontend sampai halaman /forgot-password dibuat — regresi ini menutup
 * celah itu.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.otp-login', 'admin', 'admin');
});

function issueLoginOtpFor(User $user, User $admin): string
{
    return app(OtpService::class)->generateFor($user, $admin);
}

test('login-otp berhasil dengan kode valid dan menerbitkan token', function () {
    $user = makeUser('guru.otp-login', 'guru', 'teacher');
    $code = issueLoginOtpFor($user, $this->admin);

    $response = $this->postJson('/api/v1/auth/login-otp', [
        'email' => $user->email,
        'code' => $code,
    ])->assertOk();

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.user.id'))->toBe($user->id);

    $log = AuthLoginLog::where('user_id', $user->id)->latest('created_at')->first();
    expect($log)->not->toBeNull();
    expect($log->method)->toBe('otp');
    expect($log->successful)->toBeTrue();
});

test('login-otp menolak kode salah', function () {
    $user = makeUser('guru.otp-wrong', 'guru', 'teacher');
    issueLoginOtpFor($user, $this->admin);

    $this->postJson('/api/v1/auth/login-otp', [
        'email' => $user->email,
        'code' => '000000',
    ])->assertUnauthorized();
});

test('login-otp untuk email tak terdaftar memberi pesan generik yang sama seperti kode salah', function () {
    $responseUnknownEmail = $this->postJson('/api/v1/auth/login-otp', [
        'email' => 'tidak.terdaftar@sekolah.test',
        'code' => '123456',
    ])->assertUnauthorized();

    $user = makeUser('guru.otp-generic', 'guru', 'teacher');
    issueLoginOtpFor($user, $this->admin);
    $responseWrongCode = $this->postJson('/api/v1/auth/login-otp', [
        'email' => $user->email,
        'code' => '000000',
    ])->assertUnauthorized();

    // Pesan harus identik supaya endpoint ini tidak bisa dipakai menebak
    // email mana yang terdaftar.
    expect($responseUnknownEmail->json('message'))->toBe($responseWrongCode->json('message'));
});

test('login-otp kode sekali pakai — pemakaian kedua ditolak', function () {
    $user = makeUser('guru.otp-once', 'guru', 'teacher');
    $code = issueLoginOtpFor($user, $this->admin);

    $this->postJson('/api/v1/auth/login-otp', ['email' => $user->email, 'code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/login-otp', ['email' => $user->email, 'code' => $code])->assertUnauthorized();
});

test('login-otp menolak akun yang tidak aktif meski kodenya valid', function () {
    $user = makeUser('guru.otp-inactive', 'guru', 'teacher');
    $user->update(['status' => 'suspended']);
    $code = issueLoginOtpFor($user, $this->admin);

    $this->postJson('/api/v1/auth/login-otp', [
        'email' => $user->email,
        'code' => $code,
    ])->assertForbidden();
});

test('login-otp menolak format request tidak valid', function () {
    $this->postJson('/api/v1/auth/login-otp', [
        'email' => 'bukan-email',
        'code' => '123', // kurang dari 6 digit
    ])->assertStatus(422);
});
