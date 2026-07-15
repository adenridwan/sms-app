<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'credit_hours' => $this->credit_hours,
            'is_active' => $this->is_active,
            'teachers_count' => $this->whenCounted('teachers'),
            'teachers' => TeacherResource::collection($this->whenLoaded('teachers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get category label.
     */
    private function getCategoryLabel(): string
    {
        return match ($this->category) {
            'mandatory' => 'Wajib',
            'local' => 'Muatan Lokal',
            'elective' => 'Pilihan',
            'extracurricular' => 'Ekstrakurikuler',
            default => ucfirst($this->category),
        };
    }
}
