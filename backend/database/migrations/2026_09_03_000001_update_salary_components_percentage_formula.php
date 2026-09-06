<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            // Tambah kolom untuk referensi komponen (untuk percentage)
            $table->uuid('percentage_component_id')->nullable()->after('percentage_of');
            $table->foreign('percentage_component_id')
                ->references('id')
                ->on('salary_components')
                ->onDelete('set null');

            // Ubah formula menjadi jsonb untuk GUI builder
            // PostgreSQL: ubah dari varchar ke jsonb
            // Kita gunakan raw SQL karena Laravel tidak support langsung
        });

        // Ubah kolom formula ke jsonb (PostgreSQL)
        if (config('database.default') === 'pgsql') {
            \DB::statement('ALTER TABLE salary_components ALTER COLUMN formula TYPE jsonb USING formula::jsonb');
        }
    }

    public function down(): void
    {
        // Kembalikan formula ke varchar
        if (config('database.default') === 'pgsql') {
            \DB::statement('ALTER TABLE salary_components ALTER COLUMN formula TYPE varchar(255) USING formula::varchar');
        }

        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropForeign(['percentage_component_id']);
            $table->dropColumn('percentage_component_id');
        });
    }
};
