<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Departments (Bidang/Unit)
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Self-referencing foreign key (harus terpisah setelah tabel dibuat)
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('departments')->onDelete('set null');
        });

        // Positions (Jabatan)
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->integer('level')->default(0); // Hierarchy level
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Teachers
        Schema::create('teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->unique();
            $table->string('nip')->nullable()->comment('Nomor Induk Pegawai');
            $table->string('nuptk')->nullable()->comment('Nomor Unik Pendidik dan Tenaga Kependidikan');
            $table->date('join_date');
            $table->enum('employment_status', ['permanent', 'contract', 'honorary', 'part_time'])->default('permanent');
            $table->enum('certification_status', ['certified', 'not_certified', 'in_progress'])->default('not_certified');
            $table->string('certification_number')->nullable();
            $table->string('education_level')->nullable(); // S1, S2, S3
            $table->string('education_major')->nullable();
            $table->string('university')->nullable();
            $table->integer('teaching_experience_years')->default(0);
            $table->enum('status', ['active', 'inactive', 'on_leave', 'retired', 'terminated'])->default('active');
            $table->json('additional_info')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['tenant_id', 'nip']);
            $table->index(['tenant_id', 'status']);
        });

        // Teacher Subject Assignments (Mata Pelajaran yang Diajar)
        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->unique(['teacher_id', 'subject_id']);
        });

        // Teacher Position History
        Schema::create('teacher_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('teacher_id');
            $table->uuid('department_id')->nullable();
            $table->uuid('position_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            $table->index(['teacher_id', 'is_current']);
        });

        // Staff (Non-Teaching)
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->unique();
            $table->uuid('department_id')->nullable();
            $table->uuid('position_id')->nullable();
            $table->string('employee_id')->nullable();
            $table->date('join_date');
            $table->enum('employment_status', ['permanent', 'contract', 'honorary', 'part_time'])->default('permanent');
            $table->string('education_level')->nullable();
            $table->enum('status', ['active', 'inactive', 'on_leave', 'retired', 'terminated'])->default('active');
            $table->json('additional_info')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->unique(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Employee Leave Requests
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('leave_type'); // sick, annual, maternity, etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->text('reason');
            $table->string('attachment')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('teacher_positions');
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
