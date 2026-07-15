<?php

namespace App\Domain\Attendance\Services;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeGeneratorService
{
    /**
     * Generate a new unique code for a student
     */
    public function generateStudentCode(Student $student): string
    {
        $code = 'STU-' . strtoupper(Str::random(12));

        // Ensure uniqueness
        while (Student::where('unique_code', $code)->exists()) {
            $code = 'STU-' . strtoupper(Str::random(12));
        }

        $student->update(['unique_code' => $code]);

        return $code;
    }

    /**
     * Generate a new unique code for a teacher
     */
    public function generateTeacherCode(Teacher $teacher): string
    {
        $code = 'TCH-' . strtoupper(Str::random(12));

        // Ensure uniqueness
        while (Teacher::where('unique_code', $code)->exists()) {
            $code = 'TCH-' . strtoupper(Str::random(12));
        }

        $teacher->update(['unique_code' => $code]);

        return $code;
    }

    /**
     * Generate QR code image for a student
     */
    public function generateStudentQrCode(Student $student, int $size = 300): string
    {
        $code = $student->unique_code;

        if (empty($code)) {
            $code = $this->generateStudentCode($student);
        }

        return $this->generateQrImage($code, $size);
    }

    /**
     * Generate QR code image for a teacher
     */
    public function generateTeacherQrCode(Teacher $teacher, int $size = 300): string
    {
        $code = $teacher->unique_code;

        if (empty($code)) {
            $code = $this->generateTeacherCode($teacher);
        }

        return $this->generateQrImage($code, $size);
    }

    /**
     * Generate QR code SVG string
     */
    public function generateQrSvg(string $code, int $size = 300): string
    {
        return QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->generate($code);
    }

    /**
     * Generate QR code as base64 PNG
     */
    public function generateQrImage(string $code, int $size = 300): string
    {
        $qrCode = QrCode::format('png')
            ->size($size)
            ->margin(1)
            ->generate($code);

        return 'data:image/png;base64,' . base64_encode($qrCode);
    }

    /**
     * Generate bulk QR codes for students in a classroom
     */
    public function generateBulkStudentQrCodes(array $studentIds, int $size = 200): array
    {
        $results = [];

        foreach ($studentIds as $studentId) {
            $student = Student::with('user')->find($studentId);
            if (!$student) {
                continue;
            }

            if (empty($student->unique_code)) {
                $this->generateStudentCode($student);
                $student->refresh();
            }

            $results[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
                'unique_code' => $student->unique_code,
                'qr_code' => $this->generateQrImage($student->unique_code, $size),
            ];
        }

        return $results;
    }

    /**
     * Regenerate QR code for a student (generates new unique_code)
     */
    public function regenerateStudentQrCode(Student $student, int $size = 300): array
    {
        $oldCode = $student->unique_code;
        $newCode = $this->generateStudentCode($student);

        return [
            'old_code' => $oldCode,
            'new_code' => $newCode,
            'qr_code' => $this->generateQrImage($newCode, $size),
        ];
    }

    /**
     * Regenerate QR code for a teacher (generates new unique_code)
     */
    public function regenerateTeacherQrCode(Teacher $teacher, int $size = 300): array
    {
        $oldCode = $teacher->unique_code;
        $newCode = $this->generateTeacherCode($teacher);

        return [
            'old_code' => $oldCode,
            'new_code' => $newCode,
            'qr_code' => $this->generateQrImage($newCode, $size),
        ];
    }
}
