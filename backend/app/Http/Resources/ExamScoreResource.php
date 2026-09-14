<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'student_id' => $this->student_id,
            'score' => $this->score !== null ? (float) $this->score : null,
            'notes' => $this->notes,
            'is_absent' => $this->is_absent,
            'is_remedial' => $this->is_remedial,
            'is_passed' => $this->is_passed,
            'grade_letter' => $this->grade_letter,
            'graded_at' => $this->graded_at?->toISOString(),

            // Relations
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'full_name' => $this->student->user?->full_name,
                'photo_url' => $this->student->photo_url,
            ]),
            'graded_by' => $this->whenLoaded('gradedBy', fn() => [
                'id' => $this->gradedBy->id,
                'full_name' => $this->gradedBy->full_name,
            ]),
            'exam' => $this->whenLoaded('exam', fn() => [
                'id' => $this->exam->id,
                'name' => $this->exam->name,
                'max_score' => (float) $this->exam->max_score,
                'passing_score' => (float) $this->exam->passing_score,
            ]),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
