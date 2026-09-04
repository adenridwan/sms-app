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
            'percentage_of_label' => $this->getPercentageOfLabel(),
            'percentage_component_id' => $this->percentage_component_id,
            'percentage_component' => $this->whenLoaded('percentageComponent', function () {
                return [
                    'id' => $this->percentageComponent->id,
                    'code' => $this->percentageComponent->code,
                    'name' => $this->percentageComponent->name,
                ];
            }),
            'formula' => $this->formula,
            'formula_display' => $this->getFormulaDisplay(),
            'is_taxable' => $this->is_taxable,
            'is_mandatory' => $this->is_mandatory,
            'is_active' => $this->is_active,
            'order' => $this->order,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function getPercentageOfLabel(): ?string
    {
        if (!$this->percentage_of) {
            return null;
        }

        $references = \App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent::getPercentageReferences();

        if (isset($references[$this->percentage_of])) {
            return $references[$this->percentage_of];
        }

        // Jika percentage_component_id ada, tampilkan nama komponen
        if ($this->percentage_component_id && $this->relationLoaded('percentageComponent') && $this->percentageComponent) {
            return $this->percentageComponent->name;
        }

        return $this->percentage_of;
    }

    protected function getFormulaDisplay(): ?string
    {
        if (!$this->formula || !is_array($this->formula)) {
            return null;
        }

        $parts = [];
        foreach ($this->formula as $item) {
            $type = $item['type'] ?? '';

            switch ($type) {
                case 'component':
                    $parts[] = '{' . ($item['code'] ?? $item['id'] ?? '?') . '}';
                    break;
                case 'base_salary':
                    $parts[] = '{Gaji Pokok}';
                    break;
                case 'gross_salary':
                    $parts[] = '{Gaji Kotor}';
                    break;
                case 'number':
                    $parts[] = $item['value'] ?? '0';
                    break;
                case 'operator':
                    $parts[] = ' ' . ($item['value'] ?? '+') . ' ';
                    break;
            }
        }

        return implode('', $parts);
    }
}
