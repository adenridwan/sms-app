<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
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
            'nip' => $this->nip,
            'nuptk' => $this->nuptk,
            'user' => new UserResource($this->whenLoaded('user')),
            'full_name' => $this->user?->full_name,
            'email' => $this->user?->email,
            'gender' => $this->gender,
            'gender_label' => $this->gender === 'male' ? 'Laki-laki' : 'Perempuan',
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'religion' => $this->religion,
            'address' => $this->address,
            'phone' => $this->phone,
            'education_level' => $this->education_level,
            'education_major' => $this->education_major,
            'employment_status' => $this->employment_status,
            'employment_status_label' => $this->getEmploymentStatusLabel(),
            'join_date' => $this->join_date?->format('Y-m-d'),
            'position' => $this->position,
            'specialization' => $this->specialization,
            'photo' => $this->photo,
            'photo_url' => $this->photo ? asset('storage/' . $this->photo) : null,
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'class_rooms' => ClassRoomResource::collection($this->whenLoaded('classRooms')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get employment status label.
     */
    private function getEmploymentStatusLabel(): string
    {
        return match ($this->employment_status) {
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'retired' => 'Pensiun',
            'resigned' => 'Mengundurkan Diri',
            default => ucfirst($this->employment_status),
        };
    }
}
