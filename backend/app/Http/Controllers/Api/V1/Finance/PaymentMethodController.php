<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Finance\PaymentMethodResource;
use App\Infrastructure\Persistence\Eloquent\Finance\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMethod::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $methods = $query->paginate($perPage);

        return $this->success(PaymentMethodResource::collection($methods)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:payment_methods,code'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:cash,bank_transfer,virtual_account,e_wallet,credit_card,other'],
            'provider' => ['nullable', 'string', 'max:100'],
            'configuration' => ['nullable', 'array'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['admin_fee'] = $data['admin_fee'] ?? 0;

        $method = PaymentMethod::create($data);

        return $this->success(
            new PaymentMethodResource($method),
            'Metode pembayaran berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return $this->success(new PaymentMethodResource($paymentMethod));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:payment_methods,code,' . $paymentMethod->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:cash,bank_transfer,virtual_account,e_wallet,credit_card,other'],
            'provider' => ['nullable', 'string', 'max:100'],
            'configuration' => ['nullable', 'array'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $paymentMethod->update($data);

        return $this->success(new PaymentMethodResource($paymentMethod), 'Metode pembayaran berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        if ($paymentMethod->payments()->exists()) {
            return $this->error('Metode pembayaran tidak dapat dihapus karena sudah digunakan', 422);
        }

        $paymentMethod->delete();

        return $this->success(null, 'Metode pembayaran berhasil dihapus');
    }

    /**
     * Get available payment method types.
     */
    public function types(): JsonResponse
    {
        return $this->success(PaymentMethod::getTypes());
    }
}
