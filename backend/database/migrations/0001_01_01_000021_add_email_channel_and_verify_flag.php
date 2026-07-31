<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Channel Email per-tenant di notification_settings (SMTP per sekolah).
 *    smtp_password disimpan terenkripsi (cast 'encrypted' di model).
 * 2) Flag perlu_verifikasi pada absensi — ditandai saat scan pulang di luar
 *    jam pulang (GAP-2, keputusan "Terima + tandai").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('email_enabled')->default(false)->after('templates');
            $table->string('smtp_host', 255)->nullable()->after('email_enabled');
            $table->unsignedInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_username', 255)->nullable()->after('smtp_port');
            // Panjang untuk menampung ciphertext hasil enkripsi Laravel.
            $table->text('smtp_password')->nullable()->after('smtp_username');
            $table->string('smtp_encryption', 10)->nullable()->comment('tls, ssl, atau null')->after('smtp_password');
            $table->string('email_from_address', 255)->nullable()->after('smtp_encryption');
            $table->string('email_from_name', 255)->nullable()->after('email_from_address');
            $table->boolean('notify_email')->default(true)->after('email_from_name')
                ->comment('Master switch preferensi kirim via email');
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->boolean('perlu_verifikasi')->default(false)->after('status')
                ->comment('Scan di luar jam wajar, perlu diverifikasi admin');
        });

        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->boolean('perlu_verifikasi')->default(false)->after('status')
                ->comment('Scan di luar jam wajar, perlu diverifikasi admin');
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'email_enabled', 'smtp_host', 'smtp_port', 'smtp_username',
                'smtp_password', 'smtp_encryption', 'email_from_address',
                'email_from_name', 'notify_email',
            ]);
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropColumn('perlu_verifikasi');
        });

        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->dropColumn('perlu_verifikasi');
        });
    }
};
