<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmployeeSalary extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'employee_salaries';

    protected $fillable = [
        'tenant_id',
        'employee_type',
        'employee_id',
        'salary_grade_id',
        'base_salary',
        'ptkp_status',
        'effective_date',
        'end_date',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'effective_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    // Constants for employee_type
    public const TYPE_TEACHER = 'teacher';
    public const TYPE_STAFF = 'staff';

    public static function getEmployeeTypes(): array
    {
        return [
            self::TYPE_TEACHER => 'Guru',
            self::TYPE_STAFF => 'Staf',
        ];
    }

    // Relationships

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class, 'salary_grade_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'employee_salary_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SalaryHistory::class, 'employee_salary_id');
    }

    /**
     * Get the employee (teacher or staff) based on employee_type.
     */
    public function employee(): MorphTo
    {
        return $this->morphTo('employee', 'employee_type', 'employee_id');
    }

    /**
     * Get teacher if employee_type is teacher.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'employee_id');
    }

    /**
     * Get staff if employee_type is staff.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'employee_id');
    }

    // Scopes

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeForEmployee(Builder $query, string $employeeType, string $employeeId): Builder
    {
        return $query->where('employee_type', $employeeType)
            ->where('employee_id', $employeeId);
    }

    public function scopeTeachers(Builder $query): Builder
    {
        return $query->where('employee_type', self::TYPE_TEACHER);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('employee_type', self::TYPE_STAFF);
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    // Helpers

    public function getEmployeeTypeLabel(): string
    {
        return self::getEmployeeTypes()[$this->employee_type] ?? $this->employee_type;
    }

    /**
     * Get the actual employee model (Teacher or Staff).
     */
    public function getEmployee(): Model|null
    {
        return match ($this->employee_type) {
            self::TYPE_TEACHER => $this->teacher,
            self::TYPE_STAFF => $this->staff,
            default => null,
        };
    }

    /**
     * Get active components with their salary component details.
     */
    public function getActiveComponents(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->components()
            ->with('salaryComponent')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Calculate total earnings from components.
     */
    public function calculateTotalEarnings(): float
    {
        return $this->components()
            ->whereHas('salaryComponent', fn($q) => $q->where('type', SalaryComponent::TYPE_EARNING))
            ->where('is_active', true)
            ->sum('value');
    }

    /**
     * Calculate total deductions from components.
     */
    public function calculateTotalDeductions(): float
    {
        return $this->components()
            ->whereHas('salaryComponent', fn($q) => $q->where('type', SalaryComponent::TYPE_DEDUCTION))
            ->where('is_active', true)
            ->sum('value');
    }
}
