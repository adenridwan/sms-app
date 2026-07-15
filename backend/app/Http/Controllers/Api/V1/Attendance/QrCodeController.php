<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Services\QrCodeGeneratorService;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrCodeController extends ApiController
{
    public function __construct(
        private QrCodeGeneratorService $qrService
    ) {}

    /**
     * Get QR code for a student
     */
    public function student(Student $student): JsonResponse
    {
        $student->load('user');

        $qrCode = $this->qrService->generateStudentQrCode($student);

        return $this->success([
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
            ],
            'unique_code' => $student->unique_code,
            'qr_code' => $qrCode,
        ]);
    }

    /**
     * Regenerate QR code for a student
     */
    public function regenerateStudent(Student $student): JsonResponse
    {
        $result = $this->qrService->regenerateStudentQrCode($student);

        return $this->success([
            'student_id' => $student->id,
            'old_code' => $result['old_code'],
            'new_code' => $result['new_code'],
            'qr_code' => $result['qr_code'],
        ], 'QR code berhasil diperbarui');
    }

    /**
     * Get QR codes for students in a classroom
     */
    public function bulkStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
            'size' => ['integer', 'min:100', 'max:500'],
        ]);

        $size = $data['size'] ?? 200;

        // Get students in classroom
        $studentIds = StudentEnrollment::where('classroom_id', $data['classroom_id'])
            ->where('status', 'active')
            ->pluck('student_id')
            ->toArray();

        if (empty($studentIds)) {
            return $this->success(['students' => []], 'Tidak ada siswa di kelas ini');
        }

        $results = $this->qrService->generateBulkStudentQrCodes($studentIds, $size);

        return $this->success([
            'classroom_id' => $data['classroom_id'],
            'count' => count($results),
            'students' => $results,
        ]);
    }

    /**
     * Get QR code for a teacher
     */
    public function teacher(Teacher $teacher): JsonResponse
    {
        $teacher->load('user');

        $qrCode = $this->qrService->generateTeacherQrCode($teacher);

        return $this->success([
            'teacher' => [
                'id' => $teacher->id,
                'nip' => $teacher->nip,
                'name' => $teacher->user?->full_name,
            ],
            'unique_code' => $teacher->unique_code,
            'qr_code' => $qrCode,
        ]);
    }

    /**
     * Regenerate QR code for a teacher
     */
    public function regenerateTeacher(Teacher $teacher): JsonResponse
    {
        $result = $this->qrService->regenerateTeacherQrCode($teacher);

        return $this->success([
            'teacher_id' => $teacher->id,
            'old_code' => $result['old_code'],
            'new_code' => $result['new_code'],
            'qr_code' => $result['qr_code'],
        ], 'QR code berhasil diperbarui');
    }

    /**
     * Get QR codes for all teachers
     */
    public function bulkTeachers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'size' => ['integer', 'min:100', 'max:500'],
        ]);

        $size = $data['size'] ?? 200;

        $teachers = Teacher::with('user')
            ->active()
            ->get();

        $results = [];
        foreach ($teachers as $teacher) {
            if (empty($teacher->unique_code)) {
                $this->qrService->generateTeacherCode($teacher);
                $teacher->refresh();
            }

            $results[] = [
                'teacher_id' => $teacher->id,
                'nip' => $teacher->nip,
                'name' => $teacher->user?->full_name,
                'unique_code' => $teacher->unique_code,
                'qr_code' => $this->qrService->generateQrImage($teacher->unique_code, $size),
            ];
        }

        return $this->success([
            'count' => count($results),
            'teachers' => $results,
        ]);
    }

    /**
     * Download QR code as image
     */
    public function downloadStudent(Student $student, Request $request)
    {
        $size = $request->get('size', 300);
        $format = $request->get('format', 'png');

        $student->load('user');

        if (empty($student->unique_code)) {
            $this->qrService->generateStudentCode($student);
            $student->refresh();
        }

        if ($format === 'svg') {
            $svg = $this->qrService->generateQrSvg($student->unique_code, $size);

            return response($svg)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', "attachment; filename=\"qr-{$student->nis}.svg\"");
        }

        // Return base64 image data
        $base64 = $this->qrService->generateQrImage($student->unique_code, $size);

        return $this->success([
            'nis' => $student->nis,
            'name' => $student->user?->full_name,
            'qr_code' => $base64,
            'unique_code' => $student->unique_code,
        ]);
    }

    /**
     * Download QR code as image for teacher
     */
    public function downloadTeacher(Teacher $teacher, Request $request)
    {
        $size = $request->get('size', 300);

        $teacher->load('user');

        if (empty($teacher->unique_code)) {
            $this->qrService->generateTeacherCode($teacher);
            $teacher->refresh();
        }

        $base64 = $this->qrService->generateQrImage($teacher->unique_code, $size);

        return $this->success([
            'nip' => $teacher->nip,
            'name' => $teacher->user?->full_name,
            'qr_code' => $base64,
            'unique_code' => $teacher->unique_code,
        ]);
    }
}
