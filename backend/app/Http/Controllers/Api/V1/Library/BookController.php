<?php

namespace App\Http\Controllers\Api\V1\Library;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookResource;
use App\Infrastructure\Persistence\Eloquent\Library\Book;
use App\Infrastructure\Persistence\Eloquent\Library\BookCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends ApiController
{
    /**
     * List books.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()
            ->with(['category', 'shelf', 'publisher', 'authors'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'ilike', "%{$search}%")
                        ->orWhere('isbn', 'ilike', "%{$search}%");
                });
            })
            ->when($request->category_id, fn($q, $id) => $q->where('category_id', $id))
            ->when($request->shelf_id, fn($q, $id) => $q->where('shelf_id', $id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->available === 'true', fn($q) => $q->where('available_copies', '>', 0))
            ->orderBy('title');

        $perPage = $request->get('per_page', 15);
        $books = $query->paginate($perPage);

        return $this->collection(BookResource::collection($books));
    }

    /**
     * Get a single book.
     */
    public function show(Book $book): JsonResponse
    {
        $book->load(['category', 'shelf', 'publisher', 'authors', 'copies']);

        return $this->success(new BookResource($book));
    }

    /**
     * Create a new book.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'category_id' => ['nullable', 'uuid', 'exists:book_categories,id'],
            'shelf_id' => ['nullable', 'uuid', 'exists:book_shelves,id'],
            'publisher_id' => ['nullable', 'uuid', 'exists:publishers,id'],
            'edition' => ['nullable', 'string', 'max:50'],
            'publish_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'language' => ['nullable', 'string', 'max:50'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'total_copies' => ['nullable', 'integer', 'min:1'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'author_ids' => ['nullable', 'array'],
            'author_ids.*' => ['uuid', 'exists:authors,id'],
        ]);

        try {
            DB::beginTransaction();

            // Handle cover upload
            $coverPath = null;
            if ($request->hasFile('cover')) {
                $coverPath = $request->file('cover')->store('library/covers', 'public');
            }

            $totalCopies = $data['total_copies'] ?? 1;

            $book = Book::create([
                'tenant_id' => $tenantId,
                'title' => $data['title'],
                'isbn' => $data['isbn'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'shelf_id' => $data['shelf_id'] ?? null,
                'publisher_id' => $data['publisher_id'] ?? null,
                'edition' => $data['edition'] ?? null,
                'publish_year' => $data['publish_year'] ?? null,
                'language' => $data['language'] ?? 'Indonesia',
                'pages' => $data['pages'] ?? null,
                'description' => $data['description'] ?? null,
                'price' => $data['price'] ?? null,
                'cover_image' => $coverPath,
                'total_copies' => $totalCopies,
                'available_copies' => $totalCopies,
                'status' => 'available',
            ]);

            // Create book copies
            for ($i = 1; $i <= $totalCopies; $i++) {
                BookCopy::create([
                    'tenant_id' => $tenantId,
                    'book_id' => $book->id,
                    'copy_number' => $book->id . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'condition' => 'good',
                    'status' => 'available',
                    'acquisition_date' => now(),
                ]);
            }

            // Attach authors
            if (!empty($data['author_ids'])) {
                $book->authors()->sync($data['author_ids']);
            }

            DB::commit();

            $book->load(['category', 'shelf', 'publisher', 'authors']);

            return $this->success(
                new BookResource($book),
                'Buku berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($coverPath)) {
                Storage::disk('public')->delete($coverPath);
            }

            return $this->error('Gagal menambahkan buku: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a book.
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'category_id' => ['nullable', 'uuid', 'exists:book_categories,id'],
            'shelf_id' => ['nullable', 'uuid', 'exists:book_shelves,id'],
            'publisher_id' => ['nullable', 'uuid', 'exists:publishers,id'],
            'edition' => ['nullable', 'string', 'max:50'],
            'publish_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'language' => ['nullable', 'string', 'max:50'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['available', 'unavailable', 'damaged', 'lost'])],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'author_ids' => ['nullable', 'array'],
            'author_ids.*' => ['uuid', 'exists:authors,id'],
        ]);

        try {
            // Handle cover upload
            if ($request->hasFile('cover')) {
                // Delete old cover
                if ($book->cover_image) {
                    Storage::disk('public')->delete($book->cover_image);
                }
                $data['cover_image'] = $request->file('cover')->store('library/covers', 'public');
            }

            // Remove author_ids from data (handled separately)
            $authorIds = $data['author_ids'] ?? null;
            unset($data['author_ids']);
            unset($data['cover']);

            $book->update($data);

            // Sync authors
            if ($authorIds !== null) {
                $book->authors()->sync($authorIds);
            }

            $book->load(['category', 'shelf', 'publisher', 'authors']);

            return $this->success(
                new BookResource($book),
                'Buku berhasil diperbarui'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal memperbarui buku: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a book.
     */
    public function destroy(Book $book): JsonResponse
    {
        // Check if any copies are borrowed
        $borrowedCopies = $book->copies()->where('status', 'borrowed')->exists();
        if ($borrowedCopies) {
            return $this->error('Buku tidak dapat dihapus karena ada eksemplar yang sedang dipinjam', 422);
        }

        try {
            // Delete cover
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }

            // Delete copies first
            $book->copies()->delete();

            // Delete book
            $book->delete();

            return $this->success(null, 'Buku berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus buku: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get book copies.
     */
    public function copies(Book $book): JsonResponse
    {
        $copies = $book->copies()->with('loans')->get()->map(fn($copy) => [
            'id' => $copy->id,
            'copy_number' => $copy->copy_number,
            'barcode' => $copy->barcode,
            'condition' => $copy->condition,
            'condition_label' => $copy->condition_label,
            'status' => $copy->status,
            'status_label' => $copy->status_label,
            'acquisition_date' => $copy->acquisition_date?->toDateString(),
            'acquisition_source' => $copy->acquisition_source,
            'notes' => $copy->notes,
        ]);

        return $this->success($copies);
    }
}
