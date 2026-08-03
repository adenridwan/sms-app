<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\CurriculumResource;
use App\Infrastructure\Persistence\Eloquent\Academic\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Curriculum::withCount('subjects')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = in_array($request->get('sort'), ['name', 'code', 'created_at'], true)
            ? $request->get('sort')
            : 'name';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $curricula = $query->paginate($perPage);

        return $this->success(CurriculumResource::collection($curricula)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('curricula.manage'), 403);

        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['code']) && Curriculum::where('code', $data['code'])->exists()) {
            return $this->validationError(['code' => ['Kode kurikulum sudah digunakan.']]);
        }

        $curriculum = Curriculum::create($data);

        return $this->success(new CurriculumResource($curriculum), 'Kurikulum berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Curriculum $curriculum): JsonResponse
    {
        $curriculum->loadCount('subjects');

        return $this->success(new CurriculumResource($curriculum));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Curriculum $curriculum): JsonResponse
    {
        abort_unless($request->user()->can('curricula.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['code']) && Curriculum::where('code', $data['code'])->where('id', '!=', $curriculum->id)->exists()) {
            return $this->validationError(['code' => ['Kode kurikulum sudah digunakan.']]);
        }

        $curriculum->update($data);

        return $this->success(new CurriculumResource($curriculum), 'Kurikulum berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Curriculum $curriculum): JsonResponse
    {
        abort_unless(request()->user()->can('curricula.manage'), 403);

        if ($curriculum->subjects()->exists()) {
            return $this->error('Kurikulum tidak dapat dihapus karena masih digunakan oleh mata pelajaran', 422);
        }

        $curriculum->delete();

        return $this->success(null, 'Kurikulum berhasil dihapus');
    }
}
