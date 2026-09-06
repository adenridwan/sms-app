<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scanner_devices', function (Blueprint $table) {
            // User yang sedang login di device ini
            $table->uuid('user_id')->nullable()->after('tenant_id');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Heartbeat & monitoring
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_scan_at');
            $table->string('app_version', 20)->nullable()->after('last_heartbeat_at');
            $table->string('os_version', 50)->nullable()->after('app_version');
            $table->string('device_model', 100)->nullable()->after('os_version');

            // Battery info
            $table->unsignedTinyInteger('battery_level')->nullable()->after('device_model');
            $table->boolean('battery_charging')->default(false)->after('battery_level');

            // Network info
            $table->string('network_type', 20)->nullable()->after('battery_charging')
                ->comment('wifi, mobile, ethernet, none');
            $table->string('network_name', 100)->nullable()->after('network_type')
                ->comment('WiFi SSID atau nama koneksi');
            $table->unsignedInteger('latency_ms')->nullable()->after('network_name');

            // Sync status
            $table->unsignedInteger('pending_sync_count')->default(0)->after('latency_ms');

            // Index untuk query monitoring
            $table->index(['tenant_id', 'last_heartbeat_at'], 'idx_device_heartbeat');
            $table->index(['tenant_id', 'user_id'], 'idx_device_user');
        });
    }

    public function down(): void
    {
        Schema::table('scanner_devices', function (Blueprint $table) {
            $table->dropIndex('idx_device_heartbeat');
            $table->dropIndex('idx_device_user');

            $table->dropForeign(['user_id']);

            $table->dropColumn([
                'user_id',
                'last_heartbeat_at',
                'app_version',
                'os_version',
                'device_model',
                'battery_level',
                'battery_charging',
                'network_type',
                'network_name',
                'latency_ms',
                'pending_sync_count',
            ]);
        });
    }
};
