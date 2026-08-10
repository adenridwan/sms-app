<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan "identitas login" dari "alamat surat" — lihat
 * docs/EMAIL-OTOMATIS-AKUN.md.
 *
 * `users.email` tetap NOT NULL + UNIQUE karena itulah kredensial login
 * (Auth::attempt), tapi mulai sekarang boleh berisi alamat sintetis hasil
 * generate. Surat sungguhan (OTP, reset password, notifikasi) dikirim ke
 * `contact_email`.
 *
 * `contact_email` SENGAJA tidak unik: satu orang tua dengan beberapa anak
 * memakai satu alamat untuk beberapa akun. Keamanannya dijaga oleh aturan
 * "jangan pernah mencari user hanya dengan contact_email", bukan oleh index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('email');
            $table->timestamp('contact_email_verified_at')->nullable()->after('contact_email');
            $table->boolean('email_is_generated')->default(false)->after('contact_email_verified_at');

            // Bukan unique — hanya untuk pencarian "akun mana saja yang memakai
            // alamat ini" dari sisi admin.
            $table->index('contact_email');
        });

        // Semua user yang ada sekarang emailnya diketik manusia, jadi memang
        // alamat asli. Menyalinnya menjaga notifikasi kehadiran guru
        // (NotificationDispatcher) tetap terkirim setelah tujuannya dipindah
        // ke contact_email.
        DB::table('users')->whereNull('contact_email')->update([
            'contact_email' => DB::raw('email'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['contact_email']);
            $table->dropColumn(['contact_email', 'contact_email_verified_at', 'email_is_generated']);
        });
    }
};
