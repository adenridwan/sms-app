<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Enums\LeaveStatus;
use App\Domain\Attendance\Enums\LeaveType;
use App\Domain\Attendance\Services\LeaveApprovalService;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeavePermissionController extends ApiController
{
    private const WITH_RELATIONS = ['student.user.profile', 'teacher.user.profile', 'approver.profile'];

    public function __construct(
        private LeaveApprovalService $approvalService
    ) {}

    /**
     * List leave permissions
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeavePermission::with(self::WITH_RELATIONS)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->teacher_id, fn($q, $id) => $q->where('teacher_id', $id))
            ->when($request->tipe_izin, fn($q, $type) => $q->where('tipe_izin', $type))
            ->when($request->from_date, fn($q, $date) => $q->where('tanggal_mulai', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->where('tanggal_selesai', '<=', $date));

        // R4: guru/siswa (dan role tanpa akses penuh lainnya) hanya melihat
        // pengajuan izin miliknya sendiri, apa pun parameter student_id/
        // teacher_id yang dikirim — pola yang sama dengan
        // TeacherAttendanceController::isFullAccess().
        if (!$this->isFullAccess($request->user())) {
            [$selfStudentId, $selfTeacherId] = $this->resolveSelfIds($request->user());

            $query->where(function ($q) use ($selfStudentId, $selfTeacherId) {
                $q->whereRaw('1 = 0');
                if ($selfStudentId) {
                    $q->orWhere('student_id', $selfStudentId);
                }
                if ($selfTeacherId) {
                    $q->orWhere('teacher_id', $selfTeacherId);
                }
            });
        }

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
        $user = $request->user();
        $isFullAccess = $this->isFullAccess($user);

        $data = $request->validate([
            // Untuk role tanpa akses penuh, student_id/teacher_id diabaikan
            // dan identitasnya diambil dari akun login (lihat di bawah) —
            // required_without HANYA berlaku untuk role akses-penuh yang
            // memang wajib memilih siswa/guru secara eksplisit.
            'student_id' => ['nullable', 'uuid', 'exists:students,id', $isFullAccess ? 'required_without:teacher_id' : 'sometimes'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id', $isFullAccess ? 'required_without:student_id' : 'sometimes'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'tipe_izin' => ['required', 'in:sakit,izin'],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($isFullAccess) {
            $studentId = $data['student_id'] ?? null;
            $teacherId = $data['teacher_id'] ?? null;
        } else {
            // Guru/siswa (dan role tanpa akses penuh lainnya) hanya boleh
            // mengajukan izin untuk dirinya sendiri — student_id/teacher_id
            // dari request DIABAIKAN dan diganti identitas user login,
            // supaya tidak bisa dipakai mengajukan izin atas nama orang lain.
            [$studentId, $teacherId] = $this->resolveSelfIds($user);

            if (!$studentId && !$teacherId) {
                return $this->forbidden('Akun Anda tidak terhubung ke data siswa/guru, tidak bisa mengajukan izin.');
            }
        }

        // Check for overlapping leave
        if ($this->approvalService->hasOverlappingLeave($studentId, $teacherId, $data['tanggal_mulai'], $data['tanggal_selesai'])) {
            return $this->error('Sudah ada izin yang tumpang tindih dengan rentang tanggal ini', 422);
        }

        // Handle file upload
        $buktiPath = null;
        if ($request->hasFile('bukti')) {
            $buktiPath = $request->file('bukti')->store('leave-permissions', 'public');
        }

        $permission = $this->approvalService->createLeavePermission([
            'tenant_id' => $user->tenant_id,
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'tipe_izin' => $data['tipe_izin'],
            'alasan' => $data['alasan'] ?? null,
            'bukti' => $buktiPath,
        ]);

        $permission->load(['student.user.profile', 'teacher.user.profile']);

        return $this->created($permission, 'Izin berhasil diajukan');
    }

    /**
     * Show a specific leave permission
     */
    public function show(Request $request, LeavePermission $permission): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $permission);

        $permission->load(self::WITH_RELATIONS);

        return $this->success($permission);
    }

    /**
     * Update a leave permission (only pending)
     */
    public function update(Request $request, LeavePermission $permission): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $permission);

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
        $permission->load(['student.user.profile', 'teacher.user.profile']);

        return $this->success($permission, 'Izin berhasil diperbarui');
    }

    /**
     * Delete a leave permission (only pending)
     */
    public function destroy(Request $request, LeavePermission $permission): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $permission);

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
     * Approve a leave permission — hanya role akses-penuh (bukan pemohon
     * sendiri) yang boleh menyetujui, sama seperti reject().
     */
    public function approve(Request $request, LeavePermission $permission): JsonResponse
    {
        if (!$this->isFullAccess($request->user())) {
            return $this->forbidden('Anda tidak memiliki akses untuk menyetujui perizinan.');
        }

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
        if (!$this->isFullAccess($request->user())) {
            return $this->forbidden('Anda tidak memiliki akses untuk menolak perizinan.');
        }

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

    /**
     * Role akses-penuh (dipakai bersama di seluruh modul absensi — lihat
     * TeacherAttendanceController::isFullAccess) melihat & mengelola
     * perizinan siapa saja; selain itu (guru/siswa/wali_kelas biasa) hanya
     * miliknya sendiri.
     */
    private function isFullAccess(User $user): bool
    {
        return $user->getRoleNames()->intersect(Student::ALL_ACCESS_ROLES)->isNotEmpty();
    }

    /**
     * Resolusi student_id/teacher_id milik user login sendiri.
     *
     * @return array{0: ?string, 1: ?string} [studentId, teacherId]
     */
    private function resolveSelfIds(User $user): array
    {
        $studentId = $user->student?->id;
        $teacherId = $studentId ? null : $user->teacher?->id;

        return [$studentId, $teacherId];
    }

    /**
     * Guru/siswa (dan role tanpa akses penuh lainnya) hanya boleh
     * melihat/mengubah/menghapus baris perizinan miliknya sendiri.
     */
    private function authorizeOwnership(User $user, LeavePermission $permission): void
    {
        if ($this->isFullAccess($user)) {
            return;
        }

        [$selfStudentId, $selfTeacherId] = $this->resolveSelfIds($user);

        $isOwner = ($permission->student_id && $permission->student_id === $selfStudentId)
            || ($permission->teacher_id && $permission->teacher_id === $selfTeacherId);

        abort_unless($isOwner, 403, 'Anda tidak memiliki akses ke perizinan ini.');
    }
}
