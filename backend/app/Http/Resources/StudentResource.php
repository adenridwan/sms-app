<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->user?->profile;

        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'nik' => $profile?->id_number,
            'user' => new UserResource($this->whenLoaded('user')),
            'full_name' => $this->user?->full_name,
            'email' => $this->user?->email,
            'gender' => $profile?->gender,
            'gender_label' => match ($profile?->gender) {
                'male' => 'Laki-laki',
                'female' => 'Perempuan',
                default => '-',
            },
            'birth_place' => $profile?->birth_place,
            'birth_date' => $profile?->birth_date?->format('Y-m-d'),
            'age' => $profile?->birth_date?->age,
            'religion' => $profile?->religion,
            'address' => $profile?->address,
            'phone' => $profile?->phone,
            'previous_school' => $this->previous_school,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'entry_type' => $this->entry_type,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'photo_url' => $this->user?->avatar ? asset('storage/' . $this->user->avatar) : null,
            'current_class' => $this->whenLoaded('currentClass', fn() => $this->currentClass ? [
                'id' => $this->currentClass->id,
                'name' => $this->currentClass->name,
            ] : null),
            'parents' => ParentResource::collection($this->whenLoaded('parents')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'graduated' => 'Lulus',
            'transferred' => 'Pindah',
            'dropped' => 'Keluar',
            default => ucfirst($this->status),
        };
    }
}
