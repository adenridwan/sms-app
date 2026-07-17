<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\GradeLevelResource;
use App\Infrastructure\Persistence\Eloquent\Academic\GradeLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeLevelController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GradeLevel::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->orderBy('order');

        $perPage = min((int) $request->get('per_page', 50), 100);
        $gradeLevels = $query->paginate($perPage);

        return $this->success(GradeLevelResource::collection($gradeLevels)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (GradeLevel::where('code', $data['code'])->exists()) {
            return $this->validationError(['code' => ['Kode tingkat sudah digunakan.']]);
        }

        $gradeLevel = GradeLevel::create($data);

        return $this->success(new GradeLevelResource($gradeLevel), 'Tingkat kelas berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(GradeLevel $gradeLevel): JsonResponse
    {
        return $this->success(new GradeLevelResource($gradeLevel));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GradeLevel $gradeLevel): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:20'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['code']) && GradeLevel::where('code', $data['code'])->where('id', '!=', $gradeLevel->id)->exists()) {
            return $this->validationError(['code' => ['Kode tingkat sudah digunakan.']]);
        }

        $gradeLevel->update($data);

        return $this->success(new GradeLevelResource($gradeLevel), 'Tingkat kelas berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GradeLevel $gradeLevel): JsonResponse
    {
        if ($gradeLevel->classrooms()->exists()) {
            return $this->error('Tingkat kelas tidak dapat dihapus karena masih digunakan oleh kelas', 422);
        }

        $gradeLevel->delete();

        return $this->success(null, 'Tingkat kelas berhasil dihapus');
    }
}
