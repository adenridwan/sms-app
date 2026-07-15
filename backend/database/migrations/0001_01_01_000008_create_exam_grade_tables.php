<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exam Types
        Schema::create('exam_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // UH, UTS, UAS, Quiz, Tugas
            $table->string('code');
            $table->decimal('default_weight', 5, 2)->default(1.00);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Exams
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->uuid('subject_id');
            $table->uuid('exam_type_id');
            $table->uuid('classroom_id')->nullable(); // null = all classrooms
            $table->uuid('teacher_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('exam_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->decimal('max_score', 5, 2)->default(100.00);
            $table->decimal('passing_score', 5, 2)->default(75.00);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->enum('status', ['draft', 'scheduled', 'ongoing', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('exam_type_id')->references('id')->on('exam_types')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['semester_id', 'subject_id', 'exam_date']);
        });

        // Exam Scores
        Schema::create('exam_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_remedial')->default(false);
            $table->uuid('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('graded_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['exam_id', 'student_id', 'is_remedial'], 'exam_score_unique');
        });

        // Remedial Exams
        Schema::create('remedials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->decimal('original_score', 5, 2);
            $table->decimal('remedial_score', 5, 2)->nullable();
            $table->date('remedial_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'scheduled', 'completed', 'waived'])->default('pending');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');

            $table->unique(['exam_id', 'student_id']);
        });

        // Grade Components (Komponen Nilai)
        Schema::create('grade_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subject_id');
            $table->uuid('semester_id');
            $table->string('name'); // Pengetahuan, Keterampilan, Sikap
            $table->string('code');
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
        });

        // Student Grades (Nilai Akhir per Komponen)
        Schema::create('student_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('classroom_id');
            $table->uuid('semester_id');
            $table->uuid('grade_component_id')->nullable();
            $table->decimal('score', 5, 2);
            $table->string('grade_letter')->nullable(); // A, B, C, D, E
            $table->string('predicate')->nullable(); // Sangat Baik, Baik, Cukup, Kurang
            $table->text('description')->nullable();
            $table->uuid('graded_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('grade_component_id')->references('id')->on('grade_components')->onDelete('set null');
            $table->foreign('graded_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['student_id', 'semester_id']);
        });

        // Final Grades (Nilai Rapor)
        Schema::create('final_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('classroom_id');
            $table->uuid('semester_id');
            $table->decimal('knowledge_score', 5, 2)->nullable();
            $table->decimal('skill_score', 5, 2)->nullable();
            $table->decimal('attitude_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2);
            $table->string('grade_letter')->nullable();
            $table->string('predicate')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->boolean('is_passed')->default(true);
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['student_id', 'subject_id', 'semester_id'], 'final_grade_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_grades');
        Schema::dropIfExists('student_grades');
        Schema::dropIfExists('grade_components');
        Schema::dropIfExists('remedials');
        Schema::dropIfExists('exam_scores');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_types');
    }
};
