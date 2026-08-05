<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\EmployeeSalaryResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\EmployeeSalary;
use App\Infrastructure\Persistence\Eloquent\Payroll\EmployeeSalaryComponent;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryGrade;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryHistory;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeSalaryController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeSalary::query()
            ->with(['salaryGrade', 'teacher.user', 'staff.user', 'components.salaryComponent'])
            ->withCount('components')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    // Search in teacher's user name
                    $query->whereHas('teacher.user', function ($sub) use ($search) {
                        $sub->where('full_name', 'ilike', "%{$search}%");
                    })
                    // Search in staff's user name
                    ->orWhereHas('staff.user', function ($sub) use ($search) {
                        $sub->where('full_name', 'ilike', "%{$search}%");
                    })
                    // Search in teacher NIP
                    ->orWhereHas('teacher', function ($sub) use ($search) {
                        $sub->where('nip', 'ilike', "%{$search}%");
                    })
                    // Search in staff employee_id
                    ->orWhereHas('staff', function ($sub) use ($search) {
                        $sub->where('employee_id', 'ilike', "%{$search}%");
                    });
                });
            })
            ->when($request->employee_type, fn($q, $type) => $q->where('employee_type', $type))
            ->when($request->salary_grade_id, fn($q, $gradeId) => $q->where('salary_grade_id', $gradeId))
            ->when($request->has('is_current'), fn($q) => $q->where('is_current', $request->boolean('is_current')))
            ->when($request->ptkp_status, fn($q, $status) => $q->where('ptkp_status', $status));

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $salaries = $query->paginate($perPage);

        return $this->success(EmployeeSalaryResource::collection($salaries)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_type' => ['required', 'string', Rule::in([EmployeeSalary::TYPE_TEACHER, EmployeeSalary::TYPE_STAFF])],
            'employee_id' => ['required', 'uuid'],
            'salary_grade_id' => ['required', 'uuid', 'exists:salary_grades,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'ptkp_status' => ['required', 'string', 'max:20'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:effective_date'],
            'is_current' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'components' => ['nullable', 'array'],
            'components.*.salary_component_id' => ['required_with:components', 'uuid', 'exists:salary_components,id'],
            'components.*.value' => ['required_with:components', 'numeric', 'min:0'],
            'components.*.is_active' => ['boolean'],
        ]);

        // Validate employee exists
        $employeeExists = $this->validateEmployeeExists($data['employee_type'], $data['employee_id']);
        if (!$employeeExists) {
            return $this->error('Karyawan tidak ditemukan', 404);
        }

        // Check if employee already has current salary setup
        if ($request->boolean('is_current', true)) {
            $existingCurrent = EmployeeSalary::forEmployee($data['employee_type'], $data['employee_id'])
                ->current()
                ->exists();
            if ($existingCurrent) {
                return $this->error('Karyawan sudah memiliki pengaturan gaji aktif. Nonaktifkan dulu yang lama atau gunakan fitur perbarui gaji.', 422);
            }
        }

        try {
            $salary = DB::transaction(function () use ($data, $request) {
                $data['tenant_id'] = auth()->user()->tenant_id;
                $data['is_current'] = $request->boolean('is_current', true);

                $components = $data['components'] ?? [];
                unset($data['components']);

                $salary = EmployeeSalary::create($data);

                // Create components
                foreach ($components as $component) {
                    $salary->components()->create([
                        'salary_component_id' => $component['salary_component_id'],
                        'value' => $component['value'],
                        'is_active' => $component['is_active'] ?? true,
                    ]);
                }

                // Create initial salary history
                SalaryHistory::create([
                    'employee_salary_id' => $salary->id,
                    'changed_by' => auth()->id(),
                    'change_type' => SalaryHistory::TYPE_INITIAL,
                    'old_grade_id' => null,
                    'new_grade_id' => $salary->salary_grade_id,
                    'old_base_salary' => null,
                    'new_base_salary' => $salary->base_salary,
                    'effective_date' => $salary->effective_date,
                    'reason' => 'Penetapan gaji awal',
                ]);

                return $salary;
            });

            $salary->load(['salaryGrade', 'teacher.user', 'staff.user', 'components.salaryComponent']);

            return $this->success(
                new EmployeeSalaryResource($salary),
                'Pengaturan gaji karyawan berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            return $this->error('Gagal menyimpan pengaturan gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeSalary $employeeSalary): JsonResponse
    {
        $employeeSalary->load(['salaryGrade', 'teacher.user', 'staff.user', 'components.salaryComponent']);
        $employeeSalary->loadCount('components');

        return $this->success(new EmployeeSalaryResource($employeeSalary));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmployeeSalary $employeeSalary): JsonResponse
    {
        $data = $request->validate([
            'salary_grade_id' => ['sometimes', 'uuid', 'exists:salary_grades,id'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'ptkp_status' => ['sometimes', 'string', 'max:20'],
            'effective_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:effective_date'],
            'is_current' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'change_type' => ['nullable', 'string', Rule::in(array_keys(SalaryHistory::getChangeTypes()))],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $salary = DB::transaction(function () use ($data, $request, $employeeSalary) {
                $oldGradeId = $employeeSalary->salary_grade_id;
                $oldBaseSalary = $employeeSalary->base_salary;

                // Track if significant change happened
                $gradeChanged = isset($data['salary_grade_id']) && $data['salary_grade_id'] !== $oldGradeId;
                $salaryChanged = isset($data['base_salary']) && (float) $data['base_salary'] !== (float) $oldBaseSalary;

                // If setting as current, deactivate other current salaries for this employee
                if ($request->boolean('is_current', false) && !$employeeSalary->is_current) {
                    EmployeeSalary::forEmployee($employeeSalary->employee_type, $employeeSalary->employee_id)
                        ->where('id', '!=', $employeeSalary->id)
                        ->current()
                        ->update(['is_current' => false, 'end_date' => now()->subDay()]);
                }

                unset($data['change_type'], $data['change_reason']);
                $employeeSalary->update($data);

                // Create salary history if significant change
                if ($gradeChanged || $salaryChanged) {
                    $changeType = $request->input('change_type');

                    // Auto-detect change type if not provided
                    if (!$changeType) {
                        $newSalary = (float) ($data['base_salary'] ?? $oldBaseSalary);
                        if ($newSalary > (float) $oldBaseSalary) {
                            $changeType = SalaryHistory::TYPE_PROMOTION;
                        } elseif ($newSalary < (float) $oldBaseSalary) {
                            $changeType = SalaryHistory::TYPE_DEMOTION;
                        } else {
                            $changeType = SalaryHistory::TYPE_ADJUSTMENT;
                        }
                    }

                    SalaryHistory::create([
                        'employee_salary_id' => $employeeSalary->id,
                        'changed_by' => auth()->id(),
                        'change_type' => $changeType,
                        'old_grade_id' => $oldGradeId,
                        'new_grade_id' => $employeeSalary->salary_grade_id,
                        'old_base_salary' => $oldBaseSalary,
                        'new_base_salary' => $employeeSalary->base_salary,
                        'effective_date' => $employeeSalary->effective_date,
                        'reason' => $request->input('change_reason', 'Perubahan gaji'),
                    ]);
                }

                return $employeeSalary;
            });

            $salary->load(['salaryGrade', 'teacher.user', 'staff.user', 'components.salaryComponent']);

            return $this->success(new EmployeeSalaryResource($salary), 'Pengaturan gaji berhasil diperbarui');
        } catch (\Exception $e) {
            return $this->error('Gagal memperbarui pengaturan gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeSalary $employeeSalary): JsonResponse
    {
        try {
            DB::transaction(function () use ($employeeSalary) {
                // Delete related components and history
                $employeeSalary->components()->delete();
                $employeeSalary->histories()->delete();
                $employeeSalary->delete();
            });

            return $this->success(null, 'Pengaturan gaji berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus pengaturan gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available employees (teachers/staff without current salary setup).
     */
    public function availableEmployees(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all');

        $employees = [];

        if ($type === 'all' || $type === 'teacher') {
            $teachersWithSalary = EmployeeSalary::where('employee_type', EmployeeSalary::TYPE_TEACHER)
                ->current()
                ->pluck('employee_id');

            $teachers = Teacher::with('user')
                ->whereNotIn('id', $teachersWithSalary)
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'type' => 'teacher',
                    'type_label' => 'Guru',
                    'identifier' => $t->nip,
                    'name' => $t->user?->full_name ?? $t->full_name,
                    'email' => $t->user?->email ?? $t->email,
                    'employment_status' => $t->employment_status,
                ]);

            $employees = array_merge($employees, $teachers->toArray());
        }

        if ($type === 'all' || $type === 'staff') {
            $staffWithSalary = EmployeeSalary::where('employee_type', EmployeeSalary::TYPE_STAFF)
                ->current()
                ->pluck('employee_id');

            $staff = Staff::with('user')
                ->whereNotIn('id', $staffWithSalary)
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'type' => 'staff',
                    'type_label' => 'Staf',
                    'identifier' => $s->employee_id,
                    'name' => $s->user?->full_name ?? 'Staf',
                    'email' => $s->user?->email ?? null,
                    'employment_status' => $s->employment_status,
                ]);

            $employees = array_merge($employees, $staff->toArray());
        }

        return $this->success(['data' => $employees]);
    }

    /**
     * Manage salary components for an employee salary.
     */
    public function syncComponents(Request $request, EmployeeSalary $employeeSalary): JsonResponse
    {
        $data = $request->validate([
            'components' => ['required', 'array'],
            'components.*.salary_component_id' => ['required', 'uuid', 'exists:salary_components,id'],
            'components.*.value' => ['required', 'numeric', 'min:0'],
            'components.*.is_active' => ['boolean'],
        ]);

        try {
            DB::transaction(function () use ($data, $employeeSalary) {
                // Delete existing components
                $employeeSalary->components()->delete();

                // Create new components
                foreach ($data['components'] as $component) {
                    $employeeSalary->components()->create([
                        'salary_component_id' => $component['salary_component_id'],
                        'value' => $component['value'],
                        'is_active' => $component['is_active'] ?? true,
                    ]);
                }
            });

            $employeeSalary->load('components.salaryComponent');

            return $this->success(
                new EmployeeSalaryResource($employeeSalary),
                'Komponen gaji berhasil diperbarui'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal memperbarui komponen gaji: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get salary history for an employee salary.
     */
    public function history(EmployeeSalary $employeeSalary): JsonResponse
    {
        $histories = $employeeSalary->histories()
            ->with(['changedBy', 'oldGrade', 'newGrade'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($h) => [
                'id' => $h->id,
                'change_type' => $h->change_type,
                'change_type_label' => $h->getChangeTypeLabel(),
                'old_grade' => $h->oldGrade ? [
                    'id' => $h->oldGrade->id,
                    'code' => $h->oldGrade->code,
                    'name' => $h->oldGrade->name,
                ] : null,
                'new_grade' => $h->newGrade ? [
                    'id' => $h->newGrade->id,
                    'code' => $h->newGrade->code,
                    'name' => $h->newGrade->name,
                ] : null,
                'old_base_salary' => $h->old_base_salary ? (float) $h->old_base_salary : null,
                'old_base_salary_formatted' => $h->old_base_salary ? 'Rp ' . number_format($h->old_base_salary, 0, ',', '.') : null,
                'new_base_salary' => (float) $h->new_base_salary,
                'new_base_salary_formatted' => 'Rp ' . number_format($h->new_base_salary, 0, ',', '.'),
                'salary_difference' => $h->salary_difference,
                'salary_difference_formatted' => ($h->salary_difference >= 0 ? '+' : '') . 'Rp ' . number_format($h->salary_difference, 0, ',', '.'),
                'percentage_change' => $h->percentage_change,
                'effective_date' => $h->effective_date?->format('Y-m-d'),
                'reason' => $h->reason,
                'changed_by' => $h->changedBy ? [
                    'id' => $h->changedBy->id,
                    'name' => $h->changedBy->full_name,
                ] : null,
                'created_at' => $h->created_at?->toISOString(),
            ]);

        return $this->success(['data' => $histories]);
    }

    /**
     * Get salary summary/statistics.
     */
    public function summary(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $totalEmployees = EmployeeSalary::where('tenant_id', $tenantId)->current()->count();
        $totalTeachers = EmployeeSalary::where('tenant_id', $tenantId)->current()->teachers()->count();
        $totalStaff = EmployeeSalary::where('tenant_id', $tenantId)->current()->staff()->count();

        $totalBaseSalary = EmployeeSalary::where('tenant_id', $tenantId)->current()->sum('base_salary');
        $avgBaseSalary = EmployeeSalary::where('tenant_id', $tenantId)->current()->avg('base_salary') ?? 0;

        // Salary distribution by grade
        $byGrade = EmployeeSalary::where('employee_salaries.tenant_id', $tenantId)
            ->current()
            ->join('salary_grades', 'employee_salaries.salary_grade_id', '=', 'salary_grades.id')
            ->select('salary_grades.name', DB::raw('count(*) as count'), DB::raw('sum(employee_salaries.base_salary) as total'))
            ->groupBy('salary_grades.name')
            ->get();

        return $this->success([
            'total_employees' => $totalEmployees,
            'total_teachers' => $totalTeachers,
            'total_staff' => $totalStaff,
            'total_base_salary' => (float) $totalBaseSalary,
            'total_base_salary_formatted' => 'Rp ' . number_format($totalBaseSalary, 0, ',', '.'),
            'average_base_salary' => (float) $avgBaseSalary,
            'average_base_salary_formatted' => 'Rp ' . number_format($avgBaseSalary, 0, ',', '.'),
            'by_grade' => $byGrade->map(fn($g) => [
                'grade' => $g->name,
                'count' => $g->count,
                'total' => (float) $g->total,
                'total_formatted' => 'Rp ' . number_format($g->total, 0, ',', '.'),
            ]),
        ]);
    }

    /**
     * Get PTKP status options.
     */
    public function ptkpStatuses(): JsonResponse
    {
        // PTKP (Penghasilan Tidak Kena Pajak) status options
        $statuses = [
            ['code' => 'TK/0', 'label' => 'Tidak Kawin tanpa tanggungan'],
            ['code' => 'TK/1', 'label' => 'Tidak Kawin dengan 1 tanggungan'],
            ['code' => 'TK/2', 'label' => 'Tidak Kawin dengan 2 tanggungan'],
            ['code' => 'TK/3', 'label' => 'Tidak Kawin dengan 3 tanggungan'],
            ['code' => 'K/0', 'label' => 'Kawin tanpa tanggungan'],
            ['code' => 'K/1', 'label' => 'Kawin dengan 1 tanggungan'],
            ['code' => 'K/2', 'label' => 'Kawin dengan 2 tanggungan'],
            ['code' => 'K/3', 'label' => 'Kawin dengan 3 tanggungan'],
            ['code' => 'K/I/0', 'label' => 'Kawin, istri bekerja, tanpa tanggungan'],
            ['code' => 'K/I/1', 'label' => 'Kawin, istri bekerja, dengan 1 tanggungan'],
            ['code' => 'K/I/2', 'label' => 'Kawin, istri bekerja, dengan 2 tanggungan'],
            ['code' => 'K/I/3', 'label' => 'Kawin, istri bekerja, dengan 3 tanggungan'],
        ];

        return $this->success(['data' => $statuses]);
    }

    /**
     * Validate employee exists based on type and ID.
     */
    protected function validateEmployeeExists(string $type, string $id): bool
    {
        return match ($type) {
            EmployeeSalary::TYPE_TEACHER => Teacher::where('id', $id)->exists(),
            EmployeeSalary::TYPE_STAFF => Staff::where('id', $id)->exists(),
            default => false,
        };
    }
}
