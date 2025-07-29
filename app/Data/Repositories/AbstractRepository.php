<?php

namespace App\Data\Repositories;

use App\Data\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

abstract class AbstractRepository implements RepositoryInterface
{
    protected Model $model;
    protected Builder $query;
    protected array $with = [];
    protected bool $skipCache = false;
    protected int $cacheMinutes = 60;

    public function __construct()
    {
        $this->makeModel();
        $this->resetQuery();
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    abstract public function model(): string;

    /**
     * Make Model instance
     *
     * @return Model
     * @throws \Exception
     */
    public function makeModel(): Model
    {
        $model = app($this->model());

        if (!$model instanceof Model) {
            throw new \Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetQuery(): self
    {
        $this->query = $this->model->newQuery();
        return $this;
    }

    /**
     * Apply relationships
     *
     * @param array $relations
     * @return $this
     */
    public function with(array $relations): self
    {
        $this->with = $relations;
        return $this;
    }

    /**
     * Skip cache for next query
     *
     * @return $this
     */
    public function skipCache(): self
    {
        $this->skipCache = true;
        return $this;
    }

    /**
     * Find data by id
     *
     * @param int $id
     * @param array $columns
     * @return Model|null
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        $this->applyRelations();
        $result = $this->query->find($id, $columns);
        $this->resetQuery();
        return $result;
    }

    /**
     * Find data by id or fail
     *
     * @param int $id
     * @param array $columns
     * @return Model
     */
    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        $this->applyRelations();
        $result = $this->query->findOrFail($id, $columns);
        $this->resetQuery();
        return $result;
    }

    /**
     * Find data by criteria
     *
     * @param array $criteria
     * @param array $columns
     * @return Model|null
     */
    public function findBy(array $criteria, array $columns = ['*']): ?Model
    {
        $this->applyCriteria($criteria);
        $this->applyRelations();
        $result = $this->query->first($columns);
        $this->resetQuery();
        return $result;
    }

    /**
     * Find all data by criteria
     *
     * @param array $criteria
     * @param array $columns
     * @return Collection
     */
    public function findAllBy(array $criteria, array $columns = ['*']): Collection
    {
        $this->applyCriteria($criteria);
        $this->applyRelations();
        $result = $this->query->get($columns);
        $this->resetQuery();
        return $result;
    }

    /**
     * Get all data
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection
    {
        $this->applyRelations();
        $result = $this->query->get($columns);
        $this->resetQuery();
        return $result;
    }

    /**
     * Get paginated data
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $this->applyRelations();
        $result = $this->query->paginate($perPage, $columns, $pageName, $page);
        $this->resetQuery();
        return $result;
    }

    /**
     * Create new data
     *
     * @param array $attributes
     * @return Model
     */
    public function create(array $attributes): Model
    {
        $model = $this->model->newInstance($attributes);
        $model->save();
        $this->resetQuery();
        return $model;
    }

    /**
     * Update data
     *
     * @param int $id
     * @param array $attributes
     * @return bool
     */
    public function update(int $id, array $attributes): bool
    {
        $result = $this->query->where('id', $id)->update($attributes);
        $this->resetQuery();
        return $result > 0;
    }

    /**
     * Delete data
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $result = $this->query->where('id', $id)->delete();
        $this->resetQuery();
        return $result > 0;
    }

    /**
     * Count data
     *
     * @param array $criteria
     * @return int
     */
    public function count(array $criteria = []): int
    {
        $this->applyCriteria($criteria);
        $result = $this->query->count();
        $this->resetQuery();
        return $result;
    }

    /**
     * Check if data exists
     *
     * @param array $criteria
     * @return bool
     */
    public function exists(array $criteria): bool
    {
        $this->applyCriteria($criteria);
        $result = $this->query->exists();
        $this->resetQuery();
        return $result;
    }

    /**
     * First or create
     *
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        $result = $this->query->firstOrCreate($attributes, $values);
        $this->resetQuery();
        return $result;
    }

    /**
     * Update or create
     *
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $result = $this->query->updateOrCreate($attributes, $values);
        $this->resetQuery();
        return $result;
    }

    /**
     * Order by
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    /**
     * Where clause
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function where(string $column, $operator = null, $value = null): self
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    /**
     * Where in clause
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    /**
     * Limit query
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->query->limit($limit);
        return $this;
    }

    /**
     * Apply criteria to query
     *
     * @param array $criteria
     */
    protected function applyCriteria(array $criteria): void
    {
        foreach ($criteria as $key => $value) {
            if (is_array($value)) {
                $this->query->whereIn($key, $value);
            } else {
                $this->query->where($key, $value);
            }
        }
    }

    /**
     * Apply relations to query
     */
    protected function applyRelations(): void
    {
        if (!empty($this->with)) {
            $this->query->with($this->with);
        }
    }

    /**
     * Get cache key
     *
     * @param string $method
     * @param array $args
     * @return string
     */
    protected function getCacheKey(string $method, array $args = []): string
    {
        return sprintf(
            '%s:%s:%s',
            class_basename($this->model),
            $method,
            md5(serialize($args))
        );
    }

    /**
     * Remember cache result
     *
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    protected function remember(string $key, callable $callback)
    {
        if ($this->skipCache) {
            $this->skipCache = false;
            return $callback();
        }

        return Cache::remember($key, $this->cacheMinutes, $callback);
    }
}