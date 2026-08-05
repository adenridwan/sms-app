<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentFeeResource extends JsonResource
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
                'name' => $this->student->user?->full_name ?? $this->student->full_name,
                'classroom' => $this->student->currentClass ? [
                    'id' => $this->student->currentClass->id,
                    'name' => $this->student->currentClass->name,
                ] : null,
            ]),
            'fee_structure_id' => $this->fee_structure_id,
            'fee_structure' => $this->whenLoaded('feeStructure', fn() => [
                'id' => $this->feeStructure->id,
                'fee_type' => $this->feeStructure->feeType ? [
                    'id' => $this->feeStructure->feeType->id,
                    'code' => $this->feeStructure->feeType->code,
                    'name' => $this->feeStructure->feeType->name,
                    'frequency' => $this->feeStructure->feeType->frequency,
                ] : null,
                'grade_level' => $this->feeStructure->gradeLevel ? [
                    'id' => $this->feeStructure->gradeLevel->id,
                    'name' => $this->feeStructure->gradeLevel->name,
                ] : null,
            ]),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn() => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_active' => $this->academicYear->is_active,
            ]),
            'month' => $this->month,
            'year' => $this->year,
            'period_label' => $this->getPeriodLabel(),
            'amount' => (float) $this->amount,
            'amount_formatted' => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            'discount' => (float) $this->discount,
            'discount_formatted' => 'Rp ' . number_format($this->discount, 0, ',', '.'),
            'fine' => (float) $this->fine,
            'fine_formatted' => 'Rp ' . number_format($this->fine, 0, ',', '.'),
            'total_amount' => (float) $this->total_amount,
            'total_amount_formatted' => 'Rp ' . number_format($this->total_amount, 0, ',', '.'),
            'paid_amount' => (float) $this->paid_amount,
            'paid_amount_formatted' => 'Rp ' . number_format($this->paid_amount, 0, ',', '.'),
            'remaining_amount' => (float) $this->remaining_amount,
            'remaining_amount_formatted' => 'Rp ' . number_format($this->remaining_amount, 0, ',', '.'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get period label (e.g., "Januari 2025").
     */
    protected function getPeriodLabel(): string
    {
        if (!$this->month || !$this->year) {
            return '-';
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($months[$this->month] ?? $this->month) . ' ' . $this->year;
    }
}
