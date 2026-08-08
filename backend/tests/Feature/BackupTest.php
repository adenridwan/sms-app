<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

const PG_DUMP_TEST_BINARY = 'C:\\Program Files\\PostgreSQL\\18\\bin\\pg_dump.exe';

/**
 * Menu Backup Database (Pengaturan > Backup Database) — khusus Super Admin,
 * dibuat setelah insiden 2026-08-01 (lihat CLAUDE.md § Keselamatan Database).
 * Backup nyata dijalankan lewat pg_dump terhadap sms_testing (BACKUP_DIRECTORY
 * di .env.testing diarahkan ke folder terpisah dari backup dev/produksi asli).
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->superAdmin = makeUser('superadmin.backup', 'super_admin', 'admin');
    $this->admin = makeUser('admin.backup', 'admin', 'admin');
    $this->guru = makeUser('guru.backup', 'guru', 'teacher');

    File::ensureDirectoryExists(config('backup.directory'));
});

afterEach(function () {
    File::deleteDirectory(config('backup.directory'));
});

// ---------- rute web ----------

test('halaman /settings/backups merender 200 untuk super admin', function () {
    $this->actingAs($this->superAdmin, 'sanctum')->get('/settings/backups')->assertOk();
});

test('halaman /settings/backups ditolak untuk admin biasa', function () {
    $this->actingAs($this->admin, 'sanctum')->get('/settings/backups')->assertForbidden();
});

// ---------- API: akses ----------

test('admin biasa ditolak mengakses daftar backup', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups')
        ->assertForbidden();
});

test('guru ditolak mengakses daftar backup', function () {
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/super-admin/backups')
        ->assertForbidden();
});

test('super admin bisa melihat daftar backup (kosong pada awalnya)', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups')
        ->assertOk()
        ->assertJsonPath('data', []);
});

// ---------- API: create / download / delete (pg_dump nyata ke sms_testing) ----------

test('super admin bisa membuat backup baru dan muncul di daftar', function () {
    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/backups');

    $response->assertCreated();

    $list = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups')
        ->assertOk();

    $files = collect($list->json('data'));
    expect($files)->toHaveCount(1);
    expect($files->first()['filename'])->toStartWith('sms_testing_')->toEndWith('.sql');
})->skip(! file_exists(PG_DUMP_TEST_BINARY), 'pg_dump tidak ditemukan di lingkungan ini');

test('super admin bisa mengunduh backup yang ada', function () {
    Artisan::call('backup:run');
    $filename = collect(File::files(config('backup.directory')))->first()->getFilename();

    $this->actingAs($this->superAdmin, 'sanctum')
        ->get("/api/v1/super-admin/backups/{$filename}/download")
        ->assertOk()
        ->assertHeader('content-disposition', "attachment; filename={$filename}");
})->skip(! file_exists(PG_DUMP_TEST_BINARY), 'pg_dump tidak ditemukan di lingkungan ini');

test('super admin bisa menghapus backup', function () {
    Artisan::call('backup:run');
    $filename = collect(File::files(config('backup.directory')))->first()->getFilename();

    $this->actingAs($this->superAdmin, 'sanctum')
        ->deleteJson("/api/v1/super-admin/backups/{$filename}")
        ->assertOk();

    expect(File::files(config('backup.directory')))->toHaveCount(0);
})->skip(! file_exists(PG_DUMP_TEST_BINARY), 'pg_dump tidak ditemukan di lingkungan ini');

test('nama file dengan pola path traversal ditolak 404 saat download', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups/....sql/download')
        ->assertNotFound();
});

test('nama file dengan pola path traversal ditolak 404 saat hapus', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->deleteJson('/api/v1/super-admin/backups/....sql')
        ->assertNotFound();
});

test('download backup yang tidak ada mengembalikan 404', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups/tidak-ada.sql/download')
        ->assertNotFound();
});

// ---------- API: nama database aktif (untuk dialog konfirmasi restore) ----------

test('super admin bisa melihat nama database aktif', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups/active-database')
        ->assertOk()
        ->assertJsonPath('data.database', 'sms_testing');
});

test('admin biasa ditolak melihat nama database aktif', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/super-admin/backups/active-database')
        ->assertForbidden();
});

// ---------- API: restore & import ----------
//
// CATATAN: tidak ada test di sini yang benar-benar MENJALANKAN psql restore
// terhadap sms_testing dengan sengaja. RefreshDatabase membungkus tiap test
// dalam transaction Postgres yang masih terbuka selama test berjalan — kalau
// test yang sama juga memanggil proses `psql` eksternal yang mengubah skema
// sms_testing (CREATE TABLE/ALTER SEQUENCE dst.), proses itu butuh lock yang
// bentrok dengan transaction test sendiri -> deadlock/hang (pernah terjadi,
// lihat riwayat commit). pg_dump (backup:run) aman karena read-only, tidak
// butuh lock eksklusif seperti itu. Jalur restore/import yang benar-benar
// mengeksekusi psql sudah diverifikasi manual (wipe sms_testing -> restore
// -> tabel & data kembali utuh), bukan lewat suite otomatis ini.

test('restore ditolak 422 kalau confirm_database tidak cocok', function () {
    Artisan::call('backup:run');
    $filename = collect(File::files(config('backup.directory')))->first()->getFilename();

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson("/api/v1/super-admin/backups/{$filename}/restore", ['confirm_database' => 'salah_nama_db'])
        ->assertStatus(422);
})->skip(! file_exists(PG_DUMP_TEST_BINARY), 'pg_dump tidak ditemukan di lingkungan ini');

test('restore ditolak untuk admin biasa', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/super-admin/backups/some-file.sql/restore', ['confirm_database' => 'sms_testing'])
        ->assertForbidden();
});

test('restore file yang tidak ada mengembalikan 404', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/backups/tidak-ada.sql/restore', ['confirm_database' => 'sms_testing'])
        ->assertNotFound();
});

test('restore dengan nama file pola path traversal ditolak 404', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/v1/super-admin/backups/....sql/restore', ['confirm_database' => 'sms_testing'])
        ->assertNotFound();
});

test('import ditolak 422 kalau confirm_database tidak cocok', function () {
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('dump.sql', "SELECT 1;\n");

    $this->actingAs($this->superAdmin, 'sanctum')
        ->post('/api/v1/super-admin/backups/import', [
            'file' => $file,
            'confirm_database' => 'salah',
        ])
        ->assertStatus(422);
});

test('import ditolak untuk admin biasa', function () {
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('dump.sql', "SELECT 1;\n");

    $this->actingAs($this->admin, 'sanctum')
        ->post('/api/v1/super-admin/backups/import', [
            'file' => $file,
            'confirm_database' => 'sms_testing',
        ])
        ->assertForbidden();
});
