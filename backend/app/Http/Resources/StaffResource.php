<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
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
            'user_id' => $this->user_id,
            'unique_code' => $this->unique_code,
            'rfid_code' => $this->rfid_code,
            'employee_id' => $this->employee_id,

            // Akun & Pribadi
            'username' => $this->user?->username,
            'email' => $this->user?->email,
            'contact_email' => $this->user?->contact_email,
            'email_is_generated' => (bool) $this->user?->email_is_generated,
            'first_name' => $profile?->first_name,
            'last_name' => $profile?->last_name,
            'full_name' => $this->user?->full_name,
            'phone' => $this->no_hp ?? $profile?->phone,
            'avatar_url' => $this->user?->avatar ? asset('storage/' . $this->user->avatar) : null,
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
            'id_number' => $profile?->id_number,

            // Kepegawaian
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'position_id' => $this->position_id,
            'position_name' => $this->position?->name,
            'education_level' => $this->education_level,
            'employment_status' => $this->employment_status,
            'employment_status_label' => $this->employmentStatusLabel(),
            'join_date' => $this->join_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),

            // Akun: aktif/tidak & apakah masih wajib ganti password awal
            'account_is_active' => $this->user?->isActive() ?? false,
            'must_change_password' => $this->user?->mustChangePassword() ?? false,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
