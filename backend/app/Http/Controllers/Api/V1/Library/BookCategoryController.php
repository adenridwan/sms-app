<?php

namespace App\Http\Controllers\Api\V1\Library;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookCategoryResource;
use App\Infrastructure\Persistence\Eloquent\Library\BookCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookCategoryController extends ApiController
{
    /**
     * List book categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BookCategory::query()
            ->withCount('books')
            ->with('parent')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->when($request->parent_id === 'null', fn($q) => $q->whereNull('parent_id'))
            ->when($request->parent_id && $request->parent_id !== 'null', fn($q) => $q->where('parent_id', $request->parent_id))
            ->orderBy('name');

        $perPage = $request->get('per_page', 15);
        $categories = $query->paginate($perPage);

        return $this->collection(BookCategoryResource::collection($categories));
    }

    /**
     * Get a single category.
     */
    public function show(BookCategory $category): JsonResponse
    {
        $category->load(['parent', 'children']);
        $category->loadCount('books');

        return $this->success(new BookCategoryResource($category));
    }

    /**
     * Create a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('book_categories')->where('tenant_id', $tenantId)],
            'parent_id' => ['nullable', 'uuid', 'exists:book_categories,id'],
            'description' => ['nullable', 'string'],
        ]);

        $category = BookCategory::create([
            'tenant_id' => $tenantId,
            ...$data,
        ]);

        return $this->success(
            new BookCategoryResource($category),
            'Kategori berhasil dibuat',
            201
        );
    }

    /**
     * Update a category.
     */
    public function update(Request $request, BookCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('book_categories')->where('tenant_id', $category->tenant_id)->ignore($category->id)],
            'parent_id' => ['nullable', 'uuid', 'exists:book_categories,id'],
            'description' => ['nullable', 'string'],
        ]);

        // Prevent circular reference
        if (isset($data['parent_id']) && $data['parent_id'] === $category->id) {
            return $this->error('Kategori tidak bisa menjadi parent dari dirinya sendiri', 422);
        }

        $category->update($data);

        return $this->success(
            new BookCategoryResource($category),
            'Kategori berhasil diperbarui'
        );
    }

    /**
     * Delete a category.
     */
    public function destroy(BookCategory $category): JsonResponse
    {
        // Check if category has books
        if ($category->books()->exists()) {
            return $this->error('Kategori tidak dapat dihapus karena masih memiliki buku', 422);
        }

        // Check if category has children
        if ($category->children()->exists()) {
            return $this->error('Kategori tidak dapat dihapus karena masih memiliki sub-kategori', 422);
        }

        $category->delete();

        return $this->success(null, 'Kategori berhasil dihapus');
    }
}
