<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Enums\LeaveStatus;
use App\Domain\Attendance\Enums\LeaveType;
use App\Domain\Attendance\Services\LeaveApprovalService;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeavePermissionController extends ApiController
{
    public function __construct(
        private LeaveApprovalService $approvalService
    ) {}

    /**
     * List leave permissions
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeavePermission::with(['student.user', 'teacher.user', 'approver'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->teacher_id, fn($q, $id) => $q->where('teacher_id', $id))
            ->when($request->tipe_izin, fn($q, $type) => $q->where('tipe_izin', $type))
            ->when($request->from_date, fn($q, $date) => $q->where('tanggal_mulai', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->where('tanggal_selesai', '<=', $date));

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $permissions = $query->paginate($perPage);

        return $this->success($permissions);
    }

    /**
     * Store a new leave permission
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'uuid', 'exists:students,id', 'required_without:teacher_id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id', 'required_without:student_id'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'tipe_izin' => ['required', 'in:sakit,izin'],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // Check for overlapping leave
        if ($this->approvalService->hasOverlappingLeave(
            $data['student_id'] ?? null,
            $data['teacher_id'] ?? null,
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
            'tenant_id' => $request->user()->tenant_id,
            'student_id' => $data['student_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'tipe_izin' => $data['tipe_izin'],
            'alasan' => $data['alasan'] ?? null,
            'bukti' => $buktiPath,
        ]);

        $permission->load(['student.user', 'teacher.user']);

        return $this->created($permission, 'Izin berhasil diajukan');
    }

    /**
     * Show a specific leave permission
     */
    public function show(LeavePermission $permission): JsonResponse
    {
        $permission->load(['student.user', 'teacher.user', 'approver']);

        return $this->success($permission);
    }

    /**
     * Update a leave permission (only pending)
     */
    public function update(Request $request, LeavePermission $permission): JsonResponse
    {
        if (!$permission->status->isPending()) {
            return $this->error('Izin yang sudah diproses tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'tanggal_mulai' => ['sometimes', 'date'],
            'tanggal_selesai' => ['sometimes', 'date', 'after_or_equal:tanggal_mulai'],
            'tipe_izin' => ['sometimes', 'in:sakit,izin'],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // Handle file upload
        if ($request->hasFile('bukti')) {
            // Delete old file
            if ($permission->bukti) {
                Storage::disk('public')->delete($permission->bukti);
            }
            $data['bukti'] = $request->file('bukti')->store('leave-permissions', 'public');
        }

        $permission->update($data);
        $permission->load(['student.user', 'teacher.user']);

        return $this->success($permission, 'Izin berhasil diperbarui');
    }

    /**
     * Delete a leave permission (only pending)
     */
    public function destroy(LeavePermission $permission): JsonResponse
    {
        if (!$permission->status->isPending()) {
            return $this->error('Izin yang sudah diproses tidak dapat dihapus', 422);
        }

        // Delete associated file
        if ($permission->bukti) {
            Storage::disk('public')->delete($permission->bukti);
        }

        $permission->delete();

        return $this->deleted('Izin berhasil dihapus');
    }

    /**
     * Approve a leave permission
     */
    public function approve(LeavePermission $permission): JsonResponse
    {
        $result = $this->approvalService->approve($permission, auth()->id());

        if ($result['success']) {
            return $this->success($result['data'], $result['message']);
        }

        return $this->error($result['message'], 422);
    }

    /**
     * Reject a leave permission
     */
    public function reject(Request $request, LeavePermission $permission): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->approvalService->reject(
            $permission,
            auth()->id(),
            $data['reason']
        );

        if ($result['success']) {
            return $this->success($result['data'], $result['message']);
        }

        return $this->error($result['message'], 422);
    }

    /**
     * Get pending count for dashboard
     */
    public function pendingCount(): JsonResponse
    {
        $count = LeavePermission::pending()->count();

        return $this->success(['count' => $count]);
    }
}
