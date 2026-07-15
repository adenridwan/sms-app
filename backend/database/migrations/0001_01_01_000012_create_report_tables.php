<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Report Cards (Rapor)
        Schema::create('report_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('classroom_id');
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->string('report_number')->nullable();
            $table->integer('total_subjects')->default(0);
            $table->decimal('average_score', 5, 2)->nullable();
            $table->integer('rank_in_class')->nullable();
            $table->integer('total_students_in_class')->nullable();
            $table->integer('total_present_days')->default(0);
            $table->integer('total_absent_days')->default(0);
            $table->integer('total_sick_days')->default(0);
            $table->integer('total_permitted_days')->default(0);
            $table->text('homeroom_notes')->nullable();
            $table->text('principal_notes')->nullable();
            $table->enum('promotion_status', ['promoted', 'retained', 'conditional', 'pending'])->default('pending');
            $table->string('next_classroom')->nullable();
            $table->uuid('homeroom_teacher_id')->nullable();
            $table->uuid('principal_id')->nullable();
            $table->date('issued_date')->nullable();
            $table->enum('status', ['draft', 'reviewed', 'approved', 'published', 'distributed'])->default('draft');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('homeroom_teacher_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('principal_id')->references('id')->on('users')->onDelete('set null');

            $table->unique(['student_id', 'semester_id'], 'report_card_unique');
        });

        // Report Card Extracurricular
        Schema::create('report_card_extracurriculars', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_card_id');
            $table->string('activity_name');
            $table->string('predicate')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('report_card_id')->references('id')->on('report_cards')->onDelete('cascade');
        });

        // Report Card Character Assessments
        Schema::create('report_card_characters', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_card_id');
            $table->string('character_name'); // Kejujuran, Kedisiplinan, etc.
            $table->string('predicate'); // Sangat Baik, Baik, Cukup, Kurang
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('report_card_id')->references('id')->on('report_cards')->onDelete('cascade');
        });

        // Generated Reports
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('generated_by');
            $table->string('name');
            $table->string('type'); // attendance, finance, academic, etc.
            $table->json('parameters')->nullable();
            $table->string('format')->default('pdf'); // pdf, excel, csv
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_card_characters');
        Schema::dropIfExists('report_card_extracurriculars');
        Schema::dropIfExists('report_cards');
    }
};
