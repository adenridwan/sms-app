<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\FeeTypeResource;
use App\Models\Finance\FeeType;
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
            ->when($request->category, fn($q, $category) => $q->where('category', $category))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('is_recurring'), fn($q) => $q->where('is_recurring', $request->boolean('is_recurring')));

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
            'amount' => ['required', 'numeric', 'min:0'],
            'category' => ['required', 'in:tuition,registration,development,activity,other'],
            'is_recurring' => ['boolean'],
            'recurring_period' => ['required_if:is_recurring,true', 'nullable', 'in:monthly,semester,yearly'],
            'is_active' => ['boolean'],
        ]);

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
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'category' => ['sometimes', 'in:tuition,registration,development,activity,other'],
            'is_recurring' => ['boolean'],
            'recurring_period' => ['nullable', 'in:monthly,semester,yearly'],
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
        if ($feeType->payments()->exists()) {
            return $this->error('Jenis biaya tidak dapat dihapus karena sudah memiliki transaksi', 422);
        }

        $feeType->delete();

        return $this->success(null, 'Jenis biaya berhasil dihapus');
    }
}
