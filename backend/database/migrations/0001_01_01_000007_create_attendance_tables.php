<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance Settings
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->time('check_in_start')->default('06:00:00');
            $table->time('check_in_end')->default('07:30:00');
            $table->time('check_out_start')->default('14:00:00');
            $table->time('check_out_end')->default('17:00:00');
            $table->integer('late_tolerance_minutes')->default(15);
            $table->boolean('require_location')->default(false);
            $table->boolean('require_photo')->default(false);
            $table->decimal('location_radius', 10, 2)->nullable()->comment('in meters');
            $table->decimal('school_latitude', 10, 8)->nullable();
            $table->decimal('school_longitude', 11, 8)->nullable();
            $table->json('working_days')->nullable(); // [1,2,3,4,5] for Mon-Fri
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Student Attendance
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('classroom_id');
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'sick', 'permitted', 'alpha'])->default('present');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->text('notes')->nullable();
            $table->string('excuse_document')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['student_id', 'attendance_date'], 'student_attendance_unique');
            $table->index(['classroom_id', 'attendance_date']);
            $table->index(['tenant_id', 'attendance_date']);
        });

        // Subject/Schedule Attendance (Per Mata Pelajaran)
        Schema::create('subject_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('schedule_id');
            $table->uuid('student_id');
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'sick', 'permitted'])->default('present');
            $table->text('notes')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['schedule_id', 'student_id', 'attendance_date'], 'subject_attendance_unique');
        });

        // Teacher/Staff Attendance
        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'sick', 'permitted', 'on_duty', 'work_from_home'])->default('present');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->string('check_in_photo')->nullable();
            $table->string('check_out_photo')->nullable();
            $table->text('notes')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['user_id', 'attendance_date'], 'employee_attendance_unique');
            $table->index(['tenant_id', 'attendance_date']);
        });

        // Attendance Summary (Monthly/Semester)
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuidMorphs('attendable'); // student or user
            $table->uuid('academic_year_id')->nullable();
            $table->uuid('semester_id')->nullable();
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->integer('total_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->integer('sick_days')->default(0);
            $table->integer('permitted_days')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');

            $table->unique(['attendable_type', 'attendable_id', 'month', 'year'], 'attendance_summary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('subject_attendances');
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('attendance_settings');
    }
};
