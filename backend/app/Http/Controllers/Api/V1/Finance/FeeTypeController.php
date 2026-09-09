<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Finance\FeeTypeResource;
use App\Infrastructure\Persistence\Eloquent\Finance\FeeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeTypeController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FeeType::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            })
            ->when($request->frequency, fn($q, $frequency) => $q->where('frequency', $frequency))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('is_mandatory'), fn($q) => $q->where('is_mandatory', $request->boolean('is_mandatory')));

        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $feeTypes = $query->paginate($perPage);

        return $this->success(FeeTypeResource::collection($feeTypes)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:fee_types,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['required', 'in:once,weekly,monthly,semester,yearly'],
            'is_mandatory' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;

        $feeType = FeeType::create($data);

        return $this->success(
            new FeeTypeResource($feeType),
            'Jenis biaya berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(FeeType $feeType): JsonResponse
    {
        return $this->success(new FeeTypeResource($feeType));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FeeType $feeType): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:fee_types,code,' . $feeType->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['sometimes', 'in:once,weekly,monthly,semester,yearly'],
            'is_mandatory' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $feeType->update($data);

        return $this->success(new FeeTypeResource($feeType), 'Jenis biaya berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeeType $feeType): JsonResponse
    {
        if ($feeType->feeStructures()->exists()) {
            return $this->error('Jenis biaya tidak dapat dihapus karena sudah digunakan di struktur biaya', 422);
        }

        $feeType->delete();

        return $this->success(null, 'Jenis biaya berhasil dihapus');
    }
}
