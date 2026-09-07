<?php

namespace App\Domain\Attendance\Services;

use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
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
     * Generate a new unique code for a staff member
     */
    public function generateStaffCode(Staff $staff): string
    {
        $code = 'STF-' . strtoupper(Str::random(12));

        // Ensure uniqueness
        while (Staff::where('unique_code', $code)->exists()) {
            $code = 'STF-' . strtoupper(Str::random(12));
        }

        $staff->update(['unique_code' => $code]);

        return $code;
    }

    /**
     * Generate a unique RFID code for a student (kartu RFID writable).
     * Unik lintas students+teachers dalam tenant (lihat UniqueRfidCode).
     */
    public function generateStudentRfid(Student $student): string
    {
        $code = $this->uniqueRfidCode($student->tenant_id);
        $student->update(['rfid_code' => $code]);

        return $code;
    }

    /**
     * Generate a unique RFID code for a teacher.
     */
    public function generateTeacherRfid(Teacher $teacher): string
    {
        $code = $this->uniqueRfidCode($teacher->tenant_id);
        $teacher->update(['rfid_code' => $code]);

        return $code;
    }

    /**
     * Kode RFID unik lintas tabel students & teachers pada satu tenant.
     */
    private function uniqueRfidCode(string $tenantId): string
    {
        do {
            $code = 'RF-' . strtoupper(Str::random(10));
            $clash = Student::where('tenant_id', $tenantId)->where('rfid_code', $code)->exists()
                || Teacher::where('tenant_id', $tenantId)->where('rfid_code', $code)->exists();
        } while ($clash);

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
     * Generate QR code image for a staff member
     */
    public function generateStaffQrCode(Staff $staff, int $size = 300): string
    {
        $code = $staff->unique_code;

        if (empty($code)) {
            $code = $this->generateStaffCode($staff);
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
     * Generate QR code as raw PNG bytes (via GD, tanpa imagick) memakai
     * chillerlan/php-qrcode. Dipakai untuk export ZIP/kartu yang butuh raster.
     *
     * $size = perkiraan lebar px; QR ~25-33 modul, jadi scale = size/30.
     */
    public function generateQrPng(string $code, int $size = 512): string
    {
        $scale = max(3, (int) round($size / 30));

        $options = new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => $scale,
            'quietzoneSize' => 1,
        ]);

        return (new \chillerlan\QRCode\QRCode($options))->render($code);
    }

    /**
     * Generate QR code as a base64 SVG data URI.
     *
     * Deliberately SVG, not PNG: the PNG backend requires the PHP `imagick`
     * extension, which isn't guaranteed to be installed (confirmed absent
     * on this Windows dev box — this was the root cause of "Cetak Kartu"
     * failing, see ATTENDANCE-PLAN.md). SVG needs no native image library
     * and renders identically in a plain <img src="..."> tag.
     */
    public function generateQrImage(string $code, int $size = 300): string
    {
        $qrCode = QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->generate($code);

        return 'data:image/svg+xml;base64,' . base64_encode($qrCode);
    }

    /**
     * Generate bulk QR codes for students in a classroom
     */
    public function generateBulkStudentQrCodes(array $studentIds, int $size = 200): array
    {
        $results = [];

        // `user.profile` WAJIB ikut: `name` di bawah membaca `full_name`, yang
        // dirakit dari `user_profiles` — tanpa ini setiap kartu memicu query
        // profil sendiri-sendiri (N+1), dan begitu pemanggilnya mengambil model
        // lewat koleksi (`->get()`) Eloquent strict mode melemparnya sebagai
        // LazyLoadingViolationException. Lihat bug tab "Guru" pada bulkTeachers().
        $students = Student::with('user.profile')
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        foreach ($studentIds as $studentId) {
            $student = $students->get($studentId);
            if (!$student) {
                continue;
            }

            // Tanpa refresh(): generateStudentCode() sudah menulis kode baru ke
            // instance ini lewat update(). refresh() justru memuat ulang relasi
            // yang sudah ter-eager-load secara dangkal (hanya `user`, tanpa
            // `user.profile`) sehingga `full_name` di bawah balik lazy-load.
            if (empty($student->unique_code)) {
                $this->generateStudentCode($student);
            }

            $results[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
                'photo_url' => $student->user?->avatar ? asset('storage/' . $student->user->avatar) : null,
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

    /**
     * Regenerate QR code for a staff member (generates new unique_code)
     */
    public function regenerateStaffQrCode(Staff $staff, int $size = 300): array
    {
        $oldCode = $staff->unique_code;
        $newCode = $this->generateStaffCode($staff);

        return [
            'old_code' => $oldCode,
            'new_code' => $newCode,
            'qr_code' => $this->generateQrImage($newCode, $size),
        ];
    }
}
