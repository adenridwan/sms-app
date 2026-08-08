<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Finance\FeeStructureResource;
use App\Infrastructure\Persistence\Eloquent\Finance\FeeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeStructureController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FeeStructure::query()
            ->with(['academicYear', 'feeType', 'gradeLevel', 'major'])
            ->when($request->academic_year_id, fn($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->fee_type_id, fn($q, $id) => $q->where('fee_type_id', $id))
            ->when($request->grade_level_id, fn($q, $id) => $q->where('grade_level_id', $id))
            ->when($request->major_id, fn($q, $id) => $q->where('major_id', $id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $structures = $query->paginate($perPage);

        return $this->success(FeeStructureResource::collection($structures)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'fee_type_id' => ['required', 'uuid', 'exists:fee_types,id'],
            'grade_level_id' => ['required', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['discount_amount'] = $data['discount_amount'] ?? 0;

        // Check for duplicate structure
        $exists = FeeStructure::where('academic_year_id', $data['academic_year_id'])
            ->where('fee_type_id', $data['fee_type_id'])
            ->where('grade_level_id', $data['grade_level_id'])
            ->where('major_id', $data['major_id'] ?? null)
            ->exists();

        if ($exists) {
            return $this->error('Struktur biaya dengan kombinasi tahun ajaran, jenis biaya, tingkat kelas, dan jurusan yang sama sudah ada', 422);
        }

        $structure = FeeStructure::create($data);

        return $this->success(
            new FeeStructureResource($structure->load(['academicYear', 'feeType', 'gradeLevel', 'major'])),
            'Struktur biaya berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(FeeStructure $feeStructure): JsonResponse
    {
        return $this->success(new FeeStructureResource(
            $feeStructure->load(['academicYear', 'feeType', 'gradeLevel', 'major'])
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FeeStructure $feeStructure): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['sometimes', 'uuid', 'exists:academic_years,id'],
            'fee_type_id' => ['sometimes', 'uuid', 'exists:fee_types,id'],
            'grade_level_id' => ['sometimes', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'is_active' => ['boolean'],
        ]);

        // Check for duplicate if changing key fields
        $academicYearId = $data['academic_year_id'] ?? $feeStructure->academic_year_id;
        $feeTypeId = $data['fee_type_id'] ?? $feeStructure->fee_type_id;
        $gradeLevelId = $data['grade_level_id'] ?? $feeStructure->grade_level_id;
        $majorId = array_key_exists('major_id', $data) ? $data['major_id'] : $feeStructure->major_id;

        $exists = FeeStructure::where('academic_year_id', $academicYearId)
            ->where('fee_type_id', $feeTypeId)
            ->where('grade_level_id', $gradeLevelId)
            ->where('major_id', $majorId)
            ->where('id', '!=', $feeStructure->id)
            ->exists();

        if ($exists) {
            return $this->error('Struktur biaya dengan kombinasi tahun ajaran, jenis biaya, tingkat kelas, dan jurusan yang sama sudah ada', 422);
        }

        $feeStructure->update($data);

        return $this->success(
            new FeeStructureResource($feeStructure->load(['academicYear', 'feeType', 'gradeLevel', 'major'])),
            'Struktur biaya berhasil diperbarui'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeeStructure $feeStructure): JsonResponse
    {
        if ($feeStructure->studentFees()->exists()) {
            return $this->error('Struktur biaya tidak dapat dihapus karena sudah digunakan oleh tagihan siswa', 422);
        }

        $feeStructure->delete();

        return $this->success(null, 'Struktur biaya berhasil dihapus');
    }

    /**
     * Bulk create fee structures for a grade level.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'structures' => ['required', 'array', 'min:1'],
            'structures.*.fee_type_id' => ['required', 'uuid', 'exists:fee_types,id'],
            'structures.*.amount' => ['required', 'numeric', 'min:0'],
            'structures.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'structures.*.due_date' => ['nullable', 'date'],
            'structures.*.due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'structures.*.is_active' => ['boolean'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $created = [];
        $skipped = [];

        foreach ($data['structures'] as $structure) {
            $exists = FeeStructure::where('academic_year_id', $data['academic_year_id'])
                ->where('fee_type_id', $structure['fee_type_id'])
                ->where('grade_level_id', $data['grade_level_id'])
                ->where('major_id', $data['major_id'] ?? null)
                ->exists();

            if ($exists) {
                $skipped[] = $structure['fee_type_id'];
                continue;
            }

            $created[] = FeeStructure::create([
                'tenant_id' => $tenantId,
                'academic_year_id' => $data['academic_year_id'],
                'fee_type_id' => $structure['fee_type_id'],
                'grade_level_id' => $data['grade_level_id'],
                'major_id' => $data['major_id'] ?? null,
                'amount' => $structure['amount'],
                'discount_amount' => $structure['discount_amount'] ?? 0,
                'due_date' => $structure['due_date'] ?? null,
                'due_day' => $structure['due_day'] ?? null,
                'is_active' => $structure['is_active'] ?? true,
            ]);
        }

        $message = count($created) . ' struktur biaya berhasil ditambahkan';
        if (count($skipped) > 0) {
            $message .= ', ' . count($skipped) . ' dilewati karena sudah ada';
        }

        return $this->success(
            FeeStructureResource::collection(
                collect($created)->load(['academicYear', 'feeType', 'gradeLevel', 'major'])
            ),
            $message,
            201
        );
    }
}
