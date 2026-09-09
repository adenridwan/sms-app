<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeTypeResource extends JsonResource
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
            'description' => $this->description,
            'frequency' => $this->frequency,
            'frequency_label' => $this->getFrequencyLabel(),
            'is_mandatory' => $this->is_mandatory,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get frequency label.
     */
    private function getFrequencyLabel(): string
    {
        return match ($this->frequency) {
            'once' => 'Sekali',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            'semester' => 'Per Semester',
            'yearly' => 'Tahunan',
            default => ucfirst($this->frequency),
        };
    }
}
