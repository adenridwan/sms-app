<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\PayrollSlipResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollPeriod;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlipItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollSlipController extends ApiController
{
    /**
     * Display slips for a payroll period.
     */
    public function index(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $query = $payrollPeriod->slips()
            ->with('items')
            ->when($request->employee_type, fn($q, $type) => $q->where('employee_type', $type))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('employee_name', 'ilike', "%{$search}%")
                        ->orWhere('employee_identifier', 'ilike', "%{$search}%");
                });
            });

        $sortField = $request->get('sort', 'employee_name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $slips = $query->paginate($perPage);

        return $this->success(PayrollSlipResource::collection($slips)->response()->getData(true));
    }

    /**
     * Display the specified slip.
     */
    public function show(PayrollSlip $payrollSlip): JsonResponse
    {
        $payrollSlip->load(['items', 'period', 'employeeSalary.salaryGrade']);

        return $this->success(new PayrollSlipResource($payrollSlip));
    }

    /**
     * Update slip items (add/remove/modify components).
     */
    public function updateItems(Request $request, PayrollSlip $payrollSlip): JsonResponse
    {
        if (!$payrollSlip->isEditable()) {
            return $this->error('Slip gaji yang sudah disetujui tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'uuid'],
            'items.*.salary_component_id' => ['nullable', 'uuid', 'exists:salary_components,id'],
            'items.*.component_code' => ['required', 'string', 'max:30'],
            'items.*.component_name' => ['required', 'string', 'max:100'],
            'items.*.type' => ['required', 'in:earning,deduction'],
            'items.*.category' => ['required', 'in:fixed,variable,attendance,tax,bpjs,other'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_taxable' => ['boolean'],
            'items.*.is_auto_calculated' => ['boolean'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($data, $payrollSlip) {
                // Get existing item IDs from request
                $requestItemIds = collect($data['items'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Delete items not in request
                $payrollSlip->items()
                    ->whereNotIn('id', $requestItemIds)
                    ->delete();

                // Update or create items
                foreach ($data['items'] as $itemData) {
                    if (!empty($itemData['id'])) {
                        // Update existing
                        $item = PayrollSlipItem::find($itemData['id']);
                        if ($item && $item->payroll_slip_id === $payrollSlip->id) {
                            $item->update($itemData);
                        }
                    } else {
                        // Create new
                        $itemData['payroll_slip_id'] = $payrollSlip->id;
                        $itemData['is_auto_calculated'] = false;
                        PayrollSlipItem::create($itemData);
                    }
                }

                // Recalculate totals
                $this->recalculateSlipTotals($payrollSlip);
            });

            $payrollSlip->load('items');
            $payrollSlip->period->recalculateTotals();

            return $this->success(
                new PayrollSlipResource($payrollSlip),
                'Item slip gaji berhasil diperbarui'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal memperbarui item slip gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add a single item to the slip.
     */
    public function addItem(Request $request, PayrollSlip $payrollSlip): JsonResponse
    {
        if (!$payrollSlip->isEditable()) {
            return $this->error('Slip gaji yang sudah disetujui tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'salary_component_id' => ['nullable', 'uuid', 'exists:salary_components,id'],
            'component_code' => ['required', 'string', 'max:30'],
            'component_name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:earning,deduction'],
            'category' => ['required', 'in:fixed,variable,attendance,tax,bpjs,other'],
            'amount' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['payroll_slip_id'] = $payrollSlip->id;
        $data['is_auto_calculated'] = false;

        $item = PayrollSlipItem::create($data);

        $this->recalculateSlipTotals($payrollSlip);
        $payrollSlip->period->recalculateTotals();

        $payrollSlip->load('items');

        return $this->success(
            new PayrollSlipResource($payrollSlip),
            'Item berhasil ditambahkan'
        );
    }

    /**
     * Remove an item from the slip.
     */
    public function removeItem(PayrollSlip $payrollSlip, PayrollSlipItem $item): JsonResponse
    {
        if (!$payrollSlip->isEditable()) {
            return $this->error('Slip gaji yang sudah disetujui tidak dapat diubah', 422);
        }

        if ($item->payroll_slip_id !== $payrollSlip->id) {
            return $this->error('Item tidak ditemukan di slip ini', 404);
        }

        $item->delete();

        $this->recalculateSlipTotals($payrollSlip);
        $payrollSlip->period->recalculateTotals();

        $payrollSlip->load('items');

        return $this->success(
            new PayrollSlipResource($payrollSlip),
            'Item berhasil dihapus'
        );
    }

    /**
     * Update notes for a slip.
     */
    public function updateNotes(Request $request, PayrollSlip $payrollSlip): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payrollSlip->update($data);

        return $this->success(
            new PayrollSlipResource($payrollSlip),
            'Catatan berhasil diperbarui'
        );
    }

    /**
     * Get slip for printing/PDF.
     */
    public function printData(PayrollSlip $payrollSlip): JsonResponse
    {
        $payrollSlip->load(['items', 'period']);

        $earnings = $payrollSlip->items->where('type', 'earning')->values();
        $deductions = $payrollSlip->items->where('type', 'deduction')->values();

        return $this->success([
            'period' => [
                'name' => $payrollSlip->period->name,
                'year' => $payrollSlip->period->year,
                'month' => $payrollSlip->period->month,
                'period_label' => $payrollSlip->period->getPeriodLabel(),
            ],
            'employee' => [
                'name' => $payrollSlip->employee_name,
                'identifier' => $payrollSlip->employee_identifier,
                'type' => $payrollSlip->employee_type,
                'type_label' => $payrollSlip->getEmployeeTypeLabel(),
                'salary_grade' => $payrollSlip->salary_grade_code,
                'ptkp_status' => $payrollSlip->ptkp_status,
            ],
            'earnings' => $earnings->map(fn($item) => [
                'code' => $item->component_code,
                'name' => $item->component_name,
                'amount' => (float) $item->amount,
                'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
            ]),
            'deductions' => $deductions->map(fn($item) => [
                'code' => $item->component_code,
                'name' => $item->component_name,
                'amount' => (float) $item->amount,
                'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
            ]),
            'summary' => [
                'gross_salary' => (float) $payrollSlip->gross_salary,
                'gross_salary_formatted' => 'Rp ' . number_format($payrollSlip->gross_salary, 0, ',', '.'),
                'total_deductions' => (float) $payrollSlip->total_deductions,
                'total_deductions_formatted' => 'Rp ' . number_format($payrollSlip->total_deductions, 0, ',', '.'),
                'net_salary' => (float) $payrollSlip->net_salary,
                'net_salary_formatted' => 'Rp ' . number_format($payrollSlip->net_salary, 0, ',', '.'),
            ],
            'attendance' => [
                'working_days' => $payrollSlip->working_days,
                'days_present' => $payrollSlip->days_present,
                'days_absent' => $payrollSlip->days_absent,
                'days_late' => $payrollSlip->days_late,
                'days_leave' => $payrollSlip->days_leave,
            ],
        ]);
    }

    /**
     * Recalculate slip totals from items.
     */
    protected function recalculateSlipTotals(PayrollSlip $payrollSlip): void
    {
        $earnings = $payrollSlip->items()->earnings()->sum('amount');
        $deductions = $payrollSlip->items()->deductions()->sum('amount');

        // Categorize deductions
        $bpjsKes = $payrollSlip->items()
            ->deductions()
            ->where('component_code', 'BPJS_KES')
            ->sum('amount');

        $bpjsJht = $payrollSlip->items()
            ->deductions()
            ->where('component_code', 'BPJS_JHT')
            ->sum('amount');

        $bpjsJp = $payrollSlip->items()
            ->deductions()
            ->where('component_code', 'BPJS_JP')
            ->sum('amount');

        $pph21 = $payrollSlip->items()
            ->deductions()
            ->where('component_code', 'PPH21')
            ->sum('amount');

        $otherDeductions = $deductions - $bpjsKes - $bpjsJht - $bpjsJp - $pph21;

        $payrollSlip->update([
            'gross_salary' => $earnings,
            'bpjs_kesehatan' => $bpjsKes,
            'bpjs_jht' => $bpjsJht,
            'bpjs_jp' => $bpjsJp,
            'pph21' => $pph21,
            'total_other_deductions' => max(0, $otherDeductions),
            'total_deductions' => $deductions,
            'net_salary' => $earnings - $deductions,
            'status' => PayrollSlip::STATUS_CALCULATED,
            'calculated_at' => now(),
            'calculated_by' => auth()->id(),
        ]);
    }
}
