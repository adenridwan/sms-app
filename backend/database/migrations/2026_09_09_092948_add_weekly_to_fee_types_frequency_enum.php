<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan opsi 'weekly' (Mingguan) ke enum frequency pada tabel fee_types.
     * Berguna untuk pembayaran kas mingguan atau tabungan.
     */
    public function up(): void
    {
        // PostgreSQL: Tambah value ke enum type
        DB::statement("ALTER TYPE fee_types_frequency_enum ADD VALUE IF NOT EXISTS 'weekly'");
    }

    /**
     * Reverse the migrations.
     *
     * CATATAN: PostgreSQL tidak mendukung penghapusan value dari enum secara langsung.
     * Jika perlu rollback, harus recreate enum dan kolom.
     */
    public function down(): void
    {
        // Tidak bisa menghapus value dari enum PostgreSQL tanpa recreate
        // Biarkan 'weekly' tetap ada jika rollback
    }
};
