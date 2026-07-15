<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Students
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->unique();
            $table->string('nis')->comment('Nomor Induk Siswa');
            $table->string('nisn')->nullable()->comment('Nomor Induk Siswa Nasional');
            $table->date('entry_date');
            $table->enum('entry_type', ['new', 'transfer', 'return'])->default('new');
            $table->string('previous_school')->nullable();
            $table->enum('status', ['active', 'inactive', 'graduated', 'transferred', 'dropped_out'])->default('active');
            $table->date('graduation_date')->nullable();
            $table->string('graduation_certificate_number')->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['tenant_id', 'nis']);
            $table->index(['tenant_id', 'nisn']);
            $table->index(['tenant_id', 'status']);
        });

        // Student Parents/Guardians
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('user_id')->nullable(); // If parent has user account
            $table->enum('relationship', ['father', 'mother', 'guardian', 'other']);
            $table->string('name');
            $table->string('nik')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            $table->string('income_range')->nullable();
            $table->text('address')->nullable();
            $table->string('education_level')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['student_id', 'relationship']);
        });

        // Student Enrollments (Pendaftaran per Tahun Ajaran)
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('academic_year_id');
            $table->uuid('classroom_id');
            $table->string('student_number_in_class')->nullable(); // Nomor absen
            $table->enum('status', ['active', 'promoted', 'retained', 'transferred', 'dropped'])->default('active');
            $table->date('enrollment_date');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['classroom_id', 'academic_year_id']);
        });

        // Student Documents
        Schema::create('student_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->string('type'); // birth_certificate, family_card, photo, etc.
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->boolean('is_verified')->default(false);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });

        // Student Achievements
        Schema::create('student_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['academic', 'sports', 'arts', 'science', 'other']);
            $table->enum('level', ['school', 'district', 'city', 'province', 'national', 'international']);
            $table->string('rank')->nullable(); // 1st, 2nd, etc.
            $table->date('achievement_date');
            $table->string('organizer')->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index(['student_id', 'category']);
        });

        // Student Violations/Discipline
        Schema::create('student_violations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('reported_by');
            $table->string('violation_type');
            $table->text('description');
            $table->date('violation_date');
            $table->integer('points')->default(0);
            $table->text('action_taken')->nullable();
            $table->uuid('handled_by')->nullable();
            $table->enum('status', ['reported', 'investigating', 'resolved', 'dismissed'])->default('reported');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('handled_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_violations');
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('students');
    }
};
