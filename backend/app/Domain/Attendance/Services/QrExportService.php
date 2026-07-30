<?php

namespace App\Domain\Attendance\Services;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Collection;

/**
 * Menyiapkan data untuk export QR/RFID (Excel, ZIP gambar, PDF kartu).
 *
 * Menormalkan siswa/guru menjadi baris seragam, dan (opsional) mengisi
 * unique_code + rfid_code yang masih kosong sehingga hasil export lengkap.
 */
class QrExportService
{
    public function __construct(private QrCodeGeneratorService $qr) {}

    /**
     * Baris export untuk siswa.
     *
     * @param  string $scope         'class' | 'all'
     * @param  ?string $classroomId  wajib bila scope = 'class'
     * @param  bool $generateRfid    isi rfid_code yang kosong
     * @return Collection<int, array>
     */
    public function studentRows(string $scope, ?string $classroomId, bool $generateRfid): Collection
    {
        // Eager-load: strict mode (preventLazyLoading) aktif. currentClass
        // adalah HasOneThrough → kelas aktif siswa.
        $query = Student::with(['user.profile', 'currentClass'])->where('status', 'active');

        if ($scope === 'class') {
            $ids = StudentEnrollment::where('classroom_id', $classroomId)
                ->where('status', 'active')
                ->pluck('student_id');
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function (Student $s) use ($generateRfid) {
            $this->ensureCodes(
                $s,
                fn () => $this->qr->generateStudentCode($s),
                fn () => $this->qr->generateStudentRfid($s),
                $generateRfid,
            );

            return [
                'kind' => 'student',
                'identifier' => $s->nis,
                'identifier_label' => 'NIS',
                'name' => $s->user?->full_name ?? '-',
                'classroom' => $s->currentClass?->name ?? '-',
                'unique_code' => $s->unique_code,
                'rfid_code' => $s->rfid_code ?? '',
            ];
        })->values();
    }

    /**
     * Baris export untuk seluruh guru aktif.
     *
     * @return Collection<int, array>
     */
    public function teacherRows(bool $generateRfid): Collection
    {
        return Teacher::with('user.profile')->active()->get()->map(function (Teacher $t) use ($generateRfid) {
            $this->ensureCodes(
                $t,
                fn () => $this->qr->generateTeacherCode($t),
                fn () => $this->qr->generateTeacherRfid($t),
                $generateRfid,
            );

            return [
                'kind' => 'teacher',
                'identifier' => $t->nip,
                'identifier_label' => 'NIP',
                'name' => $t->user?->full_name ?? '-',
                'classroom' => '-',
                'unique_code' => $t->unique_code,
                'rfid_code' => $t->rfid_code ?? '',
            ];
        })->values();
    }

    /**
     * Pastikan unique_code (selalu) & rfid_code (bila diminta) terisi.
     */
    private function ensureCodes($model, callable $genQr, callable $genRfid, bool $generateRfid): void
    {
        if (empty($model->unique_code)) {
            $genQr();
            $model->refresh();
        }

        if ($generateRfid && empty($model->rfid_code)) {
            $genRfid();
            $model->refresh();
        }
    }
}
