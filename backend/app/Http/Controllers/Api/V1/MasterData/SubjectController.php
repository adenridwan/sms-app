<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SubjectResource;
use App\Models\MasterData\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subject::withCount('teachers')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when($request->category, fn($q, $category) => $q->where('category', $category))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $subjects = $query->paginate($perPage);

        return $this->success(SubjectResource::collection($subjects)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'in:mandatory,local,elective,extracurricular'],
            'credit_hours' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        $subject = Subject::create($data);

        return $this->success(
            new SubjectResource($subject),
            'Mata pelajaran berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject): JsonResponse
    {
        $subject->load('teachers.user');
        $subject->loadCount('teachers');

        return $this->success(new SubjectResource($subject));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:subjects,code,' . $subject->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'in:mandatory,local,elective,extracurricular'],
            'credit_hours' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        $subject->update($data);

        return $this->success(new SubjectResource($subject), 'Mata pelajaran berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject): JsonResponse
    {
        if ($subject->teachers()->exists()) {
            return $this->error('Mata pelajaran tidak dapat dihapus karena masih memiliki guru pengajar', 422);
        }

        $subject->delete();

        return $this->success(null, 'Mata pelajaran berhasil dihapus');
    }
}
