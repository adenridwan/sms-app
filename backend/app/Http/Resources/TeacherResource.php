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
            'user_id' => $this->user_id,
            'unique_code' => $this->unique_code,
            'rfid_code' => $this->rfid_code,
            'nip' => $this->nip,
            'nuptk' => $this->nuptk,

            // Akun & Pribadi
            'username' => $this->user?->username,
            // `email` = identitas login (bisa hasil generate), `contact_email`
            // = alamat surat sungguhan. Lihat docs/EMAIL-OTOMATIS-AKUN.md.
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
            'education_level' => $this->education_level,
            'education_major' => $this->education_major,
            'university' => $this->university,
            'teaching_experience_years' => $this->teaching_experience_years,
            'employment_status' => $this->employment_status,
            'employment_status_label' => $this->employmentStatusLabel(),
            'certification_status' => $this->certification_status,
            'certification_status_label' => $this->certificationStatusLabel(),
            'certification_number' => $this->certification_number,
            'join_date' => $this->join_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),

            // Akun: aktif/tidak & apakah masih wajib ganti password awal
            'account_is_active' => $this->user?->isActive() ?? false,
            'must_change_password' => $this->user?->mustChangePassword() ?? false,

            // Dokumen (Fase G2) — hanya ada bila relasi 'media' di-eager-load
            // (index() tidak memuatnya supaya tidak N+1 pada daftar guru).
            'documents' => $this->whenLoaded('media', fn () => $this->documentsSummary()),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function employmentStatusLabel(): string
    {
        return match ($this->employment_status) {
            'permanent' => 'Tetap',
            'contract' => 'Kontrak',
            'honorary' => 'Honorer',
            'part_time' => 'Paruh Waktu',
            default => '-',
        };
    }

    private function certificationStatusLabel(): string
    {
        return match ($this->certification_status) {
            'certified' => 'Sudah Sertifikasi',
            'not_certified' => 'Belum Sertifikasi',
            'in_progress' => 'Proses Sertifikasi',
            default => '-',
        };
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'on_leave' => 'Cuti',
            'retired' => 'Pensiun',
            'terminated' => 'Berhenti',
            default => '-',
        };
    }
}
