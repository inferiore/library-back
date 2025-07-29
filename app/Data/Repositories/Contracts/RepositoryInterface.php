<?php

namespace App\Data\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    /**
     * Find a model by its primary key
     *
     * @param int $id
     * @param array $columns
     * @return Model|null
     */
    public function find(int $id, array $columns = ['*']): ?Model;

    /**
     * Find a model by its primary key or fail
     *
     * @param int $id
     * @param array $columns
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id, array $columns = ['*']): Model;

    /**
     * Find a model by specified criteria
     *
     * @param array $criteria
     * @param array $columns
     * @return Model|null
     */
    public function findBy(array $criteria, array $columns = ['*']): ?Model;

    /**
     * Find all models matching criteria
     *
     * @param array $criteria
     * @param array $columns
     * @return Collection
     */
    public function findAllBy(array $criteria, array $columns = ['*']): Collection;

    /**
     * Get all models
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated results
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator;

    /**
     * Create a new model
     *
     * @param array $attributes
     * @return Model
     */
    public function create(array $attributes): Model;

    /**
     * Update a model
     *
     * @param int $id
     * @param array $attributes
     * @return bool
     */
    public function update(int $id, array $attributes): bool;

    /**
     * Delete a model
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Count models matching criteria
     *
     * @param array $criteria
     * @return int
     */
    public function count(array $criteria = []): int;

    /**
     * Check if model exists by criteria
     *
     * @param array $criteria
     * @return bool
     */
    public function exists(array $criteria): bool;

    /**
     * Get first model or create if not exists
     *
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public function firstOrCreate(array $attributes, array $values = []): Model;

    /**
     * Update or create model
     *
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * Apply relationships to query
     *
     * @param array $relations
     * @return $this
     */
    public function with(array $relations): self;

    /**
     * Apply ordering to query
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self;

    /**
     * Apply where clause to query
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function where(string $column, $operator = null, $value = null): self;

    /**
     * Apply whereIn clause to query
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereIn(string $column, array $values): self;

    /**
     * Apply limit to query
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self;

    /**
     * Reset query builder state
     *
     * @return $this
     */
    public function resetQuery(): self;
}