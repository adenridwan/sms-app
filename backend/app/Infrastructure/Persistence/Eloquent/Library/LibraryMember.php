<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryMember extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'library_members';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'member_number',
        'member_type',
        'registered_at',
        'expires_at',
        'max_borrow_limit',
        'current_borrowed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'expires_at' => 'date',
            'max_borrow_limit' => 'integer',
            'current_borrowed' => 'integer',
        ];
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get loans.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class, 'library_member_id');
    }

    /**
     * Get active loans.
     */
    public function activeLoans(): HasMany
    {
        return $this->loans()->whereIn('status', ['borrowed', 'overdue']);
    }

    /**
     * Get reservations.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class, 'library_member_id');
    }

    /**
     * Get member type label.
     */
    public function getMemberTypeLabelAttribute(): string
    {
        return match ($this->member_type) {
            'student' => 'Siswa',
            'teacher' => 'Guru',
            'staff' => 'Staff',
            'external' => 'Eksternal',
            default => ucfirst($this->member_type ?? ''),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'suspended' => 'Ditangguhkan',
            'expired' => 'Kadaluarsa',
            default => ucfirst($this->status ?? ''),
        };
    }

    /**
     * Check if member can borrow.
     */
    public function canBorrow(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->current_borrowed < $this->max_borrow_limit;
    }

    /**
     * Update borrowed count.
     */
    public function updateBorrowedCount(): void
    {
        $this->current_borrowed = $this->activeLoans()->count();
        $this->save();
    }
}
