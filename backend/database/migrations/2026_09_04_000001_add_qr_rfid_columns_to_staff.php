<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('unique_code', 64)->unique()->nullable()->after('employee_id')
                ->comment('QR code unique identifier');
            $table->string('rfid_code', 100)->nullable()->index()->after('unique_code')
                ->comment('RFID card code');
            $table->string('no_hp', 32)->nullable()->after('rfid_code')
                ->comment('Phone number for notifications');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['unique_code', 'rfid_code', 'no_hp']);
        });
    }
};
