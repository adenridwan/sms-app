<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lokasi binary pg_dump
    |--------------------------------------------------------------------------
    | Di Windows biasanya tidak ada di PATH — isi PG_DUMP_PATH di .env dengan
    | path lengkap, contoh:
    |   PG_DUMP_PATH="C:\Program Files\PostgreSQL\18\bin\pg_dump.exe"
    */
    'pg_dump_path' => env('PG_DUMP_PATH', 'pg_dump'),

    /*
    |--------------------------------------------------------------------------
    | Lokasi binary psql (dipakai untuk restore/import backup)
    |--------------------------------------------------------------------------
    | Biasanya satu folder dengan pg_dump. Sama seperti PG_DUMP_PATH, di
    | Windows isi path lengkap di .env:
    |   PSQL_PATH="C:\Program Files\PostgreSQL\18\bin\psql.exe"
    */
    'psql_path' => env('PSQL_PATH', 'psql'),

    /*
    |--------------------------------------------------------------------------
    | Retensi backup (hari)
    |--------------------------------------------------------------------------
    | Backup yang lebih tua dari ini otomatis dihapus tiap kali backup:run
    | berjalan. 0 = jangan pernah hapus otomatis.
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Direktori penyimpanan file backup
    |--------------------------------------------------------------------------
    | Sengaja dibuat bisa dioverride (BACKUP_DIRECTORY) supaya test tidak
    | menulis/menghapus file di folder backup dev/produksi yang sama.
    */
    'directory' => env('BACKUP_DIRECTORY', storage_path('app/private/backups')),

    /*
    |--------------------------------------------------------------------------
    | File .env yang ditulis oleh fitur "Ubah Koneksi Database"
    |--------------------------------------------------------------------------
    | WAJIB dioverride (ENV_FILE_PATH) di .env.testing ke file scratch —
    | jangan sampai test menulis ke .env asli (lihat CLAUDE.md insiden 2026-08-01).
    */
    'env_file' => env('ENV_FILE_PATH', base_path('.env')),

];
