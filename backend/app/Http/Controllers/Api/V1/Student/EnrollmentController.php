<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\StoreEnrollmentRequest;
use App\Http\Requests\Student\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends ApiController
{
    /**
     * List all enrollments (optionally filtered by academic year or classroom).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $query = StudentEnrollment::query()
            ->with(['student.user.profile', 'academicYear', 'classroom'])
            ->when($request->academic_year_id, fn($q, $id) => $q->forAcademicYear($id))
            ->when($request->classroom_id, fn($q, $id) => $q->forClassroom($id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->search, function ($q, $search) {
                $q->whereHas('student', function ($query) use ($search) {
                    $query->where('nis', 'ilike', "%{$search}%")
                        ->orWhereHas('user.profile', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $enrollments = $query->paginate($perPage);

        return $this->collection(EnrollmentResource::collection($enrollments));
    }

    /**
     * Get enrollments for a specific student.
     */
    public function studentEnrollments(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $enrollments = $student->enrollments()
            ->with(['academicYear', 'classroom'])
            ->orderBy('enrollment_date', 'desc')
            ->get();

        return $this->success(EnrollmentResource::collection($enrollments));
    }

    /**
     * Enroll a student to a classroom for an academic year.
     */
    public function store(StoreEnrollmentRequest $request, Student $student): JsonResponse
    {
        $data = $request->validated();

        // Check if student already enrolled for this academic year
        $existing = StudentEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $data['academic_year_id'])
            ->first();

        if ($existing) {
            return $this->error(
                'Siswa sudah terdaftar pada tahun ajaran ini. Gunakan fitur pindah kelas untuk memindahkan.',
                422
            );
        }

        try {
            DB::beginTransaction();

            $enrollment = StudentEnrollment::create([
                'tenant_id' => $this->currentTenantId($request),
                'student_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'classroom_id' => $data['classroom_id'],
                'student_number_in_class' => $data['student_number_in_class'] ?? null,
                'status' => 'active',
                'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
            ]);

            DB::commit();

            $enrollment->load(['academicYear', 'classroom']);

            return $this->success(
                new EnrollmentResource($enrollment),
                'Siswa berhasil didaftarkan ke kelas',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal mendaftarkan siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update enrollment (e.g., change classroom or status).
     */
    public function update(UpdateEnrollmentRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // If changing classroom, check for conflicts
            if (isset($data['classroom_id']) && $data['classroom_id'] !== $enrollment->classroom_id) {
                // This is a class transfer - update the existing enrollment
                $enrollment->update([
                    'classroom_id' => $data['classroom_id'],
                    'student_number_in_class' => $data['student_number_in_class'] ?? $enrollment->student_number_in_class,
                ]);
            }

            // Update status if provided
            if (isset($data['status'])) {
                $enrollment->update(['status' => $data['status']]);
            }

            // Update student number in class if provided
            if (isset($data['student_number_in_class'])) {
                $enrollment->update(['student_number_in_class' => $data['student_number_in_class']]);
            }

            DB::commit();

            $enrollment->load(['student.user.profile', 'academicYear', 'classroom']);

            return $this->success(
                new EnrollmentResource($enrollment),
                'Data pendaftaran berhasil diperbarui'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk enroll multiple students to a classroom.
     */
    public function bulkEnroll(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.enroll'), 403);

        $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'uuid', 'exists:students,id'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
            'enrollment_date' => ['nullable', 'date'],
        ]);

        $tenantId = $this->currentTenantId($request);
        $enrolled = 0;
        $skipped = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->student_ids as $studentId) {
                // Check if already enrolled
                $existing = StudentEnrollment::where('student_id', $studentId)
                    ->where('academic_year_id', $request->academic_year_id)
                    ->exists();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                StudentEnrollment::create([
                    'tenant_id' => $tenantId,
                    'student_id' => $studentId,
                    'academic_year_id' => $request->academic_year_id,
                    'classroom_id' => $request->classroom_id,
                    'status' => 'active',
                    'enrollment_date' => $request->enrollment_date ?? now()->toDateString(),
                ]);

                $enrolled++;
            }

            DB::commit();

            return $this->success([
                'enrolled' => $enrolled,
                'skipped' => $skipped,
            ], "{$enrolled} siswa berhasil didaftarkan, {$skipped} dilewati (sudah terdaftar)");
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal mendaftarkan siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove enrollment (soft delete).
     */
    public function destroy(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        abort_unless($request->user()->can('students.enroll'), 403);

        try {
            $enrollment->delete();

            return $this->success(null, 'Pendaftaran siswa berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus pendaftaran: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get enrollment statistics for dashboard.
     */
    public function statistics(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $academicYearId = $request->academic_year_id;

        // If no academic year specified, use the active one
        if (! $academicYearId) {
            $activeYear = AcademicYear::where('is_active', true)->first();
            $academicYearId = $activeYear?->id;
        }

        if (! $academicYearId) {
            return $this->success([
                'total_enrolled' => 0,
                'by_status' => [],
                'by_classroom' => [],
            ]);
        }

        $query = StudentEnrollment::forAcademicYear($academicYearId);

        $totalEnrolled = (clone $query)->count();

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byClassroom = (clone $query)
            ->with('classroom:id,name')
            ->selectRaw('classroom_id, count(*) as count')
            ->groupBy('classroom_id')
            ->get()
            ->map(fn($item) => [
                'classroom_id' => $item->classroom_id,
                'classroom_name' => $item->classroom?->name ?? 'Unknown',
                'count' => $item->count,
            ]);

        return $this->success([
            'total_enrolled' => $totalEnrolled,
            'by_status' => $byStatus,
            'by_classroom' => $byClassroom,
        ]);
    }
}
