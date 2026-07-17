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
        $profile = $this->user?->profile;

        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'nuptk' => $this->nuptk,
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
            'religion' => $profile?->religion,
            'address' => $profile?->address,
            'phone' => $this->no_hp ?? $profile?->phone,
            'education_level' => $this->education_level,
            'education_major' => $this->education_major,
            'university' => $this->university,
            'employment_status' => $this->employment_status,
            'employment_status_label' => $this->getEmploymentStatusLabel(),
            'join_date' => $this->join_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'photo_url' => $this->user?->avatar ? asset('storage/' . $this->user->avatar) : null,
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
            'permanent' => 'Tetap',
            'contract' => 'Kontrak',
            'honorary' => 'Honorer',
            'part_time' => 'Paruh Waktu',
            default => ucfirst((string) $this->employment_status),
        };
    }

    /**
     * Get status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'on_leave' => 'Cuti',
            'retired' => 'Pensiun',
            'terminated' => 'Berhenti',
            default => ucfirst((string) $this->status),
        };
    }
}
