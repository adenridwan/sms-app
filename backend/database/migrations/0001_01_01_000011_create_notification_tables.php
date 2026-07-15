<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notification Templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('subject');
            $table->text('body');
            $table->enum('type', ['email', 'sms', 'push', 'whatsapp', 'in_app'])->default('email');
            $table->json('variables')->nullable(); // Available template variables
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Notifications (In-App)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->string('icon')->nullable();
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['user_id', 'read_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Notification Logs (Sent notifications history)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->string('recipient'); // email, phone, device_token
            $table->enum('channel', ['email', 'sms', 'push', 'whatsapp']);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'channel']);
        });

        // Push Notification Devices
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token');
            $table->enum('platform', ['ios', 'android', 'web'])->default('android');
            $table->string('device_name')->nullable();
            $table->string('device_model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'token']);
        });

        // Announcements
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by');
            $table->string('title');
            $table->text('content');
            $table->string('image')->nullable();
            $table->json('attachments')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->json('target_audience')->nullable(); // roles, classrooms, grade_levels
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('send_notification')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['tenant_id', 'is_published', 'publish_at']);
        });

        // Announcement Reads
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->uuid('announcement_id');
            $table->uuid('user_id');
            $table->timestamp('read_at');

            $table->foreign('announcement_id')->references('id')->on('announcements')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
    }
};
