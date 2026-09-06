<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan index untuk optimasi query yang sering dipanggil:
 * - teachingClassroomIds() di User model (4 query per panggilan)
 * - Statistik absensi di DashboardStatsService
 * - Lookup tagihan siswa
 *
 * Index ini mengurangi response time dari ~8 detik ke ~2-3 detik
 * di production (network latency × jumlah query).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index untuk schedules: dipakai teachingClassroomIds()
        // Partial index (WHERE deleted_at IS NULL) lebih efisien
        if (! $this->indexExists('schedules', 'idx_schedules_teacher_year_active')) {
            DB::statement('
                CREATE INDEX idx_schedules_teacher_year_active
                ON schedules (teacher_id, academic_year_id, is_active)
                WHERE deleted_at IS NULL
            ');
        }

        // Index untuk teacher_classrooms: dipakai teachingClassroomIds()
        if (! $this->indexExists('teacher_classrooms', 'idx_teacher_classrooms_teacher_year')) {
            Schema::table('teacher_classrooms', function (Blueprint $table) {
                $table->index(['teacher_id', 'academic_year_id'], 'idx_teacher_classrooms_teacher_year');
            });
        }

        // Index untuk classrooms: lookup homeroom teacher
        if (! $this->indexExists('classrooms', 'idx_classrooms_homeroom_year')) {
            Schema::table('classrooms', function (Blueprint $table) {
                $table->index(['homeroom_teacher_id', 'academic_year_id'], 'idx_classrooms_homeroom_year');
            });
        }

        // Index untuk student_attendances: query harian dan bulanan
        if (! $this->indexExists('student_attendances', 'idx_student_attendances_date_classroom')) {
            Schema::table('student_attendances', function (Blueprint $table) {
                $table->index(['attendance_date', 'classroom_id'], 'idx_student_attendances_date_classroom');
            });
        }

        // Index untuk student_attendances: lookup per siswa
        if (! $this->indexExists('student_attendances', 'idx_student_attendances_student_date')) {
            Schema::table('student_attendances', function (Blueprint $table) {
                $table->index(['student_id', 'attendance_date'], 'idx_student_attendances_student_date');
            });
        }

        // Index untuk student_fees: sum remaining per siswa
        if (! $this->indexExists('student_fees', 'idx_student_fees_student_remaining')) {
            DB::statement('
                CREATE INDEX idx_student_fees_student_remaining
                ON student_fees (student_id, remaining_amount)
                WHERE deleted_at IS NULL
            ');
        }

        // Index untuk academic_years: lookup tahun aktif per tenant
        if (! $this->indexExists('academic_years', 'idx_academic_years_active_tenant')) {
            Schema::table('academic_years', function (Blueprint $table) {
                $table->index(['is_active', 'tenant_id'], 'idx_academic_years_active_tenant');
            });
        }
    }

    public function down(): void
    {
        // Drop partial indexes via raw SQL
        DB::statement('DROP INDEX IF EXISTS idx_schedules_teacher_year_active');
        DB::statement('DROP INDEX IF EXISTS idx_student_fees_student_remaining');

        Schema::table('teacher_classrooms', function (Blueprint $table) {
            $table->dropIndex('idx_teacher_classrooms_teacher_year');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex('idx_classrooms_homeroom_year');
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropIndex('idx_student_attendances_date_classroom');
            $table->dropIndex('idx_student_attendances_student_date');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropIndex('idx_academic_years_active_tenant');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("
            SELECT 1 FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ", [$table, $indexName]);

        return count($result) > 0;
    }
};
