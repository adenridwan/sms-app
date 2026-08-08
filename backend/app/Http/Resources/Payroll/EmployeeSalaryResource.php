<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_type' => $this->employee_type,
            'employee_type_label' => $this->getEmployeeTypeLabel(),
            'employee_id' => $this->employee_id,
            'employee' => $this->getEmployeeData(),
            'salary_grade_id' => $this->salary_grade_id,
            'salary_grade' => $this->whenLoaded('salaryGrade', fn() => [
                'id' => $this->salaryGrade->id,
                'code' => $this->salaryGrade->code,
                'name' => $this->salaryGrade->name,
                'base_salary' => (float) $this->salaryGrade->base_salary,
                'base_salary_formatted' => 'Rp ' . number_format($this->salaryGrade->base_salary, 0, ',', '.'),
            ]),
            'base_salary' => (float) $this->base_salary,
            'base_salary_formatted' => 'Rp ' . number_format($this->base_salary, 0, ',', '.'),
            'ptkp_status' => $this->ptkp_status,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_current' => $this->is_current,
            'notes' => $this->notes,
            'components' => $this->whenLoaded('components', fn() => $this->components->map(fn($comp) => [
                'id' => $comp->id,
                'salary_component_id' => $comp->salary_component_id,
                'salary_component' => $comp->relationLoaded('salaryComponent') && $comp->salaryComponent ? [
                    'id' => $comp->salaryComponent->id,
                    'code' => $comp->salaryComponent->code,
                    'name' => $comp->salaryComponent->name,
                    'type' => $comp->salaryComponent->type,
                    'type_label' => $comp->salaryComponent->getTypeLabel(),
                    'calculation_type' => $comp->salaryComponent->calculation_type,
                ] : null,
                'value' => (float) $comp->value,
                'value_formatted' => 'Rp ' . number_format($comp->value, 0, ',', '.'),
                'is_active' => $comp->is_active,
            ])),
            'total_earnings' => $this->when($this->relationLoaded('components'), fn() => (float) $this->calculateTotalEarnings()),
            'total_deductions' => $this->when($this->relationLoaded('components'), fn() => (float) $this->calculateTotalDeductions()),
            'total_earnings_formatted' => $this->when($this->relationLoaded('components'), fn() => 'Rp ' . number_format($this->calculateTotalEarnings(), 0, ',', '.')),
            'total_deductions_formatted' => $this->when($this->relationLoaded('components'), fn() => 'Rp ' . number_format($this->calculateTotalDeductions(), 0, ',', '.')),
            'components_count' => $this->whenCounted('components'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get employee data based on type.
     */
    protected function getEmployeeData(): ?array
    {
        if ($this->employee_type === 'teacher' && $this->relationLoaded('teacher') && $this->teacher) {
            return [
                'id' => $this->teacher->id,
                'nip' => $this->teacher->nip,
                'name' => $this->teacher->user?->full_name ?? $this->teacher->full_name,
                'email' => $this->teacher->user?->email ?? $this->teacher->email,
                'employment_status' => $this->teacher->employment_status,
            ];
        }

        if ($this->employee_type === 'staff' && $this->relationLoaded('staff') && $this->staff) {
            return [
                'id' => $this->staff->id,
                'employee_id' => $this->staff->employee_id,
                'name' => $this->staff->user?->full_name ?? $this->staff->full_name,
                'email' => $this->staff->user?->email ?? $this->staff->email,
                'employment_status' => $this->staff->employment_status,
            ];
        }

        return null;
    }
}
