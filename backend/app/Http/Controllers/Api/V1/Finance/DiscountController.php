<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Finance\DiscountResource;
use App\Infrastructure\Persistence\Eloquent\Finance\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Discount::query()
            ->with('feeType')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->fee_type_id, fn($q, $feeTypeId) => $q->where('fee_type_id', $feeTypeId))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('valid_only'), fn($q) => $q->valid());

        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $discounts = $query->paginate($perPage);

        return $this->success(DiscountResource::collection($discounts)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:discounts,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'fee_type_id' => ['nullable', 'uuid', 'exists:fee_types,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;

        // Validate percentage is <= 100
        if ($data['type'] === 'percentage' && $data['value'] > 100) {
            return $this->error('Nilai persentase tidak boleh lebih dari 100%', 422);
        }

        $discount = Discount::create($data);

        return $this->success(
            new DiscountResource($discount->load('feeType')),
            'Potongan/beasiswa berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Discount $discount): JsonResponse
    {
        return $this->success(new DiscountResource($discount->load('feeType')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Discount $discount): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:discounts,code,' . $discount->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['sometimes', 'in:percentage,fixed'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'fee_type_id' => ['nullable', 'uuid', 'exists:fee_types,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
        ]);

        // Validate percentage is <= 100
        $type = $data['type'] ?? $discount->type;
        $value = $data['value'] ?? $discount->value;
        if ($type === 'percentage' && $value > 100) {
            return $this->error('Nilai persentase tidak boleh lebih dari 100%', 422);
        }

        $discount->update($data);

        return $this->success(new DiscountResource($discount->load('feeType')), 'Potongan/beasiswa berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount): JsonResponse
    {
        if ($discount->studentDiscounts()->exists()) {
            return $this->error('Potongan/beasiswa tidak dapat dihapus karena sudah digunakan oleh siswa', 422);
        }

        $discount->delete();

        return $this->success(null, 'Potongan/beasiswa berhasil dihapus');
    }

    /**
     * Get available discount types.
     */
    public function types(): JsonResponse
    {
        return $this->success(Discount::getTypes());
    }
}
