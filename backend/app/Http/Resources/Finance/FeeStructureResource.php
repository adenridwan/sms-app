<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeStructureResource extends JsonResource
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
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn() => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_active' => $this->academicYear->is_active,
            ]),
            'fee_type_id' => $this->fee_type_id,
            'fee_type' => $this->whenLoaded('feeType', fn() => [
                'id' => $this->feeType->id,
                'code' => $this->feeType->code,
                'name' => $this->feeType->name,
                'frequency' => $this->feeType->frequency,
            ]),
            'grade_level_id' => $this->grade_level_id,
            'grade_level' => $this->whenLoaded('gradeLevel', fn() => [
                'id' => $this->gradeLevel->id,
                'name' => $this->gradeLevel->name,
                'level' => $this->gradeLevel->level,
            ]),
            'major_id' => $this->major_id,
            'major' => $this->whenLoaded('major', fn() => $this->major ? [
                'id' => $this->major->id,
                'code' => $this->major->code,
                'name' => $this->major->name,
            ] : null),
            'amount' => (float) $this->amount,
            'amount_formatted' => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            'discount_amount' => (float) $this->discount_amount,
            'discount_amount_formatted' => 'Rp ' . number_format($this->discount_amount, 0, ',', '.'),
            'effective_amount' => $this->effective_amount,
            'effective_amount_formatted' => 'Rp ' . number_format($this->effective_amount, 0, ',', '.'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_day' => $this->due_day,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
