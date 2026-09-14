<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Exam\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'exam_date' => $this->exam_date?->format('Y-m-d'),
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'duration_minutes' => $this->duration_minutes,
            'max_score' => (float) $this->max_score,
            'passing_score' => (float) $this->passing_score,
            'weight' => (float) $this->weight,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'score_count' => $this->score_count,
            'average_score' => $this->average_score,
            'can_edit' => $this->canBeEdited(),
            'can_delete' => $this->canBeDeleted(),
            'can_accept_scores' => $this->canAcceptScores(),

            // Relations
            'academic_year' => $this->whenLoaded('academicYear', fn() => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'semester' => $this->whenLoaded('semester', fn() => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
                'semester_number' => $this->semester->semester_number,
            ]),
            'subject' => $this->whenLoaded('subject', fn() => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ]),
            'exam_type' => $this->whenLoaded('examType', fn() => [
                'id' => $this->examType->id,
                'name' => $this->examType->name,
                'code' => $this->examType->code,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn() => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn() => [
                'id' => $this->teacher->id,
                'full_name' => $this->teacher->user?->full_name,
                'nip' => $this->teacher->nip,
            ]),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
