<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
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
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'full_name' => $this->student->user?->full_name,
                'photo_url' => $this->student->user?->avatar
                    ? asset('storage/' . $this->student->user->avatar)
                    : null,
            ]),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn() => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_active' => $this->academicYear->is_active,
            ]),
            'classroom_id' => $this->classroom_id,
            'classroom' => $this->whenLoaded('classroom', fn() => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
                'code' => $this->classroom->code ?? null,
            ]),
            'student_number_in_class' => $this->student_number_in_class,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'enrollment_date' => $this->enrollment_date?->format('Y-m-d'),
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
            'promoted' => 'Naik Kelas',
            'retained' => 'Tinggal Kelas',
            'transferred' => 'Pindah',
            'dropped' => 'Keluar',
            default => ucfirst($this->status ?? ''),
        };
    }
}
