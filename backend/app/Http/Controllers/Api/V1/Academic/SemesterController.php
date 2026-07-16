<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SemesterResource;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemesterController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Semester::with('academicYear')
            ->when($request->academic_year_id, fn($q, $yearId) => $q->where('academic_year_id', $yearId))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'start_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $semesters = $query->paginate($perPage);

        return $this->success(SemesterResource::collection($semesters)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:50'],
            'semester_number' => ['required', 'integer', 'in:1,2'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        // Validate semester number is unique within academic year
        $exists = Semester::where('academic_year_id', $data['academic_year_id'])
            ->where('semester_number', $data['semester_number'])
            ->exists();

        if ($exists) {
            return $this->error('Semester ' . $data['semester_number'] . ' sudah ada untuk tahun ajaran ini', 422);
        }

        // If setting as active, deactivate others
        if ($data['is_active'] ?? false) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        $semester = Semester::create($data);
        $semester->load('academicYear');

        return $this->success(
            new SemesterResource($semester),
            'Semester berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Semester $semester): JsonResponse
    {
        $semester->load('academicYear');

        return $this->success(new SemesterResource($semester));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Semester $semester): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'semester_number' => ['sometimes', 'integer', 'in:1,2'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        // Validate semester number is unique within academic year (excluding current)
        if (isset($data['semester_number']) && $data['semester_number'] !== $semester->semester_number) {
            $exists = Semester::where('academic_year_id', $semester->academic_year_id)
                ->where('semester_number', $data['semester_number'])
                ->where('id', '!=', $semester->id)
                ->exists();

            if ($exists) {
                return $this->error('Semester ' . $data['semester_number'] . ' sudah ada untuk tahun ajaran ini', 422);
            }
        }

        // If setting as active, deactivate others
        if (($data['is_active'] ?? false) && !$semester->is_active) {
            Semester::where('is_active', true)
                ->where('id', '!=', $semester->id)
                ->update(['is_active' => false]);
        }

        $semester->update($data);
        $semester->load('academicYear');

        return $this->success(new SemesterResource($semester), 'Semester berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Semester $semester): JsonResponse
    {
        if ($semester->is_active) {
            return $this->error('Semester aktif tidak dapat dihapus', 422);
        }

        $semester->delete();

        return $this->success(null, 'Semester berhasil dihapus');
    }

    /**
     * Set semester as active.
     */
    public function setActive(Semester $semester): JsonResponse
    {
        DB::transaction(function () use ($semester) {
            Semester::where('is_active', true)->update(['is_active' => false]);
            $semester->update(['is_active' => true]);

            // Also activate the academic year
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $semester->academicYear->update(['is_active' => true]);
        });

        $semester->load('academicYear');

        return $this->success(new SemesterResource($semester), 'Semester berhasil diaktifkan');
    }
}
