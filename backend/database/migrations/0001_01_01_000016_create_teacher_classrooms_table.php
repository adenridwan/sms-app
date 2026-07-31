<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penugasan manual guru <-> kelas (rumus R3 pada ROLE-ACCESS-PLAN.md).
     *
     * teacher_id merujuk users.id, mengikuti konvensi schedules.teacher_id
     * dan classrooms.homeroom_teacher_id yang juga merujuk users.
     */
    public function up(): void
    {
        Schema::create('teacher_classrooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('teacher_id');
            $table->uuid('classroom_id');
            $table->uuid('academic_year_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');

            $table->unique(['teacher_id', 'classroom_id', 'academic_year_id'], 'teacher_classroom_year_unique');
            $table->index(['teacher_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_classrooms');
    }
};
