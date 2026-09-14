<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ParentResource;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentGuardian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuardianController extends ApiController
{
    /**
     * List all guardians (optionally filtered by student).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $query = StudentGuardian::query()
            ->with(['student.user.profile', 'user'])
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->relationship, fn($q, $rel) => $q->where('relationship', $rel))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $guardians = $query->paginate($perPage);

        return $this->collection(ParentResource::collection($guardians));
    }

    /**
     * Get a single guardian.
     */
    public function show(Request $request, StudentGuardian $guardian): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $guardian->load(['student.user.profile', 'user']);

        return $this->success(new ParentResource($guardian));
    }

    /**
     * Create a new guardian for a student.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'relationship' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'income_range' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'is_primary_contact' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            // If this is set as primary contact, unset others
            if ($data['is_primary_contact'] ?? false) {
                StudentGuardian::where('student_id', $data['student_id'])
                    ->update(['is_primary_contact' => false]);
            }

            $guardian = StudentGuardian::create([
                'tenant_id' => $this->currentTenantId($request),
                ...$data,
            ]);

            DB::commit();

            $guardian->load(['student.user.profile', 'user']);

            return $this->success(
                new ParentResource($guardian),
                'Wali/orang tua berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menambahkan wali: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a guardian.
     */
    public function update(Request $request, StudentGuardian $guardian): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        $data = $request->validate([
            'relationship' => ['sometimes', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'name' => ['sometimes', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'income_range' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'is_primary_contact' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            // If this is set as primary contact, unset others
            if ($data['is_primary_contact'] ?? false) {
                StudentGuardian::where('student_id', $guardian->student_id)
                    ->where('id', '!=', $guardian->id)
                    ->update(['is_primary_contact' => false]);
            }

            $guardian->update($data);

            DB::commit();

            $guardian->load(['student.user.profile', 'user']);

            return $this->success(
                new ParentResource($guardian),
                'Data wali/orang tua berhasil diperbarui'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui data wali: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a guardian.
     */
    public function destroy(Request $request, StudentGuardian $guardian): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        try {
            $guardian->delete();

            return $this->success(null, 'Wali/orang tua berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus wali: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get guardians for a specific student.
     */
    public function forStudent(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $guardians = $student->parents()
            ->with('user')
            ->orderByDesc('is_primary_contact')
            ->orderBy('relationship')
            ->get();

        return $this->success(ParentResource::collection($guardians));
    }
}
