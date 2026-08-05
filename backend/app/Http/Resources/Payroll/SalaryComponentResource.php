<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryComponentResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'calculation_type' => $this->calculation_type,
            'calculation_type_label' => $this->getCalculationTypeLabel(),
            'default_value' => (float) $this->default_value,
            'default_value_formatted' => $this->calculation_type === 'percentage'
                ? $this->default_value . '%'
                : 'Rp ' . number_format($this->default_value, 0, ',', '.'),
            'percentage_of' => $this->percentage_of,
            'formula' => $this->formula,
            'is_taxable' => $this->is_taxable,
            'is_mandatory' => $this->is_mandatory,
            'is_active' => $this->is_active,
            'order' => $this->order,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
