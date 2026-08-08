<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\TaxBracketResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\TaxBracket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxBracketController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxBracket::query()
            ->when($request->effective_year, fn($q, $year) => $q->forYear($year))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $query->orderBy('effective_year', 'desc')->orderBy('min_amount', 'asc');

        $perPage = $request->get('per_page', 15);
        $brackets = $query->paginate($perPage);

        return $this->success(TaxBracketResource::collection($brackets)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gt:min_amount'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;

        // Check for overlapping brackets in the same year
        $exists = TaxBracket::where('effective_year', $data['effective_year'])
            ->where('is_active', true)
            ->where(function ($q) use ($data) {
                // Check if min_amount falls within existing bracket
                $q->where(function ($inner) use ($data) {
                    $inner->where('min_amount', '<=', $data['min_amount']);
                    if ($data['max_amount'] ?? null) {
                        $inner->where(function ($max) use ($data) {
                            $max->whereNull('max_amount')
                                ->orWhere('max_amount', '>=', $data['min_amount']);
                        });
                    }
                });
            })
            ->exists();

        if ($exists) {
            return $this->error('Rentang tarif pajak tumpang tindih dengan yang sudah ada untuk tahun yang sama', 422);
        }

        $bracket = TaxBracket::create($data);

        return $this->success(
            new TaxBracketResource($bracket),
            'Tarif pajak progresif berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxBracket $taxBracket): JsonResponse
    {
        return $this->success(new TaxBracketResource($taxBracket));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxBracket $taxBracket): JsonResponse
    {
        $data = $request->validate([
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'effective_year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        $taxBracket->update($data);

        return $this->success(new TaxBracketResource($taxBracket), 'Tarif pajak progresif berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxBracket $taxBracket): JsonResponse
    {
        $taxBracket->delete();

        return $this->success(null, 'Tarif pajak progresif berhasil dihapus');
    }

    /**
     * Get brackets for specific year.
     */
    public function forYear(int $year): JsonResponse
    {
        $brackets = TaxBracket::forYear($year)
            ->active()
            ->ordered()
            ->get();

        return $this->success(TaxBracketResource::collection($brackets));
    }

    /**
     * Calculate progressive tax for given PKP amount.
     */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pkp' => ['required', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $tax = TaxBracket::calculateProgressiveTax($tenantId, $data['pkp'], $data['year']);

        return $this->success([
            'pkp' => $data['pkp'],
            'pkp_formatted' => 'Rp ' . number_format($data['pkp'], 0, ',', '.'),
            'tax' => $tax,
            'tax_formatted' => 'Rp ' . number_format($tax, 0, ',', '.'),
            'effective_rate' => $data['pkp'] > 0 ? round(($tax / $data['pkp']) * 100, 2) : 0,
        ]);
    }
}
