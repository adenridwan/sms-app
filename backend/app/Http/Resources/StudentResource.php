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
        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'nik' => $this->nik,
            'user' => new UserResource($this->whenLoaded('user')),
            'full_name' => $this->user?->full_name,
            'email' => $this->user?->email,
            'gender' => $this->gender,
            'gender_label' => $this->gender === 'male' ? 'Laki-laki' : 'Perempuan',
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age' => $this->birth_date?->age,
            'religion' => $this->religion,
            'address' => $this->address,
            'phone' => $this->phone,
            'previous_school' => $this->previous_school,
            'entry_year' => $this->entry_year,
            'entry_class' => $this->entry_class,
            'entry_semester' => $this->entry_semester,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'photo' => $this->photo,
            'photo_url' => $this->photo ? asset('storage/' . $this->photo) : null,
            'current_class' => $this->whenLoaded('currentClass', fn() => [
                'id' => $this->currentClass->id,
                'name' => $this->currentClass->name,
            ]),
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
