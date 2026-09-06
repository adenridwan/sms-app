<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\TaxSettingResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\TaxSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxSettingController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxSetting::query()
            ->when($request->search, function ($q, $search) {
                $q->where('setting_name', 'ilike', "%{$search}%")
                    ->orWhere('setting_key', 'ilike', "%{$search}%");
            })
            ->when($request->category, fn($q, $category) => $q->byCategory($category))
            ->when($request->effective_year, fn($q, $year) => $q->forYear($year))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'category');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection)->orderBy('setting_key', 'asc');

        $perPage = $request->get('per_page', 15);
        $settings = $query->paginate($perPage);

        return $this->success(TaxSettingResource::collection($settings)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'setting_key' => ['required', 'string', 'max:50'],
            'setting_name' => ['required', 'string', 'max:100'],
            'setting_value' => ['required', 'numeric', 'min:0'],
            'category' => ['required', 'in:ptkp,biaya_jabatan,ter,other'],
            'description' => ['nullable', 'string', 'max:500'],
            'effective_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;

        // Default effective_from ke 1 Januari tahun efektif jika tidak diisi
        if (empty($data['effective_from'])) {
            $data['effective_from'] = $data['effective_year'] . '-01-01';
        }

        // Check for duplicate key in the same year
        $exists = TaxSetting::where('setting_key', $data['setting_key'])
            ->where('effective_year', $data['effective_year'])
            ->exists();

        if ($exists) {
            return $this->error('Pengaturan dengan key yang sama sudah ada untuk tahun yang sama', 422);
        }

        $setting = TaxSetting::create($data);

        return $this->success(
            new TaxSettingResource($setting),
            'Pengaturan pajak berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxSetting $taxSetting): JsonResponse
    {
        return $this->success(new TaxSettingResource($taxSetting));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxSetting $taxSetting): JsonResponse
    {
        $data = $request->validate([
            'setting_key' => ['sometimes', 'string', 'max:50'],
            'setting_name' => ['sometimes', 'string', 'max:100'],
            'setting_value' => ['sometimes', 'numeric', 'min:0'],
            'category' => ['sometimes', 'in:ptkp,biaya_jabatan,ter,other'],
            'description' => ['nullable', 'string', 'max:500'],
            'effective_year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        // Check for duplicate key in the same year if changing key or year
        if (isset($data['setting_key']) || isset($data['effective_year'])) {
            $key = $data['setting_key'] ?? $taxSetting->setting_key;
            $year = $data['effective_year'] ?? $taxSetting->effective_year;

            $exists = TaxSetting::where('setting_key', $key)
                ->where('effective_year', $year)
                ->where('id', '!=', $taxSetting->id)
                ->exists();

            if ($exists) {
                return $this->error('Pengaturan dengan key yang sama sudah ada untuk tahun yang sama', 422);
            }
        }

        $taxSetting->update($data);

        return $this->success(new TaxSettingResource($taxSetting), 'Pengaturan pajak berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxSetting $taxSetting): JsonResponse
    {
        $taxSetting->delete();

        return $this->success(null, 'Pengaturan pajak berhasil dihapus');
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse
    {
        return $this->success(TaxSetting::getCategories());
    }

    /**
     * Get PTKP labels.
     */
    public function ptkpLabels(): JsonResponse
    {
        return $this->success(TaxSetting::getPtkpLabels());
    }

    /**
     * Get PTKP settings for specific year.
     */
    public function ptkpForYear(int $year): JsonResponse
    {
        $settings = TaxSetting::ptkp()
            ->forYear($year)
            ->active()
            ->get();

        return $this->success(TaxSettingResource::collection($settings));
    }

    /**
     * Get PTKP value for specific status and year.
     */
    public function getPtkpValue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:10'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $value = TaxSetting::getPtkpValue($tenantId, $data['status'], $data['year']);

        return $this->success([
            'status' => $data['status'],
            'year' => $data['year'],
            'value' => $value,
            'value_formatted' => 'Rp ' . number_format($value, 0, ',', '.'),
        ]);
    }

    /**
     * Get biaya jabatan settings for specific year.
     */
    public function biayaJabatanForYear(int $year): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $settings = TaxSetting::getBiayaJabatan($tenantId, $year);

        return $this->success([
            'year' => $year,
            'rate' => $settings['rate'],
            'rate_formatted' => $settings['rate'] . '%',
            'max_per_year' => $settings['max_per_year'],
            'max_per_year_formatted' => 'Rp ' . number_format($settings['max_per_year'], 0, ',', '.'),
            'max_per_month' => $settings['max_per_month'],
            'max_per_month_formatted' => 'Rp ' . number_format($settings['max_per_month'], 0, ',', '.'),
        ]);
    }
}
