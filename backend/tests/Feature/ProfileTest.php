<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Menu "Profil Saya" di navbar (halaman /profile) — data diri, foto, dan ganti
 * password milik pengguna yang sedang login. Bukan menu administrasi: setiap
 * pengguna hanya bisa menyentuh akunnya sendiri, tanpa permission khusus.
 */
beforeEach(function () {
    setupSchoolWorld();
    // Username bertitik memang bentuk yang dihasilkan sistem (TeacherRegistrar
    // memakai Str::slug($nama, '.')) — sekaligus menjaga agar aturan validasi
    // username tidak kembali menolak akun buatannya sendiri.
    $this->guru = makeUser('guru.profil', 'guru', 'teacher');
});

/**
 * Multipart tidak bisa dikirim lewat putJson (PHP tak mem-parse body multipart
 * pada PUT), jadi frontend memakai POST + `_method=PUT`. Header Accept-nya
 * disamakan dengan axios supaya error validasi tetap balik sebagai JSON 422,
 * bukan redirect 302 ala form web.
 */
function putProfile(array $payload): Illuminate\Testing\TestResponse
{
    return test()->post('/api/v1/auth/profile', ['_method' => 'PUT'] + $payload, [
        'Accept' => 'application/json',
    ]);
}

// ---------- halaman web ----------

test('halaman /profile merender 200 untuk pengguna yang login', function () {
    $this->actingAs($this->guru, 'sanctum')->get('/profile')->assertOk();
});

test('tamu diarahkan ke login saat membuka /profile', function () {
    $this->get('/profile')->assertRedirect('/login');
});

// ---------- API: baca profil ----------

test('pengguna bisa membaca profilnya sendiri', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/auth/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $this->guru->id)
        ->assertJsonPath('data.username', 'guru.profil')
        ->assertJsonPath('data.roles', ['guru']);
});

test('tamu ditolak membaca profil', function () {
    $this->getJson('/api/v1/auth/profile')->assertUnauthorized();
});

// ---------- API: perbarui profil ----------

test('profil bisa diperbarui lewat POST dengan _method=PUT', function () {
    $this->actingAs($this->guru, 'sanctum');

    putProfile([
        'first_name' => 'Budi',
        'last_name' => 'Santoso',
        'username' => 'budi.santoso',
        'email' => 'budi.profil.baru@sekolah.test',
        'phone' => '081234567890',
    ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Budi Santoso')
        ->assertJsonPath('data.username', 'budi.santoso')
        ->assertJsonPath('data.phone', '081234567890');

    $this->assertDatabaseHas('users', [
        'id' => $this->guru->id,
        'username' => 'budi.santoso',
        'email' => 'budi.profil.baru@sekolah.test',
    ]);
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $this->guru->id,
        'first_name' => 'Budi',
        'phone' => '081234567890',
    ]);
});

test('username yang sudah dipakai pengguna lain ditolak', function () {
    makeUser('sudah.dipakai', 'admin', 'admin');

    $this->actingAs($this->guru, 'sanctum')
        ->putJson('/api/v1/auth/profile', ['username' => 'sudah.dipakai'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('username');
});

test('menyimpan ulang email sendiri tetap diperbolehkan', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->putJson('/api/v1/auth/profile', ['email' => $this->guru->email])
        ->assertOk();
});

test('tamu ditolak memperbarui profil', function () {
    $this->putJson('/api/v1/auth/profile', ['first_name' => 'X'])->assertUnauthorized();
});

// ---------- API: avatar ----------

test('avatar tersimpan di disk public dan URL-nya dikembalikan', function () {
    Storage::fake('public');
    $this->actingAs($this->guru, 'sanctum');

    $response = putProfile(['avatar' => UploadedFile::fake()->image('foto.jpg')])->assertOk();

    $path = $response->json('data.avatar');
    expect($path)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($path);
    expect($response->json('data.avatar_url'))->toContain($path);
});

test('avatar bisa dihapus dan filenya ikut terhapus dari disk', function () {
    Storage::fake('public');
    $this->actingAs($this->guru, 'sanctum');

    $path = putProfile(['avatar' => UploadedFile::fake()->image('foto.jpg')])->json('data.avatar');

    $this->deleteJson('/api/v1/auth/profile/avatar')->assertOk();

    Storage::disk('public')->assertMissing($path);
    $this->assertDatabaseHas('users', ['id' => $this->guru->id, 'avatar' => null]);
});

test('file non-gambar ditolak sebagai avatar', function () {
    Storage::fake('public');
    $this->actingAs($this->guru, 'sanctum');

    putProfile(['avatar' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('avatar');
});

test('username bertitik hasil generate sistem tetap boleh disimpan', function () {
    $this->actingAs($this->guru, 'sanctum');

    putProfile(['username' => $this->guru->username])->assertOk();
});

test('username dengan karakter terlarang ditolak', function () {
    $this->actingAs($this->guru, 'sanctum');

    putProfile(['username' => 'guru profil!'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('username');
});

test('mengunggah avatar tidak menghapus nama yang sudah tersimpan', function () {
    Storage::fake('public');
    $this->actingAs($this->guru, 'sanctum');

    putProfile(['first_name' => 'Budi', 'last_name' => 'Santoso'])->assertOk();
    putProfile(['avatar' => UploadedFile::fake()->image('foto.jpg')])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Budi Santoso');
});

// ---------- API: ganti password ----------

test('ganti password ditolak bila password saat ini salah', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'password-yang-salah',
            'password' => 'RahasiaBaru#2026',
            'password_confirmation' => 'RahasiaBaru#2026',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

test('ganti password ditolak bila konfirmasi tidak cocok', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'RahasiaBaru#2026',
            'password_confirmation' => 'BedaSendiri#2026',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('password berhasil diganti dengan password saat ini yang benar', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'RahasiaBaru#2026',
            'password_confirmation' => 'RahasiaBaru#2026',
        ])
        ->assertOk();

    $fresh = User::find($this->guru->id);
    expect(Hash::check('RahasiaBaru#2026', $fresh->password))->toBeTrue();
});
