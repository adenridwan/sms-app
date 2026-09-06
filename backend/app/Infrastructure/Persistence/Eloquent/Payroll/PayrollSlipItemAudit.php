<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlipItemAudit extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'payroll_slip_item_audits';

    protected $fillable = [
        'payroll_slip_item_id',
        'payroll_slip_id',
        'changed_by',
        'field_name',
        'old_value',
        'new_value',
        'reason',
    ];

    // Relationships
    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollSlipItem::class, 'payroll_slip_item_id');
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class, 'payroll_slip_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Helpers
    public function getFieldLabel(): string
    {
        return match ($this->field_name) {
            'quantity' => 'Jumlah/Hari',
            'rate' => 'Tarif',
            'amount' => 'Nominal',
            'notes' => 'Catatan',
            'is_taxable' => 'Kena Pajak',
            default => $this->field_name,
        };
    }

    public function getFormattedOldValue(): string
    {
        return $this->formatValue($this->field_name, $this->old_value);
    }

    public function getFormattedNewValue(): string
    {
        return $this->formatValue($this->field_name, $this->new_value);
    }

    protected function formatValue(string $field, ?string $value): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($field) {
            'quantity' => number_format((float) $value, 2, ',', '.'),
            'rate', 'amount' => 'Rp ' . number_format((float) $value, 0, ',', '.'),
            'is_taxable' => $value ? 'Ya' : 'Tidak',
            default => $value,
        };
    }
}
