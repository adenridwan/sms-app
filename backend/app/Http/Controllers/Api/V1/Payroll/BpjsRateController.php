<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\BpjsRateResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\BpjsRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BpjsRateController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BpjsRate::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%");
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('current_only'), fn($q) => $q->current());

        $sortField = $request->get('sort', 'type');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $rates = $query->paginate($perPage);

        return $this->success(BpjsRateResource::collection($rates)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:kesehatan,jht,jkk,jkm,jp'],
            'name' => ['required', 'string', 'max:100'],
            'employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;

        $rate = BpjsRate::create($data);

        return $this->success(
            new BpjsRateResource($rate),
            'Tarif BPJS berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(BpjsRate $bpjsRate): JsonResponse
    {
        return $this->success(new BpjsRateResource($bpjsRate));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BpjsRate $bpjsRate): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', 'in:kesehatan,jht,jkk,jkm,jp'],
            'name' => ['sometimes', 'string', 'max:100'],
            'employee_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'employer_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $bpjsRate->update($data);

        return $this->success(new BpjsRateResource($bpjsRate), 'Tarif BPJS berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BpjsRate $bpjsRate): JsonResponse
    {
        $bpjsRate->delete();

        return $this->success(null, 'Tarif BPJS berhasil dihapus');
    }

    /**
     * Get available BPJS types.
     */
    public function types(): JsonResponse
    {
        return $this->success(BpjsRate::getTypes());
    }

    /**
     * Get current effective rates for all types.
     */
    public function currentRates(): JsonResponse
    {
        $rates = BpjsRate::current()
            ->get()
            ->keyBy('type');

        return $this->success(BpjsRateResource::collection($rates));
    }
}
