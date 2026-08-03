<?php

use App\Infrastructure\Persistence\Eloquent\System\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * "Koneksi Database Aplikasi" di menu Backup Database — super_admin saja,
 * digerbangi password akses TERPISAH dari password login, disimpan di tabel
 * `settings` yang sudah ada (group=security). Lihat CLAUDE.md § Keselamatan
 * Database. Endpoint update() menulis ke config('backup.env_file'), yang di
 * .env.testing diarahkan ke file scratch — TIDAK PERNAH ke .env asli.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->superAdmin = makeUser('superadmin.dbconn', 'super_admin', 'admin');
    $this->admin = makeUser('admin.dbconn', 'admin', 'admin');

    File::ensureDirectoryExists(dirname(config('backup.env_file')));
    File::put(config('backup.env_file'), "APP_NAME=Test\nDB_HOST=127.0.0.1\nDB_PORT=5432\nDB_DATABASE=sms_testing\nDB_USERNAME=postgres\nDB_PASSWORD=admin123\n");
});

afterEach(function () {
    File::delete(config('backup.env_file'));
    File::delete(config('backup.env_file') . '.bak');
});

// ---------- akses & role ----------

test('admin biasa ditolak semua endpoint koneksi database', function () {
    $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/super-admin/db-connection/access-status')->assertForbidden();
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/super-admin/db-connection/reveal', ['access_password' => 'x'])->assertForbidden();
});

test('access-status melaporkan belum dikonfigurasi sebelum password akses dibuat', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/db-connection/access-status')
        ->assertOk()
        ->assertJsonPath('data.configured', false);
});

test('reveal ditolak 422 kalau password akses belum pernah dibuat', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/reveal', ['access_password' => 'apapun'])
        ->assertStatus(422);
});

// ---------- setel & ubah password akses ----------

test('super admin bisa membuat password akses baru', function () {
    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/access-password', [
            'new_password' => 'rahasia-sekali',
            'new_password_confirmation' => 'rahasia-sekali',
        ]);

    $response->assertOk();

    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/db-connection/access-status')
        ->assertJsonPath('data.configured', true);
});

test('ubah password akses butuh password akses lama yang benar', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('lama-banget'));

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/access-password', [
            'current_password' => 'salah',
            'new_password' => 'baru-banget-123',
            'new_password_confirmation' => 'baru-banget-123',
        ])
        ->assertStatus(422);
});

// ---------- reset via artisan (skenario "lupa password akses") ----------

test('command db-connection:reset-access-password menghapus setting sehingga bisa dibuat ulang', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('lupa-ini'));

    $this->artisan('db-connection:reset-access-password', ['--force' => true])
        ->assertSuccessful();

    expect(Setting::getGlobal('security', 'db_config_access_password'))->toBeNull();

    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/db-connection/access-status')
        ->assertJsonPath('data.configured', false);
});

test('command db-connection:reset-access-password aman dijalankan walau belum pernah dikonfigurasi', function () {
    $this->artisan('db-connection:reset-access-password', ['--force' => true])
        ->assertSuccessful();

    expect(Setting::getGlobal('security', 'db_config_access_password'))->toBeNull();
});

// ---------- reveal / test / update (pakai password akses yang benar) ----------

test('reveal dengan password akses salah ditolak 403', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/reveal', ['access_password' => 'salah'])
        ->assertForbidden();
});

test('reveal dengan password akses benar menampilkan info koneksi tanpa password DB', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));

    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/reveal', ['access_password' => 'benar123']);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['host', 'port', 'database', 'username']])
        ->assertJsonMissingPath('data.password');
});

test('test koneksi dengan kredensial valid (sms_testing) berhasil', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));
    $config = config('database.connections.pgsql');

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/test', [
            'access_password' => 'benar123',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
        ])
        ->assertOk();
});

test('test koneksi dengan kredensial tidak valid gagal 422', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/db-connection/test', [
            'access_password' => 'benar123',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'database_yang_tidak_ada_ini',
            'username' => 'postgres',
            'password' => 'admin123',
        ])
        ->assertStatus(422);
});

test('update dengan koneksi gagal TIDAK menulis file env', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));
    $before = File::get(config('backup.env_file'));

    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/super-admin/db-connection', [
            'access_password' => 'benar123',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'database_yang_tidak_ada_ini',
            'username' => 'postgres',
            'password' => 'admin123',
        ])
        ->assertStatus(422);

    expect(File::get(config('backup.env_file')))->toBe($before);
});

test('update dengan koneksi berhasil menulis file env dan membuat backup .bak', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));
    $config = config('database.connections.pgsql');

    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/super-admin/db-connection', [
            'access_password' => 'benar123',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            // password dikosongkan -> pertahankan password DB yang aktif sekarang
        ])
        ->assertOk();

    $written = File::get(config('backup.env_file'));
    expect($written)->toContain('DB_DATABASE=' . $config['database']);
    expect(File::exists(config('backup.env_file') . '.bak'))->toBeTrue();
});

test('update dengan password akses salah ditolak 403 dan tidak menulis apa pun', function () {
    Setting::setGlobal('security', 'db_config_access_password', Hash::make('benar123'));
    $before = File::get(config('backup.env_file'));
    $config = config('database.connections.pgsql');

    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/super-admin/db-connection', [
            'access_password' => 'salah-total',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
        ])
        ->assertForbidden();

    expect(File::get(config('backup.env_file')))->toBe($before);
});

// ---------- versi aplikasi (footer) ----------

test('versi aplikasi default 1.0.0 sebelum pernah diisi', function () {
    $response = $this->actingAs($this->superAdmin, 'sanctum')->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('app.version', '1.0.0'));
});

test('super admin bisa menyimpan versi aplikasi tanpa password akses', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/super-admin/app-version', ['version' => '2.4.1'])
        ->assertOk()
        ->assertJsonPath('data.version', '2.4.1');

    expect(Setting::getGlobal('app', 'version'))->toBe('2.4.1');

    $response = $this->actingAs($this->superAdmin, 'sanctum')->get('/dashboard');
    $response->assertInertia(fn ($page) => $page->where('app.version', '2.4.1'));
});

test('admin biasa ditolak menyimpan versi aplikasi', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/v1/super-admin/app-version', ['version' => '9.9.9'])
        ->assertForbidden();
});

test('versi aplikasi kosong ditolak validasi', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/super-admin/app-version', ['version' => ''])
        ->assertStatus(422);
});
