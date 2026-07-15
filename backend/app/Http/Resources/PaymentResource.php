<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'name' => $this->student->user?->full_name,
            ]),
            'fee_type_id' => $this->fee_type_id,
            'fee_type' => $this->whenLoaded('feeType', fn() => [
                'id' => $this->feeType->id,
                'name' => $this->feeType->name,
            ]),
            'amount' => $this->amount,
            'amount_formatted' => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            'discount' => $this->discount,
            'final_amount' => $this->amount - ($this->discount ?? 0),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->toISOString(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'notes' => $this->notes,
            'academic_year_id' => $this->academic_year_id,
            'semester_id' => $this->semester_id,
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
            'pending' => 'Belum Dibayar',
            'partial' => 'Dibayar Sebagian',
            'paid' => 'Lunas',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }
}
