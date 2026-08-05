<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxBracketResource extends JsonResource
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
            'min_amount' => (float) $this->min_amount,
            'min_amount_formatted' => 'Rp ' . number_format($this->min_amount, 0, ',', '.'),
            'max_amount' => $this->max_amount ? (float) $this->max_amount : null,
            'max_amount_formatted' => $this->max_amount
                ? 'Rp ' . number_format($this->max_amount, 0, ',', '.')
                : 'Tidak terbatas',
            'range_label' => $this->getRangeLabel(),
            'rate' => (float) $this->rate,
            'rate_formatted' => $this->rate . '%',
            'effective_year' => $this->effective_year,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
