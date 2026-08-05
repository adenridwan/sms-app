<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
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
            'provider' => $this->provider,
            'configuration' => $this->configuration,
            'admin_fee' => (float) $this->admin_fee,
            'admin_fee_formatted' => 'Rp ' . number_format($this->admin_fee, 0, ',', '.'),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
