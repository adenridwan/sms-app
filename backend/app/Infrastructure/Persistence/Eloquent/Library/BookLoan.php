<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookLoan extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'book_loans';

    protected $fillable = [
        'tenant_id',
        'library_member_id',
        'book_copy_id',
        'borrow_date',
        'due_date',
        'return_date',
        'extension_count',
        'status',
        'condition_on_borrow',
        'condition_on_return',
        'fine_amount',
        'fine_paid',
        'issued_by',
        'returned_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'extension_count' => 'integer',
            'fine_amount' => 'decimal:2',
            'fine_paid' => 'boolean',
        ];
    }

    /**
     * Get the member.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(LibraryMember::class, 'library_member_id');
    }

    /**
     * Get the book copy.
     */
    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    /**
     * Get the user who issued the loan.
     */
    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the user who received the return.
     */
    public function returnedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'borrowed' => 'Dipinjam',
            'returned' => 'Dikembalikan',
            'overdue' => 'Terlambat',
            'lost' => 'Hilang',
            default => ucfirst($this->status ?? ''),
        };
    }

    /**
     * Check if loan is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'returned') {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $returnDate = $this->return_date ?? now();
        return $this->due_date->diffInDays($returnDate);
    }

    /**
     * Calculate fine amount.
     */
    public function calculateFine(float $dailyFine, float $maxFine): float
    {
        $daysOverdue = $this->days_overdue;
        if ($daysOverdue <= 0) {
            return 0;
        }

        $fine = $daysOverdue * $dailyFine;
        return min($fine, $maxFine);
    }

    /**
     * Return the book.
     */
    public function returnBook(string $userId, ?string $condition = null): void
    {
        $this->return_date = now();
        $this->returned_to = $userId;
        $this->status = 'returned';

        if ($condition) {
            $this->condition_on_return = $condition;
        }

        $this->save();

        // Update book copy status
        $this->bookCopy->update(['status' => 'available']);

        // Update member's borrowed count
        $this->member->updateBorrowedCount();

        // Update book's available copies
        $this->bookCopy->book->updateAvailableCopies();
    }
}
