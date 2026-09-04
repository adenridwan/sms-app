<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Payroll\Services\AttendancePayrollService;
use App\Domain\Payroll\Services\PayrollProgressService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\PayrollPeriodResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\BpjsRate;
use App\Infrastructure\Persistence\Eloquent\Payroll\EmployeeSalary;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollPeriod;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlipItem;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
use App\Infrastructure\Persistence\Eloquent\Payroll\TaxBracket;
use App\Infrastructure\Persistence\Eloquent\Payroll\TaxSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollPeriodController extends ApiController
{
    public function __construct(
        protected AttendancePayrollService $attendanceService,
        protected PayrollProgressService $progressService
    ) {}

    /**
     * Display a listing of payroll periods.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayrollPeriod::query()
            ->withCount('slips')
            ->when($request->year, fn($q, $year) => $q->where('year', $year))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%");
            });

        $sortField = $request->get('sort', 'year');
        $sortDirection = $request->get('direction', 'desc');

        if ($sortField === 'year') {
            $query->orderBy('year', $sortDirection)->orderBy('month', $sortDirection);
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->get('per_page', 15);
        $periods = $query->paginate($perPage);

        return $this->success(PayrollPeriodResource::collection($periods)->response()->getData(true));
    }

    /**
     * Store a newly created payroll period.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Check for duplicate period
        $exists = PayrollPeriod::where('year', $data['year'])
            ->where('month', $data['month'])
            ->exists();

        if ($exists) {
            return $this->error('Periode gaji untuk bulan dan tahun ini sudah ada', 422);
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['name'] = 'Gaji ' . $months[$data['month']] . ' ' . $data['year'];
        $data['status'] = PayrollPeriod::STATUS_DRAFT;

        $period = PayrollPeriod::create($data);

        return $this->success(
            new PayrollPeriodResource($period),
            'Periode gaji berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified payroll period.
     */
    public function show(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $payrollPeriod->loadCount('slips');
        $payrollPeriod->load(['approvedByUser', 'finalizedByUser']);

        return $this->success(new PayrollPeriodResource($payrollPeriod));
    }

    /**
     * Update the specified payroll period.
     */
    public function update(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        if (!$payrollPeriod->isEditable()) {
            return $this->error('Periode gaji yang sudah disetujui tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payrollPeriod->update($data);

        return $this->success(new PayrollPeriodResource($payrollPeriod), 'Periode gaji berhasil diperbarui');
    }

    /**
     * Remove the specified payroll period.
     */
    public function destroy(PayrollPeriod $payrollPeriod): JsonResponse
    {
        if (!$payrollPeriod->isDraft()) {
            return $this->error('Hanya periode dengan status draf yang dapat dihapus', 422);
        }

        $payrollPeriod->delete();

        return $this->success(null, 'Periode gaji berhasil dihapus');
    }

    /**
     * Get available statuses.
     */
    public function statuses(): JsonResponse
    {
        return $this->success(['data' => PayrollPeriod::getStatuses()]);
    }

    /**
     * Generate payroll slips for all employees with current salary.
     *
     * Otomatis menghitung komponen per_day dan per_hour dari:
     * - Data kehadiran (untuk komponen berbasis hadir/absen/telat)
     * - Jam mengajar dari jadwal (untuk komponen honor per jam)
     */
    public function generateSlips(PayrollPeriod $payrollPeriod): JsonResponse
    {
        if (!$payrollPeriod->canGenerateSlips()) {
            return $this->error('Slip gaji hanya dapat di-generate untuk periode dengan status draf', 422);
        }

        try {
            // Start progress tracking
            $this->progressService->start($payrollPeriod->id, 0);

            $result = DB::transaction(function () use ($payrollPeriod) {
                // Delete existing slips
                $payrollPeriod->slips()->delete();

                // Get all employees with current salary
                $employeeSalaries = EmployeeSalary::with(['salaryGrade', 'components.salaryComponent', 'teacher.user', 'staff.user'])
                    ->current()
                    ->get();

                $total = $employeeSalaries->count();
                $this->progressService->update($payrollPeriod->id, 0, "Memproses 0 dari {$total} karyawan...");

                $slipsCreated = 0;
                $slips = collect();

                foreach ($employeeSalaries as $index => $empSalary) {
                    $slip = $this->createSlipFromEmployeeSalary($payrollPeriod, $empSalary);
                    if ($slip) {
                        $slipsCreated++;
                        $slips->push($slip);
                    }

                    // Update progress setiap 5 slip atau di akhir
                    if (($index + 1) % 5 === 0 || $index === $total - 1) {
                        $this->progressService->update(
                            $payrollPeriod->id,
                            $index + 1,
                            "Membuat slip {$slipsCreated} dari {$total}..."
                        );
                    }
                }

                // Auto-calculate attendance-based components (per_day, per_hour)
                $tenantId = auth()->user()->tenant_id ?? request()->header('X-Tenant-ID');
                $startDate = Carbon::parse($payrollPeriod->start_date);
                $endDate = Carbon::parse($payrollPeriod->end_date);

                $this->progressService->update($payrollPeriod->id, $total, 'Menghitung komponen kehadiran...');

                $attendanceResult = $this->attendanceService->processAttendanceForPeriod(
                    $tenantId,
                    $startDate,
                    $endDate,
                    $slips,
                    function ($current, $total) use ($payrollPeriod) {
                        $this->progressService->update(
                            $payrollPeriod->id,
                            $current,
                            "Menghitung kehadiran {$current} dari {$total}..."
                        );
                    }
                );

                // Recalculate slips after attendance items added
                $this->progressService->update($payrollPeriod->id, $total, 'Menghitung BPJS & PPh21...');

                foreach ($slips as $slip) {
                    $this->calculateSlip($slip);
                }

                // Update period status and totals
                $payrollPeriod->update(['status' => PayrollPeriod::STATUS_PROCESSING]);
                $payrollPeriod->recalculateTotals();

                return [
                    'slips_created' => $slipsCreated,
                    'attendance_processed' => $attendanceResult['processed'],
                    'attendance_errors' => $attendanceResult['errors'],
                ];
            });

            $this->progressService->complete(
                $payrollPeriod->id,
                "Berhasil generate {$result['slips_created']} slip gaji",
                $result
            );

            return $this->success(
                $result,
                "Berhasil generate {$result['slips_created']} slip gaji dengan perhitungan kehadiran"
            );
        } catch (\Exception $e) {
            $this->progressService->error($payrollPeriod->id, $e->getMessage());
            return $this->error('Gagal generate slip gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get progress status for generate slip.
     */
    public function generateProgress(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $progress = $this->progressService->get($payrollPeriod->id);

        if (!$progress) {
            return $this->success([
                'status' => 'idle',
                'message' => 'Tidak ada proses yang berjalan',
            ]);
        }

        return $this->success($progress);
    }

    /**
     * Calculate all slips in the period (recalculate BPJS, taxes, etc.).
     */
    public function calculate(PayrollPeriod $payrollPeriod): JsonResponse
    {
        if ($payrollPeriod->isFinalized()) {
            return $this->error('Periode yang sudah final tidak dapat dihitung ulang', 422);
        }

        try {
            DB::transaction(function () use ($payrollPeriod) {
                $slips = $payrollPeriod->slips()->with('items')->get();

                foreach ($slips as $slip) {
                    $this->calculateSlip($slip);
                }

                $payrollPeriod->recalculateTotals();
            });

            return $this->success(
                new PayrollPeriodResource($payrollPeriod->fresh()),
                'Perhitungan gaji berhasil diperbarui'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal menghitung gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit period for approval.
     * Requires: payroll.process permission.
     */
    public function submitForApproval(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(
            request()->user()->can('payroll.process'),
            403,
            'Anda tidak memiliki izin untuk mengajukan persetujuan penggajian'
        );

        if ($payrollPeriod->status !== PayrollPeriod::STATUS_PROCESSING) {
            return $this->error('Hanya periode yang sedang diproses dapat diajukan untuk persetujuan', 422);
        }

        if ($payrollPeriod->slips()->count() === 0) {
            return $this->error('Periode harus memiliki minimal 1 slip gaji', 422);
        }

        $payrollPeriod->update(['status' => PayrollPeriod::STATUS_PENDING_APPROVAL]);

        return $this->success(
            new PayrollPeriodResource($payrollPeriod),
            'Periode gaji berhasil diajukan untuk persetujuan'
        );
    }

    /**
     * Approve the payroll period.
     * Requires: payroll.approve permission.
     */
    public function approve(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(
            request()->user()->can('payroll.approve'),
            403,
            'Anda tidak memiliki izin untuk menyetujui penggajian'
        );

        if (!$payrollPeriod->canApprove()) {
            return $this->error('Periode ini tidak dapat disetujui', 422);
        }

        $payrollPeriod->update([
            'status' => PayrollPeriod::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->success(
            new PayrollPeriodResource($payrollPeriod),
            'Periode gaji berhasil disetujui'
        );
    }

    /**
     * Mark period as paid.
     * Requires: payroll.approve permission.
     */
    public function markAsPaid(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(
            request()->user()->can('payroll.approve'),
            403,
            'Anda tidak memiliki izin untuk menandai pembayaran gaji'
        );

        if ($payrollPeriod->status !== PayrollPeriod::STATUS_APPROVED) {
            return $this->error('Hanya periode yang sudah disetujui dapat ditandai sebagai dibayar', 422);
        }

        $payrollPeriod->update(['status' => PayrollPeriod::STATUS_PAID]);
        $payrollPeriod->slips()->update(['status' => PayrollSlip::STATUS_PAID]);

        return $this->success(
            new PayrollPeriodResource($payrollPeriod),
            'Periode gaji berhasil ditandai sebagai dibayar'
        );
    }

    /**
     * Sync new employees - add slips for employees who don't have one yet.
     * Useful when new employees are added after initial slip generation.
     */
    public function syncNewEmployees(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(
            request()->user()->can('payroll.process'),
            403,
            'Anda tidak memiliki izin untuk sinkronisasi karyawan'
        );

        // Only allow sync for non-finalized periods
        if ($payrollPeriod->isFinalized()) {
            return $this->error('Periode yang sudah final tidak dapat disinkronisasi', 422);
        }

        try {
            $result = DB::transaction(function () use ($payrollPeriod) {
                // Get employee IDs that already have slips
                $existingEmployeeIds = $payrollPeriod->slips()
                    ->pluck('employee_salary_id')
                    ->toArray();

                // Get all current employees without slip in this period
                $newEmployees = EmployeeSalary::current()
                    ->with(['salaryGrade', 'teacher.user.profile', 'staff.user.profile', 'components.salaryComponent'])
                    ->whereNotIn('id', $existingEmployeeIds)
                    ->get();

                if ($newEmployees->isEmpty()) {
                    return ['added' => 0, 'message' => 'Tidak ada karyawan baru yang perlu ditambahkan'];
                }

                $slipsAdded = 0;
                $newSlips = [];

                foreach ($newEmployees as $employeeSalary) {
                    $slip = $this->createSlipForEmployee($payrollPeriod, $employeeSalary);
                    if ($slip) {
                        $newSlips[] = $slip;
                        $slipsAdded++;
                    }
                }

                // Process attendance for new slips (calculateSlip already called in createSlipForEmployee)
                if (!empty($newSlips)) {
                    $tenantId = auth()->user()->tenant_id ?? request()->header('X-Tenant-ID');
                    $startDate = \Carbon\Carbon::parse($payrollPeriod->start_date);
                    $endDate = \Carbon\Carbon::parse($payrollPeriod->end_date);

                    $this->attendanceService->processAttendanceForPeriod(
                        $tenantId,
                        $startDate,
                        $endDate,
                        collect($newSlips)
                    );
                }

                // Update period totals
                $payrollPeriod->recalculateTotals();

                return ['added' => $slipsAdded];
            });

            return $this->success(
                $result,
                $result['added'] > 0
                    ? "Berhasil menambahkan {$result['added']} slip gaji karyawan baru"
                    : 'Tidak ada karyawan baru yang perlu ditambahkan'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal sinkronisasi karyawan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a single slip for an employee (used by syncNewEmployees).
     * Reuses logic from createSlipFromEmployeeSalary but simplified.
     */
    private function createSlipForEmployee(PayrollPeriod $payrollPeriod, EmployeeSalary $employeeSalary): ?PayrollSlip
    {
        // Get employee info (same as createSlipFromEmployeeSalary)
        $employee = $employeeSalary->getEmployee();
        if (!$employee) {
            return null;
        }

        $employeeName = $employeeSalary->employee_type === 'teacher'
            ? ($employee->user?->full_name ?? $employee->full_name ?? 'Unknown')
            : ($employee->user?->full_name ?? 'Unknown');

        $employeeIdentifier = $employeeSalary->employee_type === 'teacher'
            ? $employee->nip
            : $employee->employee_id;

        // Create the slip (same structure as createSlipFromEmployeeSalary)
        $slip = PayrollSlip::create([
            'payroll_period_id' => $payrollPeriod->id,
            'employee_salary_id' => $employeeSalary->id,
            'employee_type' => $employeeSalary->employee_type,
            'employee_id' => $employeeSalary->employee_id,
            'employee_name' => $employeeName,
            'employee_identifier' => $employeeIdentifier,
            'salary_grade_code' => $employeeSalary->salaryGrade?->code,
            'ptkp_status' => $employeeSalary->ptkp_status,
            'base_salary' => $employeeSalary->base_salary,
            'gross_salary' => $employeeSalary->base_salary,
            'total_deductions' => 0,
            'net_salary' => $employeeSalary->base_salary,
            'status' => PayrollSlip::STATUS_DRAFT,
        ]);

        // Add base salary as item
        PayrollSlipItem::create([
            'payroll_slip_id' => $slip->id,
            'salary_component_id' => null,
            'component_code' => 'BASE_SALARY',
            'component_name' => 'Gaji Pokok',
            'type' => 'earning',
            'category' => 'fixed',
            'amount' => $employeeSalary->base_salary,
            'is_taxable' => true,
            'is_auto_calculated' => true,
        ]);

        // Add employee's active salary components
        foreach ($employeeSalary->components as $empComponent) {
            if (!$empComponent->is_active || !$empComponent->salaryComponent) {
                continue;
            }

            $comp = $empComponent->salaryComponent;
            PayrollSlipItem::create([
                'payroll_slip_id' => $slip->id,
                'salary_component_id' => $empComponent->salary_component_id,
                'component_code' => $comp->code,
                'component_name' => $comp->name,
                'type' => $comp->type,
                'category' => 'fixed',
                'amount' => $empComponent->value,
                'is_taxable' => $comp->is_taxable,
                'is_auto_calculated' => true,
            ]);
        }

        // Calculate the slip
        $this->calculateSlip($slip);

        return $slip;
    }

    /**
     * Finalize the payroll period (lock from further changes).
     * Requires: payroll.approve permission.
     */
    public function finalize(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(
            request()->user()->can('payroll.approve'),
            403,
            'Anda tidak memiliki izin untuk memfinalisasi penggajian'
        );

        if (!$payrollPeriod->canFinalize()) {
            return $this->error('Periode ini tidak dapat difinalisasi', 422);
        }

        $payrollPeriod->update([
            'status' => PayrollPeriod::STATUS_FINALIZED,
            'finalized_by' => auth()->id(),
            'finalized_at' => now(),
        ]);

        return $this->success(
            new PayrollPeriodResource($payrollPeriod),
            'Periode gaji berhasil difinalisasi'
        );
    }

    /**
     * Unfinalize the payroll period (revert to paid status).
     * Requires: payroll.approve permission.
     */
    public function unfinalize(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(
            request()->user()->can('payroll.approve'),
            403,
            'Anda tidak memiliki izin untuk membatalkan finalisasi'
        );

        if ($payrollPeriod->status !== PayrollPeriod::STATUS_FINALIZED) {
            return $this->error('Hanya periode yang sudah final dapat dibatalkan finalisasinya', 422);
        }

        $payrollPeriod->update([
            'status' => PayrollPeriod::STATUS_PAID,
            'finalized_by' => null,
            'finalized_at' => null,
        ]);

        return $this->success(
            new PayrollPeriodResource($payrollPeriod),
            'Finalisasi periode berhasil dibatalkan'
        );
    }

    /**
     * Get period summary statistics.
     */
    public function summary(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $stats = [
            'period' => $payrollPeriod->getPeriodLabel(),
            'status' => $payrollPeriod->getStatusLabel(),
            'employee_count' => $payrollPeriod->employee_count,
            'by_type' => [
                'teachers' => $payrollPeriod->slips()->teachers()->count(),
                'staff' => $payrollPeriod->slips()->staff()->count(),
            ],
            'totals' => [
                'gross' => (float) $payrollPeriod->total_gross,
                'gross_formatted' => 'Rp ' . number_format($payrollPeriod->total_gross, 0, ',', '.'),
                'deductions' => (float) $payrollPeriod->total_deductions,
                'deductions_formatted' => 'Rp ' . number_format($payrollPeriod->total_deductions, 0, ',', '.'),
                'net' => (float) $payrollPeriod->total_net,
                'net_formatted' => 'Rp ' . number_format($payrollPeriod->total_net, 0, ',', '.'),
            ],
            'by_status' => $payrollPeriod->slips()
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn($item) => [$item->status => $item->count]),
        ];

        return $this->success($stats);
    }

    /**
     * Calculate attendance data for all slips in the period.
     *
     * Menghitung ulang data kehadiran dari modul attendance dan menambahkan
     * komponen gaji berbasis kehadiran (tunjangan hadir, potongan absen, dll).
     *
     * Catatan: Fungsi ini untuk menghitung ulang slip yang sudah ada.
     * Untuk slip baru, generateSlips() sudah otomatis menghitung kehadiran.
     */
    public function calculateAttendance(PayrollPeriod $payrollPeriod): JsonResponse
    {
        if ($payrollPeriod->isFinalized()) {
            return $this->error('Periode yang sudah final tidak dapat dihitung ulang', 422);
        }

        if ($payrollPeriod->slips()->count() === 0) {
            return $this->error('Tidak ada slip gaji dalam periode ini. Generate slip terlebih dahulu.', 422);
        }

        try {
            // Start progress tracking
            $this->progressService->start($payrollPeriod->id, $payrollPeriod->slips()->count());

            $result = DB::transaction(function () use ($payrollPeriod) {
                $tenantId = auth()->user()->tenant_id ?? request()->header('X-Tenant-ID');
                $slips = $payrollPeriod->slips()->with('items')->get();
                $total = $slips->count();

                $startDate = Carbon::parse($payrollPeriod->start_date);
                $endDate = Carbon::parse($payrollPeriod->end_date);

                $this->progressService->update($payrollPeriod->id, 0, 'Menghitung kehadiran...');

                $processResult = $this->attendanceService->processAttendanceForPeriod(
                    $tenantId,
                    $startDate,
                    $endDate,
                    $slips,
                    function ($current, $total) use ($payrollPeriod) {
                        $this->progressService->update(
                            $payrollPeriod->id,
                            $current,
                            "Menghitung kehadiran {$current} dari {$total}..."
                        );
                    }
                );

                // Recalculate each slip to update totals
                $this->progressService->update($payrollPeriod->id, $total, 'Menghitung BPJS & PPh21...');

                foreach ($slips as $slip) {
                    $this->calculateSlip($slip);
                }

                // Update period totals
                $payrollPeriod->recalculateTotals();

                return $processResult;
            });

            $this->progressService->complete(
                $payrollPeriod->id,
                "Berhasil menghitung kehadiran untuk {$result['processed']} slip gaji",
                $result
            );

            return $this->success([
                'processed' => $result['processed'],
                'errors' => $result['errors'],
                'period' => new PayrollPeriodResource($payrollPeriod->fresh()),
            ], "Berhasil menghitung kehadiran untuk {$result['processed']} slip gaji");
        } catch (\Exception $e) {
            $this->progressService->error($payrollPeriod->id, $e->getMessage());
            return $this->error('Gagal menghitung kehadiran: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate slips with attendance calculation.
     *
     * @deprecated Gunakan generateSlips() - sekarang otomatis menghitung kehadiran.
     */
    public function generateSlipsWithAttendance(PayrollPeriod $payrollPeriod): JsonResponse
    {
        // generateSlips() sekarang sudah otomatis menghitung komponen kehadiran
        return $this->generateSlips($payrollPeriod);
    }

    /**
     * Create a slip from employee salary data.
     */
    protected function createSlipFromEmployeeSalary(PayrollPeriod $period, EmployeeSalary $empSalary): ?PayrollSlip
    {
        // Get employee info
        $employee = $empSalary->getEmployee();
        if (!$employee) {
            return null;
        }

        $employeeName = $empSalary->employee_type === 'teacher'
            ? ($employee->user?->full_name ?? $employee->full_name ?? 'Unknown')
            : ($employee->user?->full_name ?? 'Unknown');

        $employeeIdentifier = $empSalary->employee_type === 'teacher'
            ? $employee->nip
            : $employee->employee_id;

        // Create the slip
        $slip = PayrollSlip::create([
            'payroll_period_id' => $period->id,
            'employee_salary_id' => $empSalary->id,
            'employee_type' => $empSalary->employee_type,
            'employee_id' => $empSalary->employee_id,
            'employee_name' => $employeeName,
            'employee_identifier' => $employeeIdentifier,
            'salary_grade_code' => $empSalary->salaryGrade?->code,
            'ptkp_status' => $empSalary->ptkp_status,
            'base_salary' => $empSalary->base_salary,
            'gross_salary' => $empSalary->base_salary,
            'total_deductions' => 0,
            'net_salary' => $empSalary->base_salary,
            'status' => PayrollSlip::STATUS_DRAFT,
        ]);

        // Add base salary as item
        PayrollSlipItem::create([
            'payroll_slip_id' => $slip->id,
            'salary_component_id' => null,
            'component_code' => 'BASE_SALARY',
            'component_name' => 'Gaji Pokok',
            'type' => 'earning',
            'category' => 'fixed',
            'amount' => $empSalary->base_salary,
            'is_taxable' => true,
            'is_auto_calculated' => true,
        ]);

        // Add salary components
        foreach ($empSalary->components as $component) {
            if (!$component->is_active || !$component->salaryComponent) {
                continue;
            }

            PayrollSlipItem::create([
                'payroll_slip_id' => $slip->id,
                'salary_component_id' => $component->salary_component_id,
                'component_code' => $component->salaryComponent->code,
                'component_name' => $component->salaryComponent->name,
                'type' => $component->salaryComponent->type,
                'category' => 'fixed',
                'amount' => $component->value,
                'is_taxable' => $component->salaryComponent->is_taxable,
                'is_auto_calculated' => true,
            ]);
        }

        // Calculate the slip
        $this->calculateSlip($slip);

        return $slip;
    }

    /**
     * Calculate a single slip (BPJS, PPh21, totals).
     */
    protected function calculateSlip(PayrollSlip $slip): void
    {
        // Get earnings
        $grossSalary = $slip->items()->earnings()->sum('amount');

        // Calculate BPJS
        $bpjsItems = $this->calculateBpjs($slip, $grossSalary);

        // Calculate PPh 21
        $pph21 = $this->calculatePph21($slip, $grossSalary, $bpjsItems);

        // Get all deductions (including BPJS and PPh21)
        $totalDeductions = $slip->items()->deductions()->sum('amount');

        // Update slip totals
        $slip->update([
            'gross_salary' => $grossSalary,
            'bpjs_kesehatan' => $bpjsItems['kesehatan'] ?? 0,
            'bpjs_jht' => $bpjsItems['jht'] ?? 0,
            'bpjs_jp' => $bpjsItems['jp'] ?? 0,
            'pph21' => $pph21,
            'total_deductions' => $totalDeductions,
            'net_salary' => $grossSalary - $totalDeductions,
            'status' => PayrollSlip::STATUS_CALCULATED,
            'calculated_at' => now(),
            'calculated_by' => auth()->id(),
        ]);
    }

    /**
     * Calculate BPJS deductions.
     */
    protected function calculateBpjs(PayrollSlip $slip, float $grossSalary): array
    {
        $tenantId = auth()->user()->tenant_id;
        $result = [];

        // Remove existing BPJS items
        $slip->items()->where('category', 'bpjs')->delete();

        $bpjsTypes = ['kesehatan', 'jht', 'jp'];

        foreach ($bpjsTypes as $type) {
            $rate = BpjsRate::where('tenant_id', $tenantId)
                ->where('type', $type)
                ->where('is_active', true)
                ->where('effective_from', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', now());
                })
                ->first();

            if (!$rate) {
                continue;
            }

            $salaryBase = $grossSalary;

            // Apply ceiling if exists
            if ($rate->max_salary && $salaryBase > $rate->max_salary) {
                $salaryBase = $rate->max_salary;
            }

            $amount = $salaryBase * ($rate->employee_rate / 100);
            $result[$type] = $amount;

            PayrollSlipItem::create([
                'payroll_slip_id' => $slip->id,
                'salary_component_id' => null,
                'component_code' => 'BPJS_' . strtoupper(substr($type, 0, 3)),
                'component_name' => 'BPJS ' . ucfirst($type),
                'type' => 'deduction',
                'category' => 'bpjs',
                'amount' => $amount,
                'is_taxable' => false,
                'is_auto_calculated' => true,
            ]);
        }

        return $result;
    }

    /**
     * Calculate PPh 21.
     */
    protected function calculatePph21(PayrollSlip $slip, float $grossSalary, array $bpjsItems): float
    {
        $tenantId = auth()->user()->tenant_id;
        $currentYear = now()->year;

        // Remove existing tax items
        $slip->items()->where('category', 'tax')->delete();

        // 1. Gross annual
        $grossAnnual = $grossSalary * 12;

        // 2. Biaya Jabatan (5% max 6 juta/tahun or 500rb/bulan)
        $biayaJabatan = TaxSetting::where('tenant_id', $tenantId)
            ->where('category', 'biaya_jabatan')
            ->where('setting_key', 'biaya_jabatan_rate')
            ->where('effective_year', $currentYear)
            ->where('is_active', true)
            ->first();

        $biayaJabatanMax = TaxSetting::where('tenant_id', $tenantId)
            ->where('category', 'biaya_jabatan')
            ->where('setting_key', 'biaya_jabatan_max')
            ->where('effective_year', $currentYear)
            ->where('is_active', true)
            ->first();

        $biayaJabatanAmount = $grossAnnual * (($biayaJabatan?->setting_value ?? 5) / 100);
        $maxBiaya = ($biayaJabatanMax?->setting_value ?? 6000000);
        if ($biayaJabatanAmount > $maxBiaya) {
            $biayaJabatanAmount = $maxBiaya;
        }

        // 3. BPJS deductions (annual)
        $bpjsAnnual = array_sum($bpjsItems) * 12;

        // 4. Net income
        $netIncome = $grossAnnual - $biayaJabatanAmount - $bpjsAnnual;

        // 5. PTKP
        $ptkpKey = 'ptkp_' . strtolower(str_replace('/', '', $slip->ptkp_status));
        $ptkp = TaxSetting::where('tenant_id', $tenantId)
            ->where('category', 'ptkp')
            ->where('setting_key', $ptkpKey)
            ->where('effective_year', $currentYear)
            ->where('is_active', true)
            ->first();

        $ptkpAmount = $ptkp?->setting_value ?? 54000000; // Default TK/0

        // 6. PKP (Penghasilan Kena Pajak)
        $pkp = max(0, $netIncome - $ptkpAmount);

        if ($pkp <= 0) {
            return 0;
        }

        // 7. Calculate tax using brackets
        $brackets = TaxBracket::where('tenant_id', $tenantId)
            ->where('effective_year', $currentYear)
            ->where('is_active', true)
            ->orderBy('min_amount')
            ->get();

        $taxAnnual = 0;
        $remainingPkp = $pkp;

        foreach ($brackets as $bracket) {
            if ($remainingPkp <= 0) {
                break;
            }

            $min = $bracket->min_amount;
            $max = $bracket->max_amount ?? PHP_FLOAT_MAX;
            $rate = $bracket->rate / 100;

            $taxableInBracket = min($remainingPkp, $max - $min);
            if ($taxableInBracket > 0) {
                $taxAnnual += $taxableInBracket * $rate;
                $remainingPkp -= $taxableInBracket;
            }
        }

        // Monthly tax
        $pph21 = round($taxAnnual / 12);

        if ($pph21 > 0) {
            PayrollSlipItem::create([
                'payroll_slip_id' => $slip->id,
                'salary_component_id' => null,
                'component_code' => 'PPH21',
                'component_name' => 'PPh 21',
                'type' => 'deduction',
                'category' => 'tax',
                'amount' => $pph21,
                'is_taxable' => false,
                'is_auto_calculated' => true,
            ]);
        }

        return $pph21;
    }

    /**
     * Export semua slip gaji dalam satu periode sebagai ZIP file.
     */
    public function exportPdfZip(Request $request, PayrollPeriod $payrollPeriod): \Symfony\Component\HttpFoundation\Response
    {
        $employeeType = $request->query('employee_type');

        $service = app(\App\Domain\Payroll\Services\PayrollSlipPdfService::class);

        try {
            $zipPath = $service->generateBulkZip($payrollPeriod, $employeeType);

            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview penerima WhatsApp untuk bulk send.
     */
    public function previewWhatsAppRecipients(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $employeeType = $request->query('employee_type');

        $service = app(\App\Domain\Payroll\Services\PayrollSlipNotificationService::class);
        $result = $service->previewRecipients($payrollPeriod, $employeeType);

        return $this->success($result);
    }

    /**
     * Kirim slip gaji via WhatsApp ke semua karyawan dalam periode.
     */
    public function sendWhatsAppBulk(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $data = $request->validate([
            'employee_type' => ['nullable', 'in:teacher,staff'],
        ]);

        $service = app(\App\Domain\Payroll\Services\PayrollSlipNotificationService::class);
        $result = $service->sendBulk($payrollPeriod, $data['employee_type'] ?? null);

        if ($result['success']) {
            return $this->success($result, $result['message']);
        }

        return $this->error($result['message'], 422);
    }
}
