<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * The Eloquent model instance.
     */
    protected Model $model;

    /**
     * The query builder instance.
     */
    protected Builder $query;

    /**
     * Create a new repository instance.
     */
    public function __construct()
    {
        $this->model = $this->resolveModel();
        $this->resetQuery();
    }

    /**
     * Get the model class name.
     */
    abstract protected function model(): string;

    /**
     * Resolve the model instance.
     */
    protected function resolveModel(): Model
    {
        return app($this->model());
    }

    /**
     * Reset the query builder.
     */
    protected function resetQuery(): self
    {
        $this->query = $this->model->newQuery();

        return $this;
    }

    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection
    {
        $result = $this->query->get($columns);
        $this->resetQuery();

        return $result;
    }

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        $result = $this->query->paginate($perPage, $columns);
        $this->resetQuery();

        return $result;
    }

    /**
     * Find a record by ID.
     */
    public function find(string $id, array $columns = ['*']): ?Model
    {
        $result = $this->query->find($id, $columns);
        $this->resetQuery();

        return $result;
    }

    /**
     * Find a record by ID or throw an exception.
     */
    public function findOrFail(string $id, array $columns = ['*']): Model
    {
        $result = $this->query->findOrFail($id, $columns);
        $this->resetQuery();

        return $result;
    }

    /**
     * Find records by a specific field.
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        $result = $this->query->where($field, $value)->get($columns);
        $this->resetQuery();

        return $result;
    }

    /**
     * Find a single record by a specific field.
     */
    public function findOneBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        $result = $this->query->where($field, $value)->first($columns);
        $this->resetQuery();

        return $result;
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record by ID.
     */
    public function update(string $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $model->update($data);

        return $model->fresh();
    }

    /**
     * Delete a record by ID.
     */
    public function delete(string $id): bool
    {
        return $this->findOrFail($id)->delete();
    }

    /**
     * Get records with relationships.
     */
    public function with(array $relations): self
    {
        $this->query->with($relations);

        return $this;
    }

    /**
     * Order records by a specific field.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Filter records by criteria.
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        $this->query->where($column, $operator, $value);

        return $this;
    }

    /**
     * Get the count of records.
     */
    public function count(): int
    {
        $result = $this->query->count();
        $this->resetQuery();

        return $result;
    }

    /**
     * Get the underlying query builder.
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Apply filters from request.
     */
    public function applyFilters(array $filters): self
    {
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $this->query->where($field, $value);
            }
        }

        return $this;
    }

    /**
     * Search records by multiple fields.
     */
    public function search(string $term, array $fields): self
    {
        $this->query->where(function ($query) use ($term, $fields) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'ILIKE', "%{$term}%");
            }
        });

        return $this;
    }
}
