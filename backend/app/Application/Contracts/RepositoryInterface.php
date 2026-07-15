<?php

namespace App\Application\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find a record by ID.
     */
    public function find(string $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by ID or throw an exception.
     */
    public function findOrFail(string $id, array $columns = ['*']): Model;

    /**
     * Find records by a specific field.
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): Collection;

    /**
     * Find a single record by a specific field.
     */
    public function findOneBy(string $field, mixed $value, array $columns = ['*']): ?Model;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update a record by ID.
     */
    public function update(string $id, array $data): Model;

    /**
     * Delete a record by ID.
     */
    public function delete(string $id): bool;

    /**
     * Get records with relationships.
     */
    public function with(array $relations): self;

    /**
     * Order records by a specific field.
     */
    public function orderBy(string $column, string $direction = 'asc'): self;

    /**
     * Filter records by criteria.
     */
    public function where(string $column, mixed $operator, mixed $value = null): self;

    /**
     * Get the count of records.
     */
    public function count(): int;
}
