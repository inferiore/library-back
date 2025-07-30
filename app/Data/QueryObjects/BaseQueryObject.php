<?php

namespace App\Data\QueryObjects;

use Illuminate\Database\Eloquent\Builder;

abstract class BaseQueryObject
{
    protected Builder $query;
    protected array $criteria = [];

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    abstract public function apply(): Builder;

    public function setCriteria(array $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }

    public function getCriteria(): array
    {
        return $this->criteria;
    }

    protected function getCriterion(string $key, $default = null)
    {
        return $this->criteria[$key] ?? $default;
    }

    protected function hasCriterion(string $key): bool
    {
        return isset($this->criteria[$key]) && !empty($this->criteria[$key]);
    }
}