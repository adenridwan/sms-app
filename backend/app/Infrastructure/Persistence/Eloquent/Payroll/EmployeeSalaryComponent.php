<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryComponent extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'employee_salary_components';

    protected $fillable = [
        'employee_salary_id',
        'salary_component_id',
        'value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->whereHas('salaryComponent', function ($q) {
            $q->where('type', SalaryComponent::TYPE_EARNING);
        });
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->whereHas('salaryComponent', function ($q) {
            $q->where('type', SalaryComponent::TYPE_DEDUCTION);
        });
    }
}
