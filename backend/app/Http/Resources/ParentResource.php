<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'full_name' => $this->user?->full_name,
            'email' => $this->user?->email,
            'phone' => $this->phone,
            'relationship' => $this->relationship,
            'relationship_label' => $this->getRelationshipLabel(),
            'occupation' => $this->occupation,
            'income' => $this->income,
            'address' => $this->address,
            'is_primary_contact' => $this->is_primary_contact,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get relationship label.
     */
    private function getRelationshipLabel(): string
    {
        return match ($this->relationship) {
            'father' => 'Ayah',
            'mother' => 'Ibu',
            'guardian' => 'Wali',
            default => ucfirst($this->relationship),
        };
    }
}
