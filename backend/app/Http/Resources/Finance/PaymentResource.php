<?php

namespace App\Http\Resources\Finance;

use App\Infrastructure\Persistence\Eloquent\Finance\Payment;
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
                'name' => $this->student->user?->full_name ?? $this->student->full_name,
                'classroom' => $this->student->currentClass ? [
                    'id' => $this->student->currentClass->id,
                    'name' => $this->student->currentClass->name,
                ] : null,
            ]),
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => $this->whenLoaded('paymentMethod', fn() => $this->paymentMethod ? [
                'id' => $this->paymentMethod->id,
                'code' => $this->paymentMethod->code,
                'name' => $this->paymentMethod->name,
                'type' => $this->paymentMethod->type,
            ] : null),
            'total_amount' => (float) $this->total_amount,
            'total_amount_formatted' => 'Rp ' . number_format($this->total_amount, 0, ',', '.'),
            'admin_fee' => (float) $this->admin_fee,
            'admin_fee_formatted' => 'Rp ' . number_format($this->admin_fee, 0, ',', '.'),
            'grand_total' => (float) $this->grand_total,
            'grand_total_formatted' => 'Rp ' . number_format($this->grand_total, 0, ',', '.'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'paid_at' => $this->paid_at?->toISOString(),
            'transaction_id' => $this->transaction_id,
            'payment_proof' => $this->payment_proof,
            'notes' => $this->notes,
            'received_by' => $this->received_by,
            'received_by_user' => $this->whenLoaded('receivedBy', fn() => $this->receivedBy ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->full_name,
            ] : null),
            'verified_by' => $this->verified_by,
            'verified_by_user' => $this->whenLoaded('verifiedBy', fn() => $this->verifiedBy ? [
                'id' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->full_name,
            ] : null),
            'verified_at' => $this->verified_at?->toISOString(),
            'payment_details' => $this->payment_details,
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id' => $item->id,
                'student_fee_id' => $item->student_fee_id,
                'amount' => (float) $item->amount,
                'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                'student_fee' => $item->relationLoaded('studentFee') && $item->studentFee ? [
                    'id' => $item->studentFee->id,
                    'period_label' => $this->getPeriodLabel($item->studentFee->month, $item->studentFee->year),
                    'fee_type' => $item->studentFee->feeStructure?->feeType ? [
                        'id' => $item->studentFee->feeStructure->feeType->id,
                        'name' => $item->studentFee->feeStructure->feeType->name,
                    ] : null,
                ] : null,
            ])),
            'items_count' => $this->whenCounted('items'),
            'can_be_verified' => $this->canBeVerified(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get period label.
     */
    protected function getPeriodLabel(?int $month, ?int $year): string
    {
        if (!$month || !$year) {
            return '-';
        }

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        return ($months[$month] ?? $month) . ' ' . $year;
    }
}
