<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryHistory extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'salary_histories';

    protected $fillable = [
        'employee_salary_id',
        'changed_by',
        'change_type',
        'old_grade_id',
        'new_grade_id',
        'old_base_salary',
        'new_base_salary',
        'effective_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_base_salary' => 'decimal:2',
            'new_base_salary' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    // Constants for change_type
    public const TYPE_INITIAL = 'initial';
    public const TYPE_PROMOTION = 'promotion';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_DEMOTION = 'demotion';

    public static function getChangeTypes(): array
    {
        return [
            self::TYPE_INITIAL => 'Penetapan Awal',
            self::TYPE_PROMOTION => 'Kenaikan',
            self::TYPE_ADJUSTMENT => 'Penyesuaian',
            self::TYPE_DEMOTION => 'Penurunan',
        ];
    }

    // Relationships

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function oldGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class, 'old_grade_id');
    }

    public function newGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class, 'new_grade_id');
    }

    // Scopes

    public function scopeForEmployeeSalary(Builder $query, string $employeeSalaryId): Builder
    {
        return $query->where('employee_salary_id', $employeeSalaryId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('change_type', $type);
    }

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    // Helpers

    public function getChangeTypeLabel(): string
    {
        return self::getChangeTypes()[$this->change_type] ?? $this->change_type;
    }

    /**
     * Get the salary difference (new - old).
     */
    public function getSalaryDifferenceAttribute(): float
    {
        $old = (float) ($this->old_base_salary ?? 0);
        $new = (float) $this->new_base_salary;

        return $new - $old;
    }

    /**
     * Get percentage change.
     */
    public function getPercentageChangeAttribute(): float
    {
        $old = (float) ($this->old_base_salary ?? 0);

        if ($old == 0) {
            return 0;
        }

        return round((($this->salary_difference / $old) * 100), 2);
    }
}
