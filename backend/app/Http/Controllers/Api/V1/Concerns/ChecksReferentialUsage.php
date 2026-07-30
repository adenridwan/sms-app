<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penjagaan hapus untuk periode akademik (tahun ajaran & semester).
 *
 * Semua foreign key ke `academic_years` dan `semesters` dideklarasikan
 * onDelete('cascade'), jadi tanpa pemeriksaan di sini satu penghapusan bisa
 * menyeret absensi, nilai, rapor, dan tagihan satu periode penuh. Model-nya
 * memakai SoftDeletes sehingga cascade DB memang tidak langsung meledak —
 * tetapi baris yang menggantung ke periode "terhapus" sama berbahayanya bagi
 * laporan, dan akan benar-benar ikut terhapus bila baris induk di-force delete.
 */
trait ChecksReferentialUsage
{
    /**
     * Tabel yang merujuk `academic_years`.
     *
     * `semesters` sengaja tidak ada di sini: semester dimiliki tahun ajaran dan
     * ikut terhapus bersamanya — tetapi hanya setelah SEMESTER_DEPENDENTS juga
     * bersih (lihat AcademicYearController::destroy).
     */
    protected const ACADEMIC_YEAR_DEPENDENTS = [
        'classrooms' => 'kelas',
        'student_enrollments' => 'pendaftaran siswa',
        'teacher_classrooms' => 'penempatan guru',
        'schedules' => 'jadwal pelajaran',
        'student_attendances' => 'absensi siswa',
        'attendance_summaries' => 'rekap absensi',
        'exams' => 'ujian',
        'report_cards' => 'rapor',
        'fee_structures' => 'struktur biaya',
        'student_fees' => 'tagihan siswa',
        'student_discounts' => 'diskon siswa',
        'financial_reports' => 'laporan keuangan',
    ];

    /**
     * Tabel yang merujuk `semesters`.
     *
     * Tiga tabel nilai di bawah (grade_components, student_grades, final_grades)
     * HANYA punya semester_id — tanpa academic_year_id — sehingga tidak
     * terjaring pemeriksaan tahun ajaran kalau tidak dicek lewat semesternya.
     */
    protected const SEMESTER_DEPENDENTS = [
        'schedules' => 'jadwal pelajaran',
        'student_attendances' => 'absensi siswa',
        'attendance_summaries' => 'rekap absensi',
        'exams' => 'ujian',
        'grade_components' => 'komponen nilai',
        'student_grades' => 'nilai siswa',
        'final_grades' => 'nilai akhir',
        'report_cards' => 'rapor',
    ];

    /**
     * Label data yang masih merujuk $ids, untuk ditampilkan ke pengguna.
     *
     * @param  array<string, string>  $tables  peta nama tabel → label bahasa Indonesia
     * @param  string|array<int, string>  $ids
     * @return array<int, string>  daftar label yang menghalangi penghapusan
     */
    protected function usedBy(array $tables, string $column, string|array $ids): array
    {
        $ids = array_values(array_filter((array) $ids));

        if ($ids === []) {
            return [];
        }

        $found = [];

        foreach ($tables as $table => $label) {
            $query = DB::table($table)->whereIn($column, $ids);

            // Sebagian tabel dependen memakai SoftDeletes, sebagian tidak.
            // Baris yang sudah dihapus tidak dihitung sebagai "masih dipakai".
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($query->exists()) {
                $found[] = $label;
            }
        }

        return $found;
    }
}
