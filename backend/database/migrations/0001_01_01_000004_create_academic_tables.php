<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic Years
        Schema::create('academic_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // e.g., "2024/2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Semesters
        Schema::create('semesters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->string('name'); // e.g., "Semester 1", "Semester 2"
            $table->tinyInteger('number'); // 1 or 2
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->unique(['academic_year_id', 'number']);
        });

        // Curricula
        Schema::create('curricula', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // e.g., "Kurikulum Merdeka", "K13"
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Grade Levels (Tingkat Kelas)
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // e.g., "Kelas 10", "Kelas 11"
            $table->string('code'); // e.g., "X", "XI", "XII"
            $table->tinyInteger('order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Majors/Departments (Jurusan)
        Schema::create('majors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // e.g., "IPA", "IPS", "Teknik Komputer"
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Classrooms (Kelas/Rombel)
        Schema::create('classrooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->uuid('grade_level_id');
            $table->uuid('major_id')->nullable();
            $table->uuid('homeroom_teacher_id')->nullable();
            $table->string('name'); // e.g., "X IPA 1"
            $table->string('code');
            $table->string('room')->nullable(); // Physical room location
            $table->integer('capacity')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('grade_level_id')->references('id')->on('grade_levels')->onDelete('cascade');
            $table->foreign('major_id')->references('id')->on('majors')->onDelete('set null');
            $table->foreign('homeroom_teacher_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['tenant_id', 'academic_year_id', 'code']);
        });

        // Subjects (Mata Pelajaran)
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('curriculum_id')->nullable();
            $table->string('name'); // e.g., "Matematika", "Bahasa Indonesia"
            $table->string('code');
            $table->string('category')->nullable(); // Wajib, Peminatan, Muatan Lokal
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('set null');
            $table->unique(['tenant_id', 'code']);
        });

        // Subject Grade Levels (Mata Pelajaran per Tingkat)
        Schema::create('subject_grade_levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('subject_id');
            $table->uuid('grade_level_id');
            $table->uuid('major_id')->nullable();
            $table->decimal('credit_hours', 3, 1)->default(2); // SKS/Jam
            $table->decimal('kkm', 5, 2)->default(75.00); // Kriteria Ketuntasan Minimal
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('grade_level_id')->references('id')->on('grade_levels')->onDelete('cascade');
            $table->foreign('major_id')->references('id')->on('majors')->onDelete('cascade');
            $table->unique(['subject_id', 'grade_level_id', 'major_id'], 'subject_grade_major_unique');
        });

        // Time Slots (Jam Pelajaran)
        Schema::create('time_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // e.g., "Jam 1", "Jam 2"
            $table->time('start_time');
            $table->time('end_time');
            $table->tinyInteger('order')->default(0);
            $table->boolean('is_break')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Schedules (Jadwal Pelajaran)
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->uuid('classroom_id');
            $table->uuid('subject_id');
            $table->uuid('teacher_id');
            $table->uuid('time_slot_id');
            $table->tinyInteger('day_of_week'); // 1=Monday, 7=Sunday
            $table->string('room')->nullable(); // Override classroom room
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('time_slot_id')->references('id')->on('time_slots')->onDelete('cascade');

            $table->unique(['classroom_id', 'semester_id', 'day_of_week', 'time_slot_id'], 'schedule_unique');
            $table->index(['teacher_id', 'semester_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('time_slots');
        Schema::dropIfExists('subject_grade_levels');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('majors');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('curricula');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
