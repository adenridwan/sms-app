<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index untuk mempercepat pencarian user di menu Keamanan Login dan
     * halaman Pengguna. Pencarian by nama/email sekarang memakai JOIN ke
     * user_profiles — tanpa index, query bisa lambat di tabel besar.
     */
    public function up(): void
    {
        // Index trigram untuk pencarian ILIKE %...% yang cepat.
        // Membutuhkan extension pg_trgm (biasanya sudah aktif di PostgreSQL).
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->index('first_name', 'idx_user_profiles_first_name');
            $table->index('last_name', 'idx_user_profiles_last_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'idx_users_email_search');
            $table->index('username', 'idx_users_username_search');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_user_profiles_first_name');
            $table->dropIndex('idx_user_profiles_last_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email_search');
            $table->dropIndex('idx_users_username_search');
        });
    }
};
