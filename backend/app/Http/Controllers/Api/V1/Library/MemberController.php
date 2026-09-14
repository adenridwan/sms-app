<?php

namespace App\Http\Controllers\Api\V1\Library;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\LibraryMemberResource;
use App\Infrastructure\Persistence\Eloquent\Library\LibraryMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends ApiController
{
    /**
     * List library members.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LibraryMember::query()
            ->with(['user'])
            ->withCount('activeLoans')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('member_number', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn($u) => $u
                            ->where('email', 'ilike', "%{$search}%")
                            ->orWhere('username', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->member_type, fn($q, $type) => $q->where('member_type', $type))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderBy('member_number');

        $perPage = $request->get('per_page', 15);
        $members = $query->paginate($perPage);

        return $this->collection(LibraryMemberResource::collection($members));
    }

    /**
     * Get a single member.
     */
    public function show(LibraryMember $member): JsonResponse
    {
        $member->load(['user', 'activeLoans.bookCopy.book']);
        $member->loadCount('activeLoans');

        return $this->success(new LibraryMemberResource($member));
    }

    /**
     * Create a new member.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id', Rule::unique('library_members')],
            'member_type' => ['required', Rule::in(['student', 'teacher', 'staff', 'external'])],
            'max_borrow_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        // Generate member number
        $lastMember = LibraryMember::where('tenant_id', $tenantId)
            ->orderByDesc('member_number')
            ->first();

        $nextNumber = 1;
        if ($lastMember && preg_match('/LIB-(\d+)/', $lastMember->member_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }

        $memberNumber = 'LIB-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        $member = LibraryMember::create([
            'tenant_id' => $tenantId,
            'user_id' => $data['user_id'],
            'member_number' => $memberNumber,
            'member_type' => $data['member_type'],
            'registered_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
            'max_borrow_limit' => $data['max_borrow_limit'] ?? 3,
            'current_borrowed' => 0,
            'status' => 'active',
        ]);

        $member->load('user');

        return $this->success(
            new LibraryMemberResource($member),
            'Anggota berhasil ditambahkan',
            201
        );
    }

    /**
     * Update a member.
     */
    public function update(Request $request, LibraryMember $member): JsonResponse
    {
        $data = $request->validate([
            'member_type' => ['sometimes', Rule::in(['student', 'teacher', 'staff', 'external'])],
            'max_borrow_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended', 'expired'])],
        ]);

        $member->update($data);
        $member->load('user');

        return $this->success(
            new LibraryMemberResource($member),
            'Anggota berhasil diperbarui'
        );
    }

    /**
     * Delete a member.
     */
    public function destroy(LibraryMember $member): JsonResponse
    {
        // Check if member has active loans
        if ($member->activeLoans()->exists()) {
            return $this->error('Anggota tidak dapat dihapus karena masih memiliki peminjaman aktif', 422);
        }

        $member->delete();

        return $this->success(null, 'Anggota berhasil dihapus');
    }

    /**
     * Get member's loan history.
     */
    public function loans(LibraryMember $member): JsonResponse
    {
        $loans = $member->loans()
            ->with(['bookCopy.book'])
            ->orderByDesc('borrow_date')
            ->get()
            ->map(fn($loan) => [
                'id' => $loan->id,
                'book_title' => $loan->bookCopy->book->title,
                'copy_number' => $loan->bookCopy->copy_number,
                'borrow_date' => $loan->borrow_date->toDateString(),
                'due_date' => $loan->due_date->toDateString(),
                'return_date' => $loan->return_date?->toDateString(),
                'status' => $loan->status,
                'status_label' => $loan->status_label,
                'fine_amount' => $loan->fine_amount,
            ]);

        return $this->success($loans);
    }

    /**
     * Get available users (not yet members).
     */
    public function availableUsers(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $existingUserIds = LibraryMember::pluck('user_id')->toArray();

        $users = User::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereNotIn('id', $existingUserIds)
            ->where('status', 'active')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('email', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('email')
            ->limit(50)
            ->get(['id', 'email', 'username']);

        $users = $users->map(fn($u) => [
            'id' => $u->id,
            'email' => $u->email,
            'full_name' => $u->full_name,
        ]);

        return $this->success($users);
    }
}
