<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AcademicYearResource;
use App\Models\Academic\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AcademicYear::withCount('classRooms')
            ->when($request->search, fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'start_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $academicYears = $query->paginate($perPage);

        return $this->success(AcademicYearResource::collection($academicYears)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        // If setting as active, deactivate others
        if ($data['is_active'] ?? false) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
        }

        $academicYear = AcademicYear::create($data);

        return $this->success(
            new AcademicYearResource($academicYear),
            'Tahun ajaran berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->load('semesters');
        $academicYear->loadCount('classRooms');

        return $this->success(new AcademicYearResource($academicYear));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50', 'unique:academic_years,name,' . $academicYear->id],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        // If setting as active, deactivate others
        if (($data['is_active'] ?? false) && !$academicYear->is_active) {
            AcademicYear::where('is_active', true)
                ->where('id', '!=', $academicYear->id)
                ->update(['is_active' => false]);
        }

        $academicYear->update($data);

        return $this->success(new AcademicYearResource($academicYear), 'Tahun ajaran berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->is_active) {
            return $this->error('Tahun ajaran aktif tidak dapat dihapus', 422);
        }

        if ($academicYear->classRooms()->exists()) {
            return $this->error('Tahun ajaran tidak dapat dihapus karena masih memiliki kelas', 422);
        }

        $academicYear->delete();

        return $this->success(null, 'Tahun ajaran berhasil dihapus');
    }

    /**
     * Set academic year as active.
     */
    public function setActive(AcademicYear $academicYear): JsonResponse
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return $this->success(new AcademicYearResource($academicYear), 'Tahun ajaran berhasil diaktifkan');
    }
}
