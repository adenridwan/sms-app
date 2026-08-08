<?php

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSlipResource extends JsonResource
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
            'payroll_period_id' => $this->payroll_period_id,
            'period' => $this->whenLoaded('period', fn() => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'year' => $this->period->year,
                'month' => $this->period->month,
                'period_label' => $this->period->getPeriodLabel(),
                'status' => $this->period->status,
            ]),
            'employee_salary_id' => $this->employee_salary_id,
            'employee_type' => $this->employee_type,
            'employee_type_label' => $this->getEmployeeTypeLabel(),
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee_name,
            'employee_identifier' => $this->employee_identifier,
            'salary_grade_code' => $this->salary_grade_code,
            'ptkp_status' => $this->ptkp_status,

            // Earnings
            'base_salary' => (float) $this->base_salary,
            'base_salary_formatted' => 'Rp ' . number_format($this->base_salary, 0, ',', '.'),
            'total_allowances' => (float) $this->total_allowances,
            'total_allowances_formatted' => 'Rp ' . number_format($this->total_allowances, 0, ',', '.'),
            'total_overtime' => (float) $this->total_overtime,
            'total_overtime_formatted' => 'Rp ' . number_format($this->total_overtime, 0, ',', '.'),
            'total_other_income' => (float) $this->total_other_income,
            'total_other_income_formatted' => 'Rp ' . number_format($this->total_other_income, 0, ',', '.'),
            'gross_salary' => (float) $this->gross_salary,
            'gross_salary_formatted' => 'Rp ' . number_format($this->gross_salary, 0, ',', '.'),

            // Deductions
            'bpjs_kesehatan' => (float) $this->bpjs_kesehatan,
            'bpjs_kesehatan_formatted' => 'Rp ' . number_format($this->bpjs_kesehatan, 0, ',', '.'),
            'bpjs_jht' => (float) $this->bpjs_jht,
            'bpjs_jht_formatted' => 'Rp ' . number_format($this->bpjs_jht, 0, ',', '.'),
            'bpjs_jp' => (float) $this->bpjs_jp,
            'bpjs_jp_formatted' => 'Rp ' . number_format($this->bpjs_jp, 0, ',', '.'),
            'pph21' => (float) $this->pph21,
            'pph21_formatted' => 'Rp ' . number_format($this->pph21, 0, ',', '.'),
            'total_other_deductions' => (float) $this->total_other_deductions,
            'total_other_deductions_formatted' => 'Rp ' . number_format($this->total_other_deductions, 0, ',', '.'),
            'total_deductions' => (float) $this->total_deductions,
            'total_deductions_formatted' => 'Rp ' . number_format($this->total_deductions, 0, ',', '.'),

            // Net
            'net_salary' => (float) $this->net_salary,
            'net_salary_formatted' => 'Rp ' . number_format($this->net_salary, 0, ',', '.'),

            // Attendance
            'working_days' => $this->working_days,
            'days_present' => $this->days_present,
            'days_absent' => $this->days_absent,
            'days_late' => $this->days_late,
            'days_leave' => $this->days_leave,
            'attendance_deduction' => (float) $this->attendance_deduction,
            'attendance_deduction_formatted' => 'Rp ' . number_format($this->attendance_deduction, 0, ',', '.'),

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_editable' => $this->isEditable(),
            'calculated_by' => $this->whenLoaded('calculatedByUser', fn() => [
                'id' => $this->calculatedByUser->id,
                'name' => $this->calculatedByUser->full_name,
            ]),
            'calculated_at' => $this->calculated_at?->toISOString(),
            'notes' => $this->notes,

            // Items
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id' => $item->id,
                'salary_component_id' => $item->salary_component_id,
                'component_code' => $item->component_code,
                'component_name' => $item->component_name,
                'type' => $item->type,
                'type_label' => $item->getTypeLabel(),
                'category' => $item->category,
                'category_label' => $item->getCategoryLabel(),
                'amount' => (float) $item->amount,
                'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                'quantity' => (float) $item->quantity,
                'rate' => $item->rate ? (float) $item->rate : null,
                'rate_formatted' => $item->rate ? 'Rp ' . number_format($item->rate, 0, ',', '.') : null,
                'is_taxable' => $item->is_taxable,
                'is_auto_calculated' => $item->is_auto_calculated,
                'notes' => $item->notes,
            ])),

            'earnings' => $this->when($this->relationLoaded('items'), fn() =>
                $this->items->where('type', 'earning')->values()->map(fn($item) => [
                    'id' => $item->id,
                    'code' => $item->component_code,
                    'name' => $item->component_name,
                    'amount' => (float) $item->amount,
                    'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                    'is_auto_calculated' => $item->is_auto_calculated,
                ])
            ),

            'deductions' => $this->when($this->relationLoaded('items'), fn() =>
                $this->items->where('type', 'deduction')->values()->map(fn($item) => [
                    'id' => $item->id,
                    'code' => $item->component_code,
                    'name' => $item->component_name,
                    'amount' => (float) $item->amount,
                    'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                    'is_auto_calculated' => $item->is_auto_calculated,
                ])
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
