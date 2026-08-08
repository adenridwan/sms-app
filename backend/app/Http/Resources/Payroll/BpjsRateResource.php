<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BpjsRateResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'name' => $this->name,
            'employee_rate' => (float) $this->employee_rate,
            'employee_rate_formatted' => $this->employee_rate . '%',
            'employer_rate' => (float) $this->employer_rate,
            'employer_rate_formatted' => $this->employer_rate . '%',
            'total_rate' => $this->total_rate,
            'total_rate_formatted' => $this->total_rate . '%',
            'min_salary' => $this->min_salary ? (float) $this->min_salary : null,
            'min_salary_formatted' => $this->min_salary
                ? 'Rp ' . number_format($this->min_salary, 0, ',', '.')
                : null,
            'max_salary' => $this->max_salary ? (float) $this->max_salary : null,
            'max_salary_formatted' => $this->max_salary
                ? 'Rp ' . number_format($this->max_salary, 0, ',', '.')
                : null,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'is_effective' => $this->isEffective(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
