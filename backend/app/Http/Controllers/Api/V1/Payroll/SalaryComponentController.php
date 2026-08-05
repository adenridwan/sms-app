<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\SalaryComponentResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryComponentController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalaryComponent::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->calculation_type, fn($q, $calcType) => $q->where('calculation_type', $calcType))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('is_mandatory'), fn($q) => $q->where('is_mandatory', $request->boolean('is_mandatory')))
            ->when($request->has('is_taxable'), fn($q) => $q->where('is_taxable', $request->boolean('is_taxable')));

        $sortField = $request->get('sort', 'order');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy('type', 'asc')->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $components = $query->paginate($perPage);

        return $this->success(SalaryComponentResource::collection($components)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:salary_components,code'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:earning,deduction'],
            'calculation_type' => ['required', 'in:fixed,percentage,per_day,per_hour,formula'],
            'default_value' => ['required', 'numeric', 'min:0'],
            'percentage_of' => ['nullable', 'string', 'max:50'],
            'formula' => ['nullable', 'string', 'max:255'],
            'is_taxable' => ['boolean'],
            'is_mandatory' => ['boolean'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['order'] = $data['order'] ?? SalaryComponent::where('type', $data['type'])->max('order') + 1;

        // Validate percentage is <= 100
        if ($data['calculation_type'] === 'percentage' && $data['default_value'] > 100) {
            return $this->error('Nilai persentase tidak boleh lebih dari 100%', 422);
        }

        $component = SalaryComponent::create($data);

        return $this->success(
            new SalaryComponentResource($component),
            'Komponen gaji berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(SalaryComponent $salaryComponent): JsonResponse
    {
        return $this->success(new SalaryComponentResource($salaryComponent));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:salary_components,code,' . $salaryComponent->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:earning,deduction'],
            'calculation_type' => ['sometimes', 'in:fixed,percentage,per_day,per_hour,formula'],
            'default_value' => ['sometimes', 'numeric', 'min:0'],
            'percentage_of' => ['nullable', 'string', 'max:50'],
            'formula' => ['nullable', 'string', 'max:255'],
            'is_taxable' => ['boolean'],
            'is_mandatory' => ['boolean'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Validate percentage is <= 100
        $calculationType = $data['calculation_type'] ?? $salaryComponent->calculation_type;
        $value = $data['default_value'] ?? $salaryComponent->default_value;
        if ($calculationType === 'percentage' && $value > 100) {
            return $this->error('Nilai persentase tidak boleh lebih dari 100%', 422);
        }

        $salaryComponent->update($data);

        return $this->success(new SalaryComponentResource($salaryComponent), 'Komponen gaji berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalaryComponent $salaryComponent): JsonResponse
    {
        if ($salaryComponent->employeeSalaryComponents()->exists()) {
            return $this->error('Komponen gaji tidak dapat dihapus karena sudah digunakan oleh karyawan', 422);
        }

        $salaryComponent->delete();

        return $this->success(null, 'Komponen gaji berhasil dihapus');
    }

    /**
     * Get available component types.
     */
    public function types(): JsonResponse
    {
        return $this->success(SalaryComponent::getTypes());
    }

    /**
     * Get available calculation types.
     */
    public function calculationTypes(): JsonResponse
    {
        return $this->success(SalaryComponent::getCalculationTypes());
    }
}
