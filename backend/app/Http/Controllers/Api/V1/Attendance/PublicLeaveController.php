<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Services\LeaveApprovalService;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLeaveController extends ApiController
{
    public function __construct(
        private LeaveApprovalService $approvalService
    ) {}

    /**
     * Look up student by NIS for leave submission
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nis' => ['required', 'string', 'max:50'],
        ]);

        $student = Student::with(['user', 'enrollments.classroom'])
            ->where('nis', $data['nis'])
            ->where('status', 'active')
            ->first();

        if (!$student) {
            return $this->error('Siswa tidak ditemukan atau tidak aktif', 404);
        }

        $enrollment = $student->currentEnrollment();

        return $this->success([
            'student_id' => $student->id,
            'nis' => $student->nis,
            'name' => $student->user?->full_name,
            'classroom' => $enrollment?->classroom?->name,
        ]);
    }

    /**
     * Submit leave permission from public portal
     */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'tipe_izin' => ['required', 'in:sakit,izin'],
            'alasan' => ['required', 'string', 'max:1000'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'guardian_name' => ['required', 'string', 'max:100'],
            'guardian_phone' => ['required', 'string', 'max:20'],
        ]);

        $student = Student::find($data['student_id']);
        if (!$student) {
            return $this->error('Siswa tidak ditemukan', 404);
        }

        // Check for overlapping leave
        if ($this->approvalService->hasOverlappingLeave(
            $data['student_id'],
            null,
            $data['tanggal_mulai'],
            $data['tanggal_selesai']
        )) {
            return $this->error('Sudah ada izin yang tumpang tindih dengan rentang tanggal ini', 422);
        }

        // Handle file upload
        $buktiPath = null;
        if ($request->hasFile('bukti')) {
            $buktiPath = $request->file('bukti')->store('leave-permissions', 'public');
        }

        $permission = $this->approvalService->createLeavePermission([
            'tenant_id' => $student->tenant_id,
            'student_id' => $data['student_id'],
            'teacher_id' => null,
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'tipe_izin' => $data['tipe_izin'],
            'alasan' => "Diajukan oleh: {$data['guardian_name']} ({$data['guardian_phone']})\n\n{$data['alasan']}",
            'bukti' => $buktiPath,
        ]);

        return $this->created([
            'permission_id' => $permission->id,
            'status' => 'pending',
            'message' => 'Izin berhasil diajukan dan menunggu persetujuan',
        ], 'Izin berhasil diajukan');
    }

    /**
     * Check leave permission status
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'permission_id' => ['required', 'uuid'],
        ]);

        $permission = LeavePermission::with(['student.user'])
            ->find($data['permission_id']);

        if (!$permission) {
            return $this->error('Izin tidak ditemukan', 404);
        }

        return $this->success([
            'permission_id' => $permission->id,
            'student_name' => $permission->student?->user?->full_name,
            'tanggal_mulai' => $permission->tanggal_mulai->format('d/m/Y'),
            'tanggal_selesai' => $permission->tanggal_selesai->format('d/m/Y'),
            'tipe_izin' => $permission->tipe_izin->label(),
            'status' => $permission->status->value,
            'status_label' => $permission->status->label(),
            'rejection_reason' => $permission->rejection_reason,
            'submitted_at' => $permission->created_at->format('d/m/Y H:i'),
            'processed_at' => $permission->approved_at?->format('d/m/Y H:i'),
        ]);
    }
}
