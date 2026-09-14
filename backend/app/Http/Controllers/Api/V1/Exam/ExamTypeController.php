<?php

namespace App\Http\Controllers\Api\V1\Exam;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ExamTypeResource;
use App\Infrastructure\Persistence\Eloquent\Exam\ExamType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamTypeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('exams.view'), 403);

        $query = ExamType::query()
            ->search($request->search);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $request->integer('per_page', 15);

        if ($request->boolean('all')) {
            $examTypes = $query->orderBy('name')->get();
            return $this->success(ExamTypeResource::collection($examTypes));
        }

        $examTypes = $query->orderBy('name')->paginate($perPage);

        return $this->collection($examTypes, ExamTypeResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('exams.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('exam_types')->where('tenant_id', tenant()->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'default_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $validated['tenant_id'] = tenant()->id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $examType = ExamType::create($validated);

        return $this->success(
            new ExamTypeResource($examType),
            'Jenis ujian berhasil ditambahkan',
            201
        );
    }

    public function show(Request $request, ExamType $type): JsonResponse
    {
        abort_unless($request->user()->can('exams.view'), 403);

        return $this->success(new ExamTypeResource($type));
    }

    public function update(Request $request, ExamType $type): JsonResponse
    {
        abort_unless($request->user()->can('exams.manage'), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('exam_types')->where('tenant_id', tenant()->id)->ignore($type->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'default_weight' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $type->update($validated);

        return $this->success(
            new ExamTypeResource($type->fresh()),
            'Jenis ujian berhasil diperbarui'
        );
    }

    public function destroy(Request $request, ExamType $type): JsonResponse
    {
        abort_unless($request->user()->can('exams.manage'), 403);

        // Check if type has exams
        if ($type->exams()->exists()) {
            return $this->error(
                'Jenis ujian tidak dapat dihapus karena masih digunakan',
                422
            );
        }

        $type->delete();

        return $this->success(null, 'Jenis ujian berhasil dihapus');
    }
}
