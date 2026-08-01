<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyimpanan sparse untuk visibilitas menu per-role (Opsi A).
 *
 * Satu baris = pasangan (role, menu_key) yang DISEMBUNYIKAN untuk tenant tsb.
 * Tabel kosong = perilaku default = persis seperti sekarang (murni berbasis
 * permission). Resolusi: menu tampil bila user punya permission-nya DAN tidak
 * ada baris "hidden" untuk salah satu role-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_visibility_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('role', 100);
            $table->string('menu_key', 100);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'role', 'menu_key']);
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_visibility_settings');
    }
};
