<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollSlip extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'payroll_slips';

    protected $fillable = [
        'payroll_period_id',
        'employee_salary_id',
        'employee_type',
        'employee_id',
        'employee_name',
        'employee_identifier',
        'salary_grade_code',
        'ptkp_status',
        'base_salary',
        'total_allowances',
        'total_overtime',
        'total_other_income',
        'gross_salary',
        'bpjs_kesehatan',
        'bpjs_jht',
        'bpjs_jp',
        'pph21',
        'total_other_deductions',
        'total_deductions',
        'net_salary',
        'working_days',
        'days_present',
        'days_absent',
        'days_late',
        'days_leave',
        'attendance_deduction',
        'status',
        'calculated_by',
        'calculated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_overtime' => 'decimal:2',
            'total_other_income' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'bpjs_kesehatan' => 'decimal:2',
            'bpjs_jht' => 'decimal:2',
            'bpjs_jp' => 'decimal:2',
            'pph21' => 'decimal:2',
            'total_other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'attendance_deduction' => 'decimal:2',
            'working_days' => 'integer',
            'days_present' => 'integer',
            'days_absent' => 'integer',
            'days_late' => 'integer',
            'days_leave' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';

    // Employee type constants
    public const TYPE_TEACHER = 'teacher';
    public const TYPE_STAFF = 'staff';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draf',
            self::STATUS_CALCULATED => 'Dihitung',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_PAID => 'Dibayar',
        ];
    }

    public static function getEmployeeTypes(): array
    {
        return [
            self::TYPE_TEACHER => 'Guru',
            self::TYPE_STAFF => 'Staf',
        ];
    }

    // Relationships
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollSlipItem::class, 'payroll_slip_id');
    }

    public function calculatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'employee_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'employee_id');
    }

    // Scopes
    public function scopeForPeriod(Builder $query, string $periodId): Builder
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeTeachers(Builder $query): Builder
    {
        return $query->where('employee_type', self::TYPE_TEACHER);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('employee_type', self::TYPE_STAFF);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // Helpers
    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getEmployeeTypeLabel(): string
    {
        return self::getEmployeeTypes()[$this->employee_type] ?? $this->employee_type;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CALCULATED]);
    }

    /**
     * Calculate totals from items.
     */
    public function calculateFromItems(): void
    {
        $earnings = $this->items()->where('type', 'earning')->sum('amount');
        $deductions = $this->items()->where('type', 'deduction')->sum('amount');

        // Categorize earnings
        $allowances = $this->items()
            ->where('type', 'earning')
            ->whereIn('category', ['fixed', 'variable'])
            ->where('component_code', '!=', 'BASE_SALARY')
            ->sum('amount');

        $overtime = $this->items()
            ->where('type', 'earning')
            ->where('category', 'variable')
            ->where('component_code', 'like', '%LEMBUR%')
            ->sum('amount');

        $otherIncome = $this->items()
            ->where('type', 'earning')
            ->where('category', 'other')
            ->sum('amount');

        // BPJS deductions
        $bpjsKesehatan = $this->items()
            ->where('type', 'deduction')
            ->where('category', 'bpjs')
            ->where('component_code', 'BPJS_KES')
            ->sum('amount');

        $bpjsJht = $this->items()
            ->where('type', 'deduction')
            ->where('category', 'bpjs')
            ->where('component_code', 'BPJS_JHT')
            ->sum('amount');

        $bpjsJp = $this->items()
            ->where('type', 'deduction')
            ->where('category', 'bpjs')
            ->where('component_code', 'BPJS_JP')
            ->sum('amount');

        // PPh 21
        $pph21 = $this->items()
            ->where('type', 'deduction')
            ->where('category', 'tax')
            ->sum('amount');

        // Other deductions
        $otherDeductions = $this->items()
            ->where('type', 'deduction')
            ->whereNotIn('category', ['bpjs', 'tax'])
            ->sum('amount');

        $this->update([
            'total_allowances' => $allowances,
            'total_overtime' => $overtime,
            'total_other_income' => $otherIncome,
            'gross_salary' => $earnings,
            'bpjs_kesehatan' => $bpjsKesehatan,
            'bpjs_jht' => $bpjsJht,
            'bpjs_jp' => $bpjsJp,
            'pph21' => $pph21,
            'total_other_deductions' => $otherDeductions,
            'total_deductions' => $deductions,
            'net_salary' => $earnings - $deductions,
            'status' => self::STATUS_CALCULATED,
            'calculated_at' => now(),
            'calculated_by' => auth()->id(),
        ]);
    }

    /**
     * Get the actual employee model.
     */
    public function getEmployee(): ?Model
    {
        return match ($this->employee_type) {
            self::TYPE_TEACHER => $this->teacher,
            self::TYPE_STAFF => $this->staff,
            default => null,
        };
    }
}
