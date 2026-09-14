<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookLoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'borrow_date' => $this->borrow_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'return_date' => $this->return_date?->toDateString(),
            'extension_count' => $this->extension_count,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'condition_on_borrow' => $this->condition_on_borrow,
            'condition_on_return' => $this->condition_on_return,
            'fine_amount' => $this->fine_amount,
            'fine_paid' => $this->fine_paid,
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->days_overdue,
            'notes' => $this->notes,
            'member' => $this->whenLoaded('member', fn() => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'member_type' => $this->member->member_type,
                'user' => $this->member->user ? [
                    'id' => $this->member->user->id,
                    'full_name' => $this->member->user->full_name,
                    'email' => $this->member->user->email,
                ] : null,
            ]),
            'book_copy' => $this->whenLoaded('bookCopy', fn() => [
                'id' => $this->bookCopy->id,
                'copy_number' => $this->bookCopy->copy_number,
                'barcode' => $this->bookCopy->barcode,
                'condition' => $this->bookCopy->condition,
                'book' => $this->bookCopy->book ? [
                    'id' => $this->bookCopy->book->id,
                    'title' => $this->bookCopy->book->title,
                    'isbn' => $this->bookCopy->book->isbn,
                    'cover_url' => $this->bookCopy->book->cover_url,
                ] : null,
            ]),
            'issued_by' => $this->whenLoaded('issuedByUser', fn() => [
                'id' => $this->issuedByUser->id,
                'full_name' => $this->issuedByUser->full_name,
            ]),
            'returned_to' => $this->whenLoaded('returnedToUser', fn() => [
                'id' => $this->returnedToUser->id,
                'full_name' => $this->returnedToUser->full_name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
