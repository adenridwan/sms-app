<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
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
            'code' => $this->code,
            'room' => $this->room,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'grade_level_id' => $this->grade_level_id,
            'grade_level' => $this->whenLoaded('gradeLevel', fn () => [
                'id' => $this->gradeLevel->id,
                'name' => $this->gradeLevel->name,
                'code' => $this->gradeLevel->code,
            ]),
            'major_id' => $this->major_id,
            'major' => $this->whenLoaded('major', fn () => $this->major ? [
                'id' => $this->major->id,
                'name' => $this->major->name,
                'code' => $this->major->code,
            ] : null),
            'homeroom_teacher_id' => $this->homeroom_teacher_id,
            'homeroom_teacher' => $this->whenLoaded('homeroomTeacher', fn () => $this->homeroomTeacher ? [
                'id' => $this->homeroomTeacher->id,
                'name' => $this->homeroomTeacher->full_name ?? $this->homeroomTeacher->name,
            ] : null),
            'students_count' => $this->whenCounted('enrollments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
