<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryMemberResource extends JsonResource
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
            'member_number' => $this->member_number,
            'member_type' => $this->member_type,
            'member_type_label' => $this->member_type_label,
            'registered_at' => $this->registered_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'max_borrow_limit' => $this->max_borrow_limit,
            'current_borrowed' => $this->current_borrowed,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'can_borrow' => $this->canBorrow(),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'full_name' => $this->user->full_name,
                'avatar_url' => $this->user->avatar_url,
            ]),
            'active_loans_count' => $this->whenCounted('activeLoans'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
