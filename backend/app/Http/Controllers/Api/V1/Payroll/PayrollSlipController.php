<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\PayrollSlipResource;
use App\Http\Resources\Payroll\SalaryComponentResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollPeriod;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlipItem;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlipItemAudit;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
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
     * If salary_component_id is provided, use that component.
     * If not, find or create the component in master table.
     */
    public function addItem(Request $request, PayrollSlip $payrollSlip): JsonResponse
    {
        if (!$payrollSlip->isEditable()) {
            return $this->error('Slip gaji yang sudah disetujui tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'salary_component_id' => ['nullable', 'uuid', 'exists:salary_components,id'],
            'component_code' => ['required_without:salary_component_id', 'nullable', 'string', 'max:30'],
            'component_name' => ['required_without:salary_component_id', 'nullable', 'string', 'max:100'],
            'type' => ['required_without:salary_component_id', 'nullable', 'in:earning,deduction'],
            'category' => ['nullable', 'in:basic,allowance,attendance,statutory,tax,other'],
            'amount' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Determine salary component
        if (!empty($data['salary_component_id'])) {
            // Use existing component from master
            $component = SalaryComponent::find($data['salary_component_id']);
        } else {
            // Find or create component in master table
            $code = strtoupper($data['component_code']);
            $component = SalaryComponent::where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first();

            if (!$component) {
                // Create new component in master table
                $calculationType = 'fixed';
                $defaultValue = $data['amount'];

                // If quantity and rate provided, it's a per_day component
                if (!empty($data['quantity']) && !empty($data['rate']) && $data['quantity'] > 0) {
                    $calculationType = 'per_day';
                    $defaultValue = $data['rate'];
                }

                $component = SalaryComponent::create([
                    'tenant_id' => $tenantId,
                    'code' => $code,
                    'name' => $data['component_name'],
                    'type' => $data['type'],
                    'calculation_type' => $calculationType,
                    'default_value' => $defaultValue,
                    'is_taxable' => $data['is_taxable'] ?? true,
                    'is_mandatory' => false,
                    'is_active' => true,
                    'order' => SalaryComponent::where('tenant_id', $tenantId)
                        ->where('type', $data['type'])
                        ->max('order') + 1,
                    'description' => 'Dibuat otomatis dari slip gaji',
                ]);
            }
        }

        // Map category from frontend to backend enum
        $categoryMap = [
            'basic' => 'fixed',
            'allowance' => 'variable',
            'attendance' => 'attendance',
            'statutory' => 'bpjs',
            'tax' => 'tax',
            'other' => 'other',
        ];

        // Create slip item linked to component
        $itemData = [
            'payroll_slip_id' => $payrollSlip->id,
            'salary_component_id' => $component->id,
            'component_code' => $component->code,
            'component_name' => $component->name,
            'type' => $component->type,
            'category' => $categoryMap[$data['category'] ?? 'other'] ?? 'other',
            'amount' => $data['amount'],
            'quantity' => $data['quantity'] ?? 1,
            'rate' => $data['rate'] ?? null,
            'is_taxable' => $component->is_taxable,
            'is_auto_calculated' => false,
            'notes' => $data['notes'] ?? null,
        ];

        $item = PayrollSlipItem::create($itemData);

        $this->recalculateSlipTotals($payrollSlip);
        $payrollSlip->period->recalculateTotals();

        $payrollSlip->load('items');

        return $this->success(
            new PayrollSlipResource($payrollSlip),
            'Item berhasil ditambahkan'
        );
    }

    /**
     * Get available salary components for dropdown.
     */
    public function getComponents(Request $request): JsonResponse
    {
        $user = auth()->user();
        // Super admin: ambil tenant dari header atau context
        // User biasa: ambil dari tenant_id user
        $tenantId = $user->tenant_id ?? tenant()?->id;

        $query = SalaryComponent::where('is_active', true)
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->orderBy('type')
            ->orderBy('order');

        // Filter by tenant jika ada
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $components = $query->get();

        return $this->success(SalaryComponentResource::collection($components));
    }

    /**
     * Remove an item from the slip.
     */
    public function removeItem(PayrollSlip $payrollSlip, PayrollSlipItem $item): JsonResponse
    {
        if (!$payrollSlip->isEditable()) {
            return $this->error('Slip gaji yang sudah dibayar tidak dapat diubah', 422);
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
     * Update a single item with audit logging.
     * Allows editing quantity, rate, amount with reason.
     */
    public function updateItem(Request $request, PayrollSlip $payrollSlip, PayrollSlipItem $item): JsonResponse
    {
        if (!$payrollSlip->isEditable()) {
            return $this->error('Slip gaji yang sudah dibayar tidak dapat diubah', 422);
        }

        if ($item->payroll_slip_id !== $payrollSlip->id) {
            return $this->error('Item tidak ditemukan di slip ini', 404);
        }

        $data = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $reason = $data['reason'];
        unset($data['reason']);

        // Track changes for audit
        $audits = [];
        $editableFields = ['quantity', 'rate', 'amount', 'notes'];

        foreach ($editableFields as $field) {
            if (array_key_exists($field, $data)) {
                $oldValue = $item->{$field};
                $newValue = $data[$field];

                // Only log if value actually changed
                if ((string) $oldValue !== (string) $newValue) {
                    $audits[] = [
                        'payroll_slip_item_id' => $item->id,
                        'payroll_slip_id' => $payrollSlip->id,
                        'changed_by' => auth()->id(),
                        'field_name' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'reason' => $reason,
                    ];
                }
            }
        }

        if (empty($audits)) {
            return $this->error('Tidak ada perubahan yang dilakukan', 422);
        }

        try {
            DB::transaction(function () use ($item, $data, $audits, $payrollSlip) {
                // If quantity or rate changed, recalculate amount
                if (isset($data['quantity']) || isset($data['rate'])) {
                    $quantity = $data['quantity'] ?? $item->quantity;
                    $rate = $data['rate'] ?? $item->rate;

                    if ($quantity && $rate) {
                        $data['amount'] = (float) $quantity * (float) $rate;
                    }
                }

                // Mark as manually edited
                $data['is_auto_calculated'] = false;

                // Update item
                $item->update($data);

                // Create audit records
                foreach ($audits as $audit) {
                    PayrollSlipItemAudit::create($audit);
                }

                // Recalculate totals
                $this->recalculateSlipTotals($payrollSlip);
            });

            $payrollSlip->period->recalculateTotals();
            $payrollSlip->load('items');

            return $this->success(
                new PayrollSlipResource($payrollSlip),
                'Item berhasil diperbarui'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal memperbarui item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get audit history for a slip.
     */
    public function getAudits(PayrollSlip $payrollSlip): JsonResponse
    {
        $audits = $payrollSlip->audits()
            ->with(['item', 'changedByUser'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'item' => $audit->item ? [
                        'id' => $audit->item->id,
                        'component_code' => $audit->item->component_code,
                        'component_name' => $audit->item->component_name,
                    ] : null,
                    'field' => $audit->field_name,
                    'field_label' => $audit->getFieldLabel(),
                    'old_value' => $audit->old_value,
                    'new_value' => $audit->new_value,
                    'old_value_formatted' => $audit->getFormattedOldValue(),
                    'new_value_formatted' => $audit->getFormattedNewValue(),
                    'reason' => $audit->reason,
                    'changed_by' => $audit->changedByUser ? [
                        'id' => $audit->changedByUser->id,
                        'name' => $audit->changedByUser->name ?? $audit->changedByUser->username,
                    ] : null,
                    'changed_at' => $audit->created_at->format('d M Y H:i'),
                ];
            });

        return $this->success([
            'audits' => $audits,
            'total' => $audits->count(),
        ]);
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

    /**
     * Download PDF slip gaji.
     */
    public function downloadPdf(PayrollSlip $payrollSlip): \Symfony\Component\HttpFoundation\Response
    {
        $service = app(\App\Domain\Payroll\Services\PayrollSlipPdfService::class);
        return $service->download($payrollSlip);
    }

    /**
     * Stream PDF slip gaji untuk preview di browser.
     */
    public function previewPdf(PayrollSlip $payrollSlip): \Symfony\Component\HttpFoundation\Response
    {
        $service = app(\App\Domain\Payroll\Services\PayrollSlipPdfService::class);
        return $service->stream($payrollSlip);
    }

    /**
     * Kirim slip gaji via WhatsApp.
     */
    public function sendWhatsApp(Request $request, PayrollSlip $payrollSlip): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $service = app(\App\Domain\Payroll\Services\PayrollSlipNotificationService::class);
        $result = $service->sendSingle($payrollSlip, $data['phone'] ?? null);

        if ($result['success']) {
            return $this->success($result, $result['message']);
        }

        return $this->error($result['message'], 422);
    }

    /**
     * Get employee phone untuk preview sebelum kirim.
     */
    public function getEmployeePhone(PayrollSlip $payrollSlip): JsonResponse
    {
        $payrollSlip->load(['teacher.user.profile', 'staff.user.profile']);

        $employee = $payrollSlip->employee_type === 'teacher'
            ? $payrollSlip->teacher
            : $payrollSlip->staff;

        $phone = $employee?->user?->profile?->phone;

        return $this->success([
            'phone' => $phone,
            'phone_masked' => $phone ? $this->maskPhone($phone) : null,
            'has_phone' => !empty($phone),
        ]);
    }

    /**
     * Mask phone number for display.
     */
    private function maskPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) < 8) {
            return $phone;
        }
        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }
}
