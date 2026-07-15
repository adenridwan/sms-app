<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenants (Schools)
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->unique()->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('npsn')->nullable()->comment('Nomor Pokok Sekolah Nasional');
            $table->enum('level', ['tk', 'sd', 'smp', 'sma', 'smk', 'university'])->default('sma');
            $table->enum('status', ['active', 'inactive', 'suspended', 'trial'])->default('trial');
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tenant Domains (for multiple domains per tenant)
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
