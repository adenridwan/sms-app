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
        $query = GradeLevel::withCount('classrooms')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
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
        abort_unless($request->user()->can('grade-levels.manage'), 403);

        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        // Kode diisi otomatis (increment) agar pengguna tidak perlu
        // mengetik kode manual, yang sering memicu error duplikat.
        $data['code'] = $this->generateNextCode();

        // create() tidak membaca ulang baris dari DB, jadi default kolom
        // is_active (true) tidak otomatis terisi di model in-memory bila
        // klien tidak mengirim field ini — set eksplisit di sini.
        $data['is_active'] = $data['is_active'] ?? true;
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
        abort_unless($request->user()->can('grade-levels.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        // Kode bersifat generated dan tidak dapat diubah melalui form.
        $gradeLevel->update($data);

        return $this->success(new GradeLevelResource($gradeLevel), 'Tingkat kelas berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GradeLevel $gradeLevel): JsonResponse
    {
        abort_unless(request()->user()->can('grade-levels.manage'), 403);

        if ($gradeLevel->classrooms()->exists()) {
            return $this->error('Tingkat kelas tidak dapat dihapus karena masih digunakan oleh kelas', 422);
        }

        $gradeLevel->delete();

        return $this->success(null, 'Tingkat kelas berhasil dihapus');
    }

    /**
     * Generate a unique, incrementing code (TK01, TK02, ...) scoped to the
     * current tenant, retrying past any gaps left by deleted records.
     */
    private function generateNextCode(): string
    {
        $sequence = GradeLevel::withTrashed()->count();

        do {
            $sequence++;
            $code = 'TK'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        } while (GradeLevel::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
