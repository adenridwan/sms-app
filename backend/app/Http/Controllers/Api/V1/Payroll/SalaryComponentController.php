<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Payroll\Services\ComponentCalculationService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\SalaryComponentResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryComponentController extends ApiController
{
    public function __construct(
        protected ComponentCalculationService $calculationService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalaryComponent::with('percentageComponent')
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
            'percentage_component_id' => ['nullable', 'uuid', 'exists:salary_components,id'],
            'formula' => ['nullable', 'array'],
            'formula.*.type' => ['required_with:formula', 'in:component,base_salary,gross_salary,number,operator'],
            'formula.*.id' => ['nullable', 'uuid'],
            'formula.*.value' => ['nullable'],
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

        // Validate percentage_of untuk calculation_type = percentage
        if ($data['calculation_type'] === 'percentage') {
            if (empty($data['percentage_of']) && empty($data['percentage_component_id'])) {
                return $this->error('Persentase dari harus diisi untuk tipe persentase', 422);
            }
        }

        // Validate formula untuk calculation_type = formula
        if ($data['calculation_type'] === 'formula') {
            if (empty($data['formula'])) {
                return $this->error('Rumus harus diisi untuk tipe rumus khusus', 422);
            }

            $errors = $this->calculationService->validateFormula($data['formula']);
            if (!empty($errors)) {
                return $this->error('Rumus tidak valid: ' . implode(', ', $errors), 422);
            }
        }

        $component = SalaryComponent::create($data);
        $component->load('percentageComponent');

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
        $salaryComponent->load('percentageComponent');
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
            'percentage_component_id' => ['nullable', 'uuid', 'exists:salary_components,id'],
            'formula' => ['nullable', 'array'],
            'formula.*.type' => ['required_with:formula', 'in:component,base_salary,gross_salary,number,operator'],
            'formula.*.id' => ['nullable', 'uuid'],
            'formula.*.value' => ['nullable'],
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

        // Validate percentage_of untuk calculation_type = percentage
        if ($calculationType === 'percentage') {
            $percentageOf = $data['percentage_of'] ?? $salaryComponent->percentage_of;
            $percentageComponentId = $data['percentage_component_id'] ?? $salaryComponent->percentage_component_id;
            if (empty($percentageOf) && empty($percentageComponentId)) {
                return $this->error('Persentase dari harus diisi untuk tipe persentase', 422);
            }
        }

        // Validate formula untuk calculation_type = formula
        if ($calculationType === 'formula') {
            $formula = $data['formula'] ?? $salaryComponent->formula;
            if (empty($formula)) {
                return $this->error('Rumus harus diisi untuk tipe rumus khusus', 422);
            }

            if (isset($data['formula'])) {
                $errors = $this->calculationService->validateFormula($data['formula']);
                if (!empty($errors)) {
                    return $this->error('Rumus tidak valid: ' . implode(', ', $errors), 422);
                }
            }
        }

        // Prevent self-reference in percentage
        if (isset($data['percentage_component_id']) && $data['percentage_component_id'] === $salaryComponent->id) {
            return $this->error('Komponen tidak dapat mereferensikan dirinya sendiri', 422);
        }

        $salaryComponent->update($data);
        $salaryComponent->load('percentageComponent');

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

    /**
     * Get available percentage references.
     */
    public function percentageReferences(): JsonResponse
    {
        return $this->success([
            'references' => SalaryComponent::getPercentageReferences(),
            'operators' => ComponentCalculationService::FORMULA_OPERATORS,
        ]);
    }

    /**
     * Get components available for formula/percentage reference.
     * Excludes the given component ID to prevent self-reference.
     */
    public function availableForReference(Request $request): JsonResponse
    {
        $excludeId = $request->query('exclude');

        $components = SalaryComponent::query()
            ->where('is_active', true)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('type')
            ->orderBy('order')
            ->get(['id', 'code', 'name', 'type', 'default_value']);

        return $this->success($components->map(fn($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'type' => $c->type,
            'type_label' => $c->type === 'earning' ? 'Pendapatan' : 'Potongan',
            'default_value' => (float) $c->default_value,
            'default_value_formatted' => 'Rp ' . number_format($c->default_value, 0, ',', '.'),
        ]));
    }

    /**
     * Validate a formula structure.
     */
    public function validateFormula(Request $request): JsonResponse
    {
        $formula = $request->input('formula', []);

        if (!is_array($formula)) {
            return $this->error('Formula harus berupa array', 422);
        }

        $errors = $this->calculationService->validateFormula($formula);

        if (!empty($errors)) {
            return $this->success([
                'valid' => false,
                'errors' => $errors,
            ]);
        }

        return $this->success([
            'valid' => true,
            'errors' => [],
        ]);
    }
}
