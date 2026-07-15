<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Holidays (tb_hari_libur)
        Schema::create('holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->date('tanggal');
            $table->string('keterangan', 255);
            $table->boolean('is_recurring')->default(false)->comment('Repeat annually');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'tanggal']);
            $table->index(['tenant_id', 'tanggal']);
        });

        // Leave Permissions (tb_perizinan) - For students and teachers
        Schema::create('leave_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id')->nullable();
            $table->uuid('teacher_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('tipe_izin', ['sakit', 'izin'])->default('sakit');
            $table->text('alasan')->nullable();
            $table->string('bukti', 255)->nullable()->comment('Path to supporting document');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['student_id', 'tanggal_mulai', 'tanggal_selesai']);
            $table->index(['teacher_id', 'tanggal_mulai', 'tanggal_selesai']);
        });

        // Attendance Audit Logs
        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable()->comment('User who made the change');
            $table->string('aksi', 255)->comment('Action performed: scan_masuk, scan_pulang, approval, etc.');
            $table->string('tabel', 100)->comment('Table affected');
            $table->uuid('record_id')->nullable()->comment('ID of affected record');
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tabel', 'record_id']);
        });

        // Notification Settings per Tenant
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();

            // WhatsApp Settings
            $table->boolean('wa_enabled')->default(false);
            $table->string('wa_provider', 50)->default('fonnte')->comment('fonnte, wablas');
            $table->string('wa_api_key', 255)->nullable();
            $table->string('wa_sender_number', 32)->nullable();

            // Telegram Settings
            $table->boolean('telegram_enabled')->default(false);
            $table->string('telegram_bot_token', 255)->nullable();
            $table->string('telegram_default_chat_id', 100)->nullable();

            // Notification Templates
            $table->json('templates')->nullable()->comment('Custom message templates');

            // Notification Preferences
            $table->boolean('notify_check_in')->default(true);
            $table->boolean('notify_check_out')->default(true);
            $table->boolean('notify_late')->default(true);
            $table->boolean('notify_absent')->default(true);
            $table->boolean('notify_leave_approved')->default(true);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Scanner Devices (for tracking which devices are authorized)
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('device_name', 100);
            $table->string('device_token', 255)->unique();
            $table->string('location', 255)->nullable()->comment('Physical location description');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
        });

        // Offline Scan Queue (for syncing offline scans)
        Schema::create('offline_scan_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('scanner_device_id')->nullable();
            $table->string('unique_code', 64);
            $table->enum('scan_type', ['masuk', 'pulang']);
            $table->timestamp('scanned_at');
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('scanner_device_id')->references('id')->on('scanner_devices')->onDelete('set null');
            $table->index(['tenant_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_scan_queue');
        Schema::dropIfExists('scanner_devices');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('leave_permissions');
        Schema::dropIfExists('holidays');
    }
};
