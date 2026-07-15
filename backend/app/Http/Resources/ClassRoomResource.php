<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassRoomResource extends JsonResource
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
            'name' => $this->name,
            'level' => $this->level,
            'major' => $this->major,
            'capacity' => $this->capacity,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn() => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'homeroom_teacher_id' => $this->homeroom_teacher_id,
            'homeroom_teacher' => $this->whenLoaded('homeroomTeacher', fn() => [
                'id' => $this->homeroomTeacher->id,
                'name' => $this->homeroomTeacher->user?->full_name,
            ]),
            'is_active' => $this->is_active,
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
