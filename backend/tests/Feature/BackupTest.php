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
