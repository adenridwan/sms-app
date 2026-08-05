<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxSettingResource extends JsonResource
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
            'setting_key' => $this->setting_key,
            'setting_name' => $this->setting_name,
            'setting_value' => (float) $this->setting_value,
            'setting_value_formatted' => 'Rp ' . number_format($this->setting_value, 0, ',', '.'),
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'description' => $this->description,
            'effective_year' => $this->effective_year,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
