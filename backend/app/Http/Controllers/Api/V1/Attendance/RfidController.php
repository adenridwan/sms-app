<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Rules\UniqueRfidCode;
use App\Domain\Attendance\Services\RfidCodeGenerator;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidController extends ApiController
{
    public function __construct(
        private RfidCodeGenerator $generator,
    ) {}

    /**
     * Assign/change/clear the RFID card code for a student.
     */
    public function updateStudent(Student $student, Request $request): JsonResponse
    {
        if (! $request->user()->can('students.update')) {
            return $this->forbidden('Anda tidak memiliki izin mengubah data siswa.');
        }

        $data = $request->validate([
            'rfid_code' => [
                'nullable', 'string', 'max:100',
                new UniqueRfidCode($student->tenant_id, excludeStudentId: $student->id),
            ],
        ]);

        $student->update(['rfid_code' => $data['rfid_code'] ?? null]);

        return $this->success([
            'student_id' => $student->id,
            'rfid_code' => $student->fresh()->rfid_code,
        ], 'Kode RFID siswa berhasil diperbarui');
    }

    /**
     * Assign/change/clear the RFID card code for a teacher.
     */
    public function updateTeacher(Teacher $teacher, Request $request): JsonResponse
    {
        if (! $request->user()->can('teachers.update')) {
            return $this->forbidden('Anda tidak memiliki izin mengubah data guru.');
        }

        $data = $request->validate([
            'rfid_code' => [
                'nullable', 'string', 'max:100',
                new UniqueRfidCode($teacher->tenant_id, excludeTeacherId: $teacher->id),
            ],
        ]);

        $teacher->update(['rfid_code' => $data['rfid_code'] ?? null]);

        return $this->success([
            'teacher_id' => $teacher->id,
            'rfid_code' => $teacher->fresh()->rfid_code,
        ], 'Kode RFID guru berhasil diperbarui');
    }

    /**
     * Terbitkan kode RFID baru untuk guru (dipakai bila sekolah menerbitkan
     * sendiri kartunya, bukan memakai UID bawaan kartu).
     *
     * Langsung disimpan supaya keunikannya dijamin pada saat yang sama
     * dengan pembuatannya — kode yang hanya "diusulkan" bisa keburu diambil
     * admin lain sebelum ditekan Simpan.
     */
    public function generateTeacher(Teacher $teacher, Request $request): JsonResponse
    {
        if (! $request->user()->can('teachers.update')) {
            return $this->forbidden('Anda tidak memiliki izin mengubah data guru.');
        }

        $previousCode = $teacher->rfid_code;

        $teacher->update(['rfid_code' => $this->generator->forTeacher($teacher)]);

        return $this->success([
            'teacher_id' => $teacher->id,
            'rfid_code' => $teacher->fresh()->rfid_code,
            'previous_rfid_code' => $previousCode,
        ], 'Kode RFID baru berhasil dibuat');
    }
}
