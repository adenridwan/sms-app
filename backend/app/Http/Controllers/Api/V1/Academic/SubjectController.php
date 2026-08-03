<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\SubjectResource;
use App\Infrastructure\Persistence\Eloquent\Academic\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subject::with('curriculum')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->when($request->filled('curriculum_id'), fn ($q) => $q->where('curriculum_id', $request->get('curriculum_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->get('category')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = in_array($request->get('sort'), ['name', 'code', 'created_at'], true)
            ? $request->get('sort')
            : 'name';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $subjects = $query->paginate($perPage);

        return $this->success(SubjectResource::collection($subjects)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('subjects.manage'), 403);

        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'curriculum_id' => ['nullable', 'uuid', Rule::exists('curricula', 'id')],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20'],
            'category' => ['nullable', 'string', Rule::in(Subject::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if (Subject::where('code', $data['code'])->exists()) {
            return $this->validationError(['code' => ['Kode mata pelajaran sudah digunakan.']]);
        }

        $subject = Subject::create($data);
        $subject->load('curriculum');

        return $this->success(new SubjectResource($subject), 'Mata pelajaran berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject): JsonResponse
    {
        $subject->load('curriculum');

        return $this->success(new SubjectResource($subject));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject): JsonResponse
    {
        abort_unless($request->user()->can('subjects.manage'), 403);

        $data = $request->validate([
            'curriculum_id' => ['nullable', 'uuid', Rule::exists('curricula', 'id')],
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:20'],
            'category' => ['nullable', 'string', Rule::in(Subject::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['code']) && Subject::where('code', $data['code'])->where('id', '!=', $subject->id)->exists()) {
            return $this->validationError(['code' => ['Kode mata pelajaran sudah digunakan.']]);
        }

        $subject->update($data);
        $subject->load('curriculum');

        return $this->success(new SubjectResource($subject), 'Mata pelajaran berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject): JsonResponse
    {
        abort_unless(request()->user()->can('subjects.manage'), 403);

        if ($subject->teacherSubjects()->exists()) {
            return $this->error('Mata pelajaran tidak dapat dihapus karena masih diampu oleh guru', 422);
        }

        $subject->delete();

        return $this->success(null, 'Mata pelajaran berhasil dihapus');
    }
}
