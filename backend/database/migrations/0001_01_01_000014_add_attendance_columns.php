<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns to students table for QR/RFID scanning
        Schema::table('students', function (Blueprint $table) {
            $table->string('unique_code', 64)->unique()->nullable()->after('nisn')
                ->comment('QR code unique identifier');
            $table->string('rfid_code', 100)->nullable()->index()->after('unique_code')
                ->comment('RFID card code');
            $table->integer('poin_pelanggaran')->default(0)->after('rfid_code')
                ->comment('Accumulated lateness violation points');
        });

        // Add columns to teachers table for QR/RFID scanning
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('unique_code', 64)->unique()->nullable()->after('nuptk')
                ->comment('QR code unique identifier');
            $table->string('rfid_code', 100)->nullable()->index()->after('unique_code')
                ->comment('RFID card code');
            $table->string('no_hp', 32)->nullable()->after('rfid_code')
                ->comment('Phone number for notifications');
        });

        // Add menit_keterlambatan to student_attendances
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->integer('menit_keterlambatan')->default(0)->after('status')
                ->comment('Minutes of lateness');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['unique_code', 'rfid_code', 'poin_pelanggaran']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['unique_code', 'rfid_code', 'no_hp']);
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropColumn('menit_keterlambatan');
        });
    }
};
