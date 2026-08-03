<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kode akses sekali-pakai yang di-generate admin (Opsi A: dibacakan ke
        // user lewat kanal luar, bukan dikirim push). Satu baris aktif per user
        // ditegakkan di service, bukan di skema, agar riwayat pemakaian terbaca.
        Schema::create('auth_otp_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignUuid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });

        // Riwayat percobaan login (sukses & gagal) untuk menu audit admin.
        // Kolom users.last_login_at hanya menyimpan yang terakhir, tanpa histori.
        Schema::create('auth_login_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('method', 20)->default('password');
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_login_logs');
        Schema::dropIfExists('auth_otp_codes');
    }
};
