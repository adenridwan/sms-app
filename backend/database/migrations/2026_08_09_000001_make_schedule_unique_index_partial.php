<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `schedule_unique` sebelumnya adalah unique index biasa — tidak
 * mengecualikan baris yang sudah soft-deleted (schedules pakai SoftDeletes).
 * Akibatnya hapus satu jadwal lalu langsung membuat jadwal baru di kelas +
 * hari + jam yang PERSIS sama gagal dengan unique violation mentah dari
 * Postgres, walau secara aplikasi slot itu sudah "kosong" (Eloquent
 * otomatis menyembunyikan baris yang di-soft-delete dari query biasa).
 *
 * Diganti jadi partial unique index (`WHERE deleted_at IS NULL`) supaya
 * baris yang sudah dihapus tidak lagi dihitung menempati slotnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique('schedule_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX schedule_unique
             ON schedules (classroom_id, semester_id, day_of_week, time_slot_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS schedule_unique');

        Schema::table('schedules', function (Blueprint $table) {
            $table->unique(['classroom_id', 'semester_id', 'day_of_week', 'time_slot_id'], 'schedule_unique');
        });
    }
};
