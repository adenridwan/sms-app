<?php

use App\Domain\Auth\Services\OtpService;
use App\Infrastructure\Persistence\Eloquent\Auth\AuthOtpCode;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Support\Facades\Auth;

/**
 * Pendaftaran mandiri lewat /register tidak lagi langsung aktif: akun lahir
 * berstatus `pending` dan baru bisa dipakai setelah admin membuat kode di menu
 * Keamanan Login dan pendaftar memasukkannya di halaman aktivasi.
 *
 * Catatan guard: `actingAs($x, 'sanctum')` mengunci guard default ke sanctum
 * untuk sisa test, sedangkan endpoint login memakai Auth::attempt() yang butuh
 * SessionGuard — makanya ada Auth::shouldUse('web') sebelum memanggil login
 * sungguhan (alasan yang sama didokumentasikan di TeacherModuleTest).
 */
beforeEach(function () {
    setupSchoolWorld();

    // tenant_id null meniru super admin sungguhan (UserSeeder): tanpa tenant,
    // global scope BelongsToTenant tidak dipasang sehingga admin bisa melihat
    // pendaftar publik yang juga belum punya tenant.
    $this->superAdmin = User::create([
        'tenant_id' => null,
        'username' => 'superadmin.aktivasi',
        'email' => 'superadmin.aktivasi@sekolah.test',
        'password' => 'password',
        'status' => 'active',
        'user_type' => 'super_admin',
    ]);
    $this->superAdmin->assignRole('super_admin');
});

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'pendaftar' . uniqid(),
        'email' => 'pendaftar.' . uniqid() . '@sekolah.test',
        'password' => 'RahasiaKuat123',
        'password_confirmation' => 'RahasiaKuat123',
        'first_name' => 'Pendaftar',
        'last_name' => 'Baru',
        'phone' => '081200000001',
    ], $overrides);
}

/** Daftar lalu kembalikan payload-nya, supaya email/password bisa dipakai lagi. */
function registerNewUser(array $overrides = []): array
{
    $payload = registerPayload($overrides);
    test()->postJson('/api/v1/auth/register', $payload)->assertCreated();

    return $payload;
}

/** Kode polos hanya ada di respons admin — tidak pernah tersimpan apa adanya. */
function issueOtpFor(string $email): string
{
    $user = User::withoutTenant()->where('email', $email)->firstOrFail();

    $response = test()->actingAs(test()->superAdmin, 'sanctum')
        ->postJson("/api/v1/admin/login-security/users/{$user->id}/otp")
        ->assertOk();

    Auth::shouldUse('web');

    return $response->json('data.code');
}

// ---------- pendaftaran: pending, tanpa token ----------

test('pendaftaran membuat akun berstatus pending dan tidak menerbitkan token', function () {
    $payload = registerPayload();

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.requires_activation', true)
        ->assertJsonMissingPath('data.token');

    $user = User::withoutTenant()->where('email', $payload['email'])->first();
    expect($user)->not->toBeNull()
        ->and($user->status)->toBe('pending')
        ->and($user->tokens()->count())->toBe(0);
});

test('pesan pendaftaran mengarahkan pendaftar menghubungi administrator', function () {
    $response = $this->postJson('/api/v1/auth/register', registerPayload());

    expect($response->json('message'))->toContain('hubungi administrator');
});

test('akun pending tidak bisa login memakai password', function () {
    $payload = registerNewUser();

    Auth::shouldUse('web');

    $this->postJson('/api/v1/auth/login', [
        'email' => $payload['email'],
        'password' => $payload['password'],
    ])->assertForbidden();
});

// ---------- aktivasi ----------

test('kode dari menu keamanan login mengaktifkan akun pending', function () {
    $payload = registerNewUser();
    $code = issueOtpFor($payload['email']);

    $this->postJson('/api/v1/auth/activate', [
        'email' => $payload['email'],
        'code' => $code,
    ])->assertOk()->assertJsonPath('data.status', 'active');

    $user = User::withoutTenant()->where('email', $payload['email'])->first();
    expect($user->status)->toBe('active');
});

test('setelah diaktifkan, akun bisa login memakai password sendiri', function () {
    $payload = registerNewUser();
    $code = issueOtpFor($payload['email']);

    $this->postJson('/api/v1/auth/activate', ['email' => $payload['email'], 'code' => $code])
        ->assertOk();

    Auth::shouldUse('web');

    $this->postJson('/api/v1/auth/login', [
        'email' => $payload['email'],
        'password' => $payload['password'],
    ])->assertOk()->assertJsonStructure(['data' => ['token']]);
});

test('kode salah ditolak dan akun tetap pending', function () {
    $payload = registerNewUser();
    issueOtpFor($payload['email']);

    $this->postJson('/api/v1/auth/activate', [
        'email' => $payload['email'],
        'code' => '000000',
    ])->assertUnauthorized();

    $user = User::withoutTenant()->where('email', $payload['email'])->first();
    expect($user->status)->toBe('pending');
});

test('kode aktivasi hanya bisa dipakai sekali', function () {
    $payload = registerNewUser();
    $code = issueOtpFor($payload['email']);

    $this->postJson('/api/v1/auth/activate', ['email' => $payload['email'], 'code' => $code])
        ->assertOk();

    // Sudah aktif: pemakaian ulang tidak error, tapi juga tidak menghidupkan
    // apa pun — kodenya sendiri sudah ditandai terpakai.
    expect(AuthOtpCode::whereNull('used_at')->count())->toBe(0);
});

test('email tak dikenal ditolak dengan pesan yang sama seperti kode salah', function () {
    $this->postJson('/api/v1/auth/activate', [
        'email' => 'tidak.ada@sekolah.test',
        'code' => '123456',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Kode aktivasi tidak valid atau sudah kedaluwarsa.');
});

test('akun suspended tidak bisa menghidupkan dirinya sendiri walau kodenya benar', function () {
    $payload = registerNewUser();
    $code = issueOtpFor($payload['email']);

    User::withoutTenant()->where('email', $payload['email'])->update(['status' => 'suspended']);

    $this->postJson('/api/v1/auth/activate', [
        'email' => $payload['email'],
        'code' => $code,
    ])->assertForbidden();

    $user = User::withoutTenant()->where('email', $payload['email'])->first();
    expect($user->status)->toBe('suspended');
});

test('kode kedaluwarsa tidak bisa dipakai aktivasi', function () {
    $payload = registerNewUser();
    $code = issueOtpFor($payload['email']);

    $user = User::withoutTenant()->where('email', $payload['email'])->firstOrFail();
    AuthOtpCode::where('user_id', $user->id)
        ->update(['expires_at' => now()->subMinutes(OtpService::TTL_MINUTES + 1)]);

    $this->postJson('/api/v1/auth/activate', [
        'email' => $payload['email'],
        'code' => $code,
    ])->assertUnauthorized();

    expect($user->fresh()->status)->toBe('pending');
});

test('percobaan aktivasi tercatat di riwayat login dengan metode activation', function () {
    $payload = registerNewUser();
    $code = issueOtpFor($payload['email']);

    $this->postJson('/api/v1/auth/activate', ['email' => $payload['email'], 'code' => $code])
        ->assertOk();

    $logs = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/admin/login-security/logs?email=' . $payload['email'])
        ->assertOk()
        ->json('data.data');

    expect(collect($logs)->pluck('method'))->toContain('activation');
});
