<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollPeriod extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'tenant_id',
        'name',
        'year',
        'month',
        'start_date',
        'end_date',
        'payment_date',
        'status',
        'approved_by',
        'approved_at',
        'finalized_by',
        'finalized_at',
        'notes',
        'total_gross',
        'total_deductions',
        'total_net',
        'employee_count',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
        ];
    }

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_FINALIZED = 'finalized';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draf',
            self::STATUS_PROCESSING => 'Sedang Diproses',
            self::STATUS_PENDING_APPROVAL => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_PAID => 'Dibayar',
            self::STATUS_FINALIZED => 'Final',
        ];
    }

    // Relationships
    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class, 'payroll_period_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    // Scopes
    public function scopeYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeMonth(Builder $query, int $month): Builder
    {
        return $query->where('month', $month);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    // Helpers
    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getPeriodLabel(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return ($months[$this->month] ?? $this->month) . ' ' . $this->year;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PROCESSING]);
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function canGenerateSlips(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function canFinalize(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    /**
     * Recalculate totals from slips.
     */
    public function recalculateTotals(): void
    {
        $totals = $this->slips()
            ->selectRaw('SUM(gross_salary) as total_gross')
            ->selectRaw('SUM(total_deductions) as total_deductions')
            ->selectRaw('SUM(net_salary) as total_net')
            ->selectRaw('COUNT(*) as employee_count')
            ->first();

        $this->update([
            'total_gross' => $totals->total_gross ?? 0,
            'total_deductions' => $totals->total_deductions ?? 0,
            'total_net' => $totals->total_net ?? 0,
            'employee_count' => $totals->employee_count ?? 0,
        ]);
    }
}
