<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token provisioning untuk login via QR code. Admin men-generate token
     * sementara (15 menit), user scan QR di app mobile, lalu dapat JWT +
     * data user tanpa perlu ketik email/password. Cocok untuk onboarding
     * perangkat baru tanpa koneksi stabil.
     */
    public function up(): void
    {
        Schema::create('provision_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'redeemed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_tokens');
    }
};
