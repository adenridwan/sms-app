<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
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
            'year' => $this->year,
            'month' => $this->month,
            'period_label' => $this->getPeriodLabel(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_draft' => $this->isDraft(),
            'is_editable' => $this->isEditable(),
            'is_finalized' => $this->isFinalized(),
            'can_generate_slips' => $this->canGenerateSlips(),
            'can_approve' => $this->canApprove(),
            'can_finalize' => $this->canFinalize(),
            'approved_by' => $this->whenLoaded('approvedByUser', fn() => [
                'id' => $this->approvedByUser->id,
                'name' => $this->approvedByUser->full_name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'finalized_by' => $this->whenLoaded('finalizedByUser', fn() => [
                'id' => $this->finalizedByUser->id,
                'name' => $this->finalizedByUser->full_name,
            ]),
            'finalized_at' => $this->finalized_at?->toISOString(),
            'notes' => $this->notes,
            'total_gross' => (float) $this->total_gross,
            'total_gross_formatted' => 'Rp ' . number_format($this->total_gross, 0, ',', '.'),
            'total_deductions' => (float) $this->total_deductions,
            'total_deductions_formatted' => 'Rp ' . number_format($this->total_deductions, 0, ',', '.'),
            'total_net' => (float) $this->total_net,
            'total_net_formatted' => 'Rp ' . number_format($this->total_net, 0, ',', '.'),
            'employee_count' => $this->employee_count,
            'slips_count' => $this->whenCounted('slips'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
