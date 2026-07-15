<?php

namespace App\Http\Resources;

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
            'amount' => $this->amount,
            'amount_formatted' => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'is_recurring' => $this->is_recurring,
            'recurring_period' => $this->recurring_period,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get category label.
     */
    private function getCategoryLabel(): string
    {
        return match ($this->category) {
            'tuition' => 'SPP',
            'registration' => 'Pendaftaran',
            'development' => 'Pengembangan',
            'activity' => 'Kegiatan',
            'other' => 'Lainnya',
            default => ucfirst($this->category),
        };
    }
}
