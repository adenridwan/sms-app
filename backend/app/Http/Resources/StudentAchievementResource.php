<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAchievementResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->category_label,
            'level' => $this->level,
            'level_label' => $this->level_label,
            'rank' => $this->rank,
            'achievement_date' => $this->achievement_date?->format('Y-m-d'),
            'organizer' => $this->organizer,
            'certificate_url' => $this->certificate_path
                ? asset('storage/' . $this->certificate_path)
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
