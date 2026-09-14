<?php

namespace App\Http\Controllers\Api\V1\Library;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookLoanResource;
use App\Infrastructure\Persistence\Eloquent\Library\Book;
use App\Infrastructure\Persistence\Eloquent\Library\BookCopy;
use App\Infrastructure\Persistence\Eloquent\Library\BookLoan;
use App\Infrastructure\Persistence\Eloquent\Library\LibraryMember;
use App\Infrastructure\Persistence\Eloquent\Library\LibrarySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LoanController extends ApiController
{
    /**
     * List loans.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BookLoan::query()
            ->with(['member.user', 'bookCopy.book', 'issuedByUser', 'returnedToUser'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('member.user', fn($u) => $u->where('email', 'ilike', "%{$search}%"))
                    ->orWhereHas('bookCopy.book', fn($b) => $b->where('title', 'ilike', "%{$search}%"))
                    ->orWhereHas('bookCopy', fn($c) => $c->where('copy_number', 'ilike', "%{$search}%"));
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->member_id, fn($q, $id) => $q->where('library_member_id', $id))
            ->when($request->overdue === 'true', fn($q) => $q->where('status', 'borrowed')->where('due_date', '<', now()))
            ->orderByDesc('created_at');

        $perPage = $request->get('per_page', 15);
        $loans = $query->paginate($perPage);

        return $this->collection(BookLoanResource::collection($loans));
    }

    /**
     * Get a single loan.
     */
    public function show(BookLoan $loan): JsonResponse
    {
        $loan->load(['member.user', 'bookCopy.book', 'issuedByUser', 'returnedToUser']);

        return $this->success(new BookLoanResource($loan));
    }

    /**
     * Create a new loan (borrow book).
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $data = $request->validate([
            'library_member_id' => ['required', 'uuid', 'exists:library_members,id'],
            'book_copy_id' => ['required', 'uuid', 'exists:book_copies,id'],
            'notes' => ['nullable', 'string'],
        ]);

        // Get member
        $member = LibraryMember::find($data['library_member_id']);
        if (!$member) {
            return $this->error('Anggota tidak ditemukan', 404);
        }

        // Check if member can borrow
        if (!$member->canBorrow()) {
            return $this->error('Anggota tidak dapat meminjam buku (limit tercapai atau status tidak aktif)', 422);
        }

        // Get book copy
        $bookCopy = BookCopy::find($data['book_copy_id']);
        if (!$bookCopy) {
            return $this->error('Eksemplar buku tidak ditemukan', 404);
        }

        // Check if copy is available
        if (!$bookCopy->isAvailable()) {
            return $this->error('Eksemplar buku tidak tersedia', 422);
        }

        // Get settings
        $settings = LibrarySetting::getOrCreate($tenantId);

        try {
            DB::beginTransaction();

            // Create loan
            $loan = BookLoan::create([
                'tenant_id' => $tenantId,
                'library_member_id' => $member->id,
                'book_copy_id' => $bookCopy->id,
                'borrow_date' => now(),
                'due_date' => now()->addDays($settings->default_loan_days),
                'status' => 'borrowed',
                'condition_on_borrow' => $bookCopy->condition,
                'issued_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Update book copy status
            $bookCopy->update(['status' => 'borrowed']);

            // Update member's borrowed count
            $member->updateBorrowedCount();

            // Update book's available copies
            $bookCopy->book->updateAvailableCopies();

            DB::commit();

            $loan->load(['member.user', 'bookCopy.book', 'issuedByUser']);

            return $this->success(
                new BookLoanResource($loan),
                'Peminjaman berhasil dibuat',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal membuat peminjaman: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return a book.
     */
    public function returnBook(Request $request, BookLoan $loan): JsonResponse
    {
        if ($loan->status === 'returned') {
            return $this->error('Buku sudah dikembalikan', 422);
        }

        $data = $request->validate([
            'condition_on_return' => ['nullable', Rule::in(['good', 'fair', 'poor', 'damaged', 'lost'])],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            // Get settings for fine calculation
            $settings = LibrarySetting::getOrCreate($loan->tenant_id);

            // Calculate fine if overdue
            $fineAmount = 0;
            if ($loan->isOverdue()) {
                $fineAmount = $loan->calculateFine($settings->daily_fine, $settings->max_fine);
            }

            // Return the book
            $loan->return_date = now();
            $loan->returned_to = $request->user()->id;
            $loan->status = 'returned';
            $loan->condition_on_return = $data['condition_on_return'] ?? $loan->bookCopy->condition;
            $loan->fine_amount = $fineAmount;
            if ($data['notes'] ?? null) {
                $loan->notes = ($loan->notes ? $loan->notes . "\n" : '') . $data['notes'];
            }
            $loan->save();

            // Update book copy
            $condition = $data['condition_on_return'] ?? $loan->bookCopy->condition;
            $loan->bookCopy->update([
                'status' => 'available',
                'condition' => $condition,
            ]);

            // Update member's borrowed count
            $loan->member->updateBorrowedCount();

            // Update book's available copies
            $loan->bookCopy->book->updateAvailableCopies();

            DB::commit();

            $loan->load(['member.user', 'bookCopy.book', 'returnedToUser']);

            return $this->success(
                new BookLoanResource($loan),
                $fineAmount > 0 ? "Buku dikembalikan. Denda: Rp " . number_format($fineAmount, 0, ',', '.') : 'Buku berhasil dikembalikan'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal mengembalikan buku: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extend loan.
     */
    public function extend(Request $request, BookLoan $loan): JsonResponse
    {
        if ($loan->status !== 'borrowed') {
            return $this->error('Hanya peminjaman aktif yang dapat diperpanjang', 422);
        }

        // Get settings
        $settings = LibrarySetting::getOrCreate($loan->tenant_id);

        // Check extension limit
        if ($loan->extension_count >= $settings->max_extensions) {
            return $this->error('Batas perpanjangan telah tercapai', 422);
        }

        // Check if overdue
        if ($loan->isOverdue()) {
            return $this->error('Peminjaman yang terlambat tidak dapat diperpanjang', 422);
        }

        $loan->due_date = $loan->due_date->addDays($settings->extension_days);
        $loan->extension_count += 1;
        $loan->save();

        $loan->load(['member.user', 'bookCopy.book']);

        return $this->success(
            new BookLoanResource($loan),
            'Peminjaman berhasil diperpanjang'
        );
    }

    /**
     * Get loan statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $totalLoans = BookLoan::where('tenant_id', $tenantId)->count();
        $activeLoans = BookLoan::where('tenant_id', $tenantId)->where('status', 'borrowed')->count();
        $overdueLoans = BookLoan::where('tenant_id', $tenantId)
            ->where('status', 'borrowed')
            ->where('due_date', '<', now())
            ->count();
        $returnedToday = BookLoan::where('tenant_id', $tenantId)
            ->where('status', 'returned')
            ->whereDate('return_date', now())
            ->count();
        $totalFines = BookLoan::where('tenant_id', $tenantId)
            ->where('fine_paid', false)
            ->sum('fine_amount');

        return $this->success([
            'total_loans' => $totalLoans,
            'active_loans' => $activeLoans,
            'overdue_loans' => $overdueLoans,
            'returned_today' => $returnedToday,
            'unpaid_fines' => $totalFines,
        ]);
    }
}
