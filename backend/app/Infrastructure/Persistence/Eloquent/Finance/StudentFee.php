<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentFee extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'student_fees';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'fee_structure_id',
        'academic_year_id',
        'month',
        'year',
        'amount',
        'discount',
        'fine',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'fine' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    // Constants for status
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_WAIVED = 'waived';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_UNPAID => 'Belum Dibayar',
            self::STATUS_PARTIAL => 'Dibayar Sebagian',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_OVERDUE => 'Jatuh Tempo',
            self::STATUS_WAIVED => 'Dibebaskan',
        ];
    }

    // Relationships

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function paymentItems(): HasMany
    {
        return $this->hasMany(PaymentItem::class, 'student_fee_id');
    }

    // Scopes

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeForStudent(Builder $query, string $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForAcademicYear(Builder $query, string $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForPeriod(Builder $query, int $month, int $year): Builder
    {
        return $query->where('month', $month)->where('year', $year);
    }

    public function scopeDueBefore(Builder $query, string $date): Builder
    {
        return $query->where('due_date', '<=', $date);
    }

    // Helpers

    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Check if the fee is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now()->toDateString()
            && in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }

    /**
     * Recalculate totals after payment.
     */
    public function recalculateTotals(): void
    {
        $paidAmount = $this->paymentItems()
            ->whereHas('payment', fn($q) => $q->where('status', Payment::STATUS_COMPLETED))
            ->sum('amount');

        $this->paid_amount = $paidAmount;
        $this->remaining_amount = $this->total_amount - $paidAmount;

        if ($this->remaining_amount <= 0) {
            $this->status = self::STATUS_PAID;
        } elseif ($paidAmount > 0) {
            $this->status = self::STATUS_PARTIAL;
        }

        $this->save();
    }
}
