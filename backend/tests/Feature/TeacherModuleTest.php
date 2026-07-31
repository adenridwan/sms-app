<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * `actingAs($x, 'sanctum')` calls `Auth::shouldUse('sanctum')`, which stays
 * the default guard for the rest of the test. Auth::attempt() (used by the
 * login endpoint) requires a SessionGuard, so any real, unauthenticated
 * login call later in the same test must restore the 'web' guard first.
 */
function useWebGuard(): void
{
    Auth::shouldUse('web');
}

/**
 * Fase G1 TEACHER-MODULE-PLAN.md: API guru yang benar (skema nyata),
 * akun otomatis dengan password awal = tanggal lahir (ddmmyyyy), dan
 * alur wajib ganti password sebelum mengakses sistem.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.guru', 'admin', 'admin');
});

function teacherPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Budi',
        'last_name' => 'Santoso',
        'email' => 'budi.santoso.' . uniqid() . '@sekolah.test',
        'phone' => '081234567890',
        'gender' => 'male',
        'birth_place' => 'Jakarta',
        'birth_date' => '1990-08-17',
        'religion' => 'islam',
        'address' => 'Jl. Contoh No. 1',
        'nip' => null,
        'employment_status' => 'permanent',
    ], $overrides);
}

// ---------- store: akun otomatis (R1) ----------

test('membuat guru membuat users, user_profiles, dan teachers sekaligus', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload());

    $response->assertCreated()
        ->assertJsonPath('data.first_name', 'Budi')
        ->assertJsonPath('data.must_change_password', true);

    $userId = $response->json('data.user_id');
    expect(DB::table('users')->where('id', $userId)->exists())->toBeTrue()
        ->and(DB::table('user_profiles')->where('user_id', $userId)->exists())->toBeTrue()
        ->and(DB::table('teachers')->where('user_id', $userId)->exists())->toBeTrue();

    $user = User::find($userId);
    expect($user->hasRole('guru'))->toBeTrue()
        ->and($user->user_type)->toBe('teacher')
        ->and($user->mustChangePassword())->toBeTrue();
});

test('password awal adalah tanggal lahir format ddmmyyyy', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['birth_date' => '1985-03-05']));

    $response->assertCreated();
    expect($response->json('data.initial_password'))->toBe('05031985');

    $email = $response->json('data.email');
    useWebGuard();
    $login = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => '05031985']);
    $login->assertOk();
});

test('username dibuat otomatis dan unik saat bentrok', function () {
    $r1 = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['first_name' => 'Unik', 'last_name' => 'Tes']));
    $r2 = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['first_name' => 'Unik', 'last_name' => 'Tes']));

    $r1->assertCreated();
    $r2->assertCreated();
    expect($r1->json('data.initial_username'))->not->toBe($r2->json('data.initial_username'));
});

test('tanggal lahir wajib diisi', function () {
    $payload = teacherPayload();
    unset($payload['birth_date']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('birth_date');
});

test('NIP duplikat dalam satu tenant ditolak 422', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['nip' => 'NIP-SAMA-001']))
        ->assertCreated();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['nip' => 'NIP-SAMA-001']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('nip');
});

test('guru (bukan admin) ditolak menambah guru', function () {
    $guru = makeUser('guru.nopriv', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload())
        ->assertForbidden();
});

// ---------- update: tidak mengubah password ----------

test('update guru tidak mengubah password atau penanda must_change_password', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['birth_date' => '1993-06-10']))
        ->json('data');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/v1/teachers/' . $created['id'], ['first_name' => 'BudiUbah', 'employment_status' => 'contract'])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'BudiUbah')
        ->assertJsonPath('data.employment_status', 'contract')
        ->assertJsonPath('data.must_change_password', true);

    // Password awal tetap sama persis (tidak diregenerasi saat update)
    useWebGuard();
    $this->postJson('/api/v1/auth/login', ['email' => $created['email'], 'password' => '10061993'])
        ->assertOk();
});

// ---------- destroy: soft delete berantai ----------

test('hapus guru menonaktifkan akun (soft delete) sehingga tidak bisa login', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['birth_date' => '1991-04-22']))
        ->json('data');
    $email = $created['email'];
    $password = $created['initial_password'];

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson('/api/v1/teachers/' . $created['id'])
        ->assertOk();

    expect(Teacher::find($created['id']))->toBeNull()
        ->and(Teacher::withTrashed()->find($created['id']))->not->toBeNull()
        ->and(User::find($created['user_id']))->toBeNull();

    useWebGuard();
    $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnauthorized();
});

// ---------- alur wajib ganti password ----------

test('user dengan must_change_password diarahkan ke halaman ganti password', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload())
        ->json('data');
    $guruUser = User::find($created['user_id']);

    $this->actingAs($guruUser)
        ->get('/dashboard')
        ->assertRedirect(route('change-password'));
});

test('halaman ganti password sendiri tidak ikut di-redirect (tanpa loop)', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload())
        ->json('data');
    $guruUser = User::find($created['user_id']);

    $this->withoutVite()
        ->actingAs($guruUser)
        ->get('/change-password')
        ->assertOk();
});

test('user tanpa must_change_password mengakses dashboard normal', function () {
    $admin = $this->admin->fresh();
    expect($admin->mustChangePassword())->toBeFalse();

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/dashboard')
        ->assertOk();
});

test('ganti password sendiri berhasil menghapus penanda dan membuka akses', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['birth_date' => '1992-11-20']))
        ->json('data');
    $guruUser = User::find($created['user_id']);

    $this->actingAs($guruUser, 'sanctum')
        ->putJson('/api/v1/auth/password', [
            'current_password' => '20111992',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])
        ->assertOk();

    expect($guruUser->fresh()->mustChangePassword())->toBeFalse();

    $this->withoutVite()
        ->actingAs($guruUser->fresh())
        ->get('/dashboard')
        ->assertOk();
});

test('reset password oleh admin menandai must_change_password lagi', function () {
    $created = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload(['birth_date' => '1992-11-20']))
        ->json('data');
    $guruUser = User::find($created['user_id']);

    // guru mengganti sendiri dulu -> penanda hilang
    $this->actingAs($guruUser, 'sanctum')->putJson('/api/v1/auth/password', [
        'current_password' => '20111992',
        'password' => 'passwordbaru123',
        'password_confirmation' => 'passwordbaru123',
    ])->assertOk();
    expect($guruUser->fresh()->mustChangePassword())->toBeFalse();

    // reset password (super admin only) -> penanda wajib ganti muncul lagi
    $superAdmin = makeUser('sa.resetguru', 'super_admin', 'super_admin');
    $this->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/v1/admin/users/{$created['user_id']}/reset-password", ['password' => 'direset12345'])
        ->assertOk();

    expect($guruUser->fresh()->mustChangePassword())->toBeTrue();
});

// ---------- index tidak lagi 500 ----------

test('daftar guru (index) tidak error', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers', teacherPayload())
        ->assertCreated();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/teachers?search=Budi')
        ->assertOk();
});
