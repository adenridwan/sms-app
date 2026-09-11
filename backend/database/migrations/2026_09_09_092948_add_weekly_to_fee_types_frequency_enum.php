<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan opsi 'weekly' (Mingguan) ke enum frequency pada tabel fee_types.
     * Berguna untuk pembayaran kas mingguan atau tabungan.
     *
     * Laravel's enum() uses CHECK constraints in PostgreSQL, not native ENUM types.
     * We need to drop and recreate the constraint with the new value.
     */
    public function up(): void
    {
        // Drop the existing check constraint and modify the column
        // Laravel enum in PostgreSQL is varchar with check constraint
        DB::statement("ALTER TABLE fee_types DROP CONSTRAINT IF EXISTS fee_types_frequency_check");
        DB::statement("ALTER TABLE fee_types ADD CONSTRAINT fee_types_frequency_check CHECK (frequency::text = ANY (ARRAY['once'::text, 'monthly'::text, 'semester'::text, 'yearly'::text, 'weekly'::text]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE fee_types DROP CONSTRAINT IF EXISTS fee_types_frequency_check");
        DB::statement("ALTER TABLE fee_types ADD CONSTRAINT fee_types_frequency_check CHECK (frequency::text = ANY (ARRAY['once'::text, 'monthly'::text, 'semester'::text, 'yearly'::text]))");
    }
};
