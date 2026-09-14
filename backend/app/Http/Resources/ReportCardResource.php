<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'total_subjects' => $this->total_subjects,
            'average_score' => $this->average_score !== null ? (float) $this->average_score : null,
            'rank_in_class' => $this->rank_in_class,
            'total_students_in_class' => $this->total_students_in_class,
            'total_present_days' => $this->total_present_days,
            'total_absent_days' => $this->total_absent_days,
            'total_sick_days' => $this->total_sick_days,
            'total_permitted_days' => $this->total_permitted_days,
            'homeroom_notes' => $this->homeroom_notes,
            'principal_notes' => $this->principal_notes,
            'promotion_status' => $this->promotion_status,
            'promotion_status_label' => $this->promotion_status_label,
            'next_classroom' => $this->next_classroom,
            'issued_date' => $this->issued_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->status_label,
            'can_edit' => $this->canBeEdited(),
            'can_approve' => $this->canBeApproved(),
            'can_publish' => $this->canBePublished(),

            // Relations
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'full_name' => $this->student->user?->full_name,
                'photo_url' => $this->student->photo_url,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn() => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'academic_year' => $this->whenLoaded('academicYear', fn() => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'semester' => $this->whenLoaded('semester', fn() => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
                'semester_number' => $this->semester->semester_number,
            ]),
            'homeroom_teacher' => $this->whenLoaded('homeroomTeacher', fn() => [
                'id' => $this->homeroomTeacher->id,
                'full_name' => $this->homeroomTeacher->full_name,
            ]),
            'principal' => $this->whenLoaded('principal', fn() => [
                'id' => $this->principal->id,
                'full_name' => $this->principal->full_name,
            ]),
            'extracurriculars' => $this->whenLoaded('extracurriculars', fn() =>
                $this->extracurriculars->map(fn($e) => [
                    'id' => $e->id,
                    'activity_name' => $e->activity_name,
                    'predicate' => $e->predicate,
                    'description' => $e->description,
                ])
            ),
            'characters' => $this->whenLoaded('characters', fn() =>
                $this->characters->map(fn($c) => [
                    'id' => $c->id,
                    'character_name' => $c->character_name,
                    'predicate' => $c->predicate,
                    'description' => $c->description,
                ])
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
