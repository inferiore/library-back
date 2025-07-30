<?php

namespace App\Data\QueryObjects;

use Illuminate\Database\Eloquent\Builder;

class BookSearchQueryObject extends BaseQueryObject
{
    public function apply(): Builder
    {
        $this->applySearchTerm();
        $this->applyFilters();
        $this->applySorting();
        
        return $this->query;
    }

    protected function applySearchTerm(): void
    {
        $search = $this->getCriterion('search');
        
        if (!empty($search)) {
            $this->query->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('author', 'LIKE', "%{$search}%")
                      ->orWhere('genre', 'LIKE', "%{$search}%")
                      ->orWhere('isbn', 'LIKE', "%{$search}%");
            });
        }
    }

    protected function applyFilters(): void
    {
        $this->applyGenreFilter();
        $this->applyAuthorFilter();
        $this->applyAvailabilityFilter();
        $this->applyCopiesFilter();
        $this->applyDateRangeFilter();
    }

    protected function applyGenreFilter(): void
    {
        $genre = $this->getCriterion('genre');
        
        if (!empty($genre)) {
            if (is_array($genre)) {
                $this->query->whereIn('genre', $genre);
            } else {
                $this->query->where('genre', $genre);
            }
        }
    }

    protected function applyAuthorFilter(): void
    {
        $author = $this->getCriterion('author');
        
        if (!empty($author)) {
            $this->query->where('author', 'LIKE', '%' . $author . '%');
        }
    }

    protected function applyAvailabilityFilter(): void
    {
        $availableOnly = $this->getCriterion('available_only');
        
        if ($availableOnly === true || $availableOnly === 'true' || $availableOnly === '1') {
            $this->query->where('available_copies', '>', 0);
        }
        
        $unavailableOnly = $this->getCriterion('unavailable_only');
        
        if ($unavailableOnly === true || $unavailableOnly === 'true' || $unavailableOnly === '1') {
            $this->query->where('available_copies', '<=', 0);
        }
    }

    protected function applyCopiesFilter(): void
    {
        $minCopies = $this->getCriterion('min_copies');
        if (!empty($minCopies) && is_numeric($minCopies)) {
            $this->query->where('total_copies', '>=', (int) $minCopies);
        }

        $maxCopies = $this->getCriterion('max_copies');
        if (!empty($maxCopies) && is_numeric($maxCopies)) {
            $this->query->where('total_copies', '<=', (int) $maxCopies);
        }

        $minAvailable = $this->getCriterion('min_available');
        if (!empty($minAvailable) && is_numeric($minAvailable)) {
            $this->query->where('available_copies', '>=', (int) $minAvailable);
        }

        $maxAvailable = $this->getCriterion('max_available');
        if (!empty($maxAvailable) && is_numeric($maxAvailable)) {
            $this->query->where('available_copies', '<=', (int) $maxAvailable);
        }
    }

    protected function applyDateRangeFilter(): void
    {
        $createdAfter = $this->getCriterion('created_after');
        if (!empty($createdAfter)) {
            $this->query->where('created_at', '>=', $createdAfter);
        }

        $createdBefore = $this->getCriterion('created_before');
        if (!empty($createdBefore)) {
            $this->query->where('created_at', '<=', $createdBefore);
        }

        $updatedAfter = $this->getCriterion('updated_after');
        if (!empty($updatedAfter)) {
            $this->query->where('updated_at', '>=', $updatedAfter);
        }

        $updatedBefore = $this->getCriterion('updated_before');
        if (!empty($updatedBefore)) {
            $this->query->where('updated_at', '<=', $updatedBefore);
        }
    }

    protected function applySorting(): void
    {
        $sortBy = $this->getCriterion('sort_by', 'title');
        $sortDirection = $this->getCriterion('sort_direction', 'asc');
        
        $allowedSortFields = [
            'title', 'author', 'genre', 'isbn', 
            'total_copies', 'available_copies', 
            'created_at', 'updated_at'
        ];
        
        $allowedDirections = ['asc', 'desc'];
        
        if (in_array($sortBy, $allowedSortFields) && in_array(strtolower($sortDirection), $allowedDirections)) {
            $this->query->orderBy($sortBy, strtolower($sortDirection));
        } else {
            $this->query->orderBy('title', 'asc');
        }
        
        $secondarySort = $this->getCriterion('secondary_sort');
        if (!empty($secondarySort) && is_array($secondarySort)) {
            $secondaryField = $secondarySort['field'] ?? null;
            $secondaryDirection = $secondarySort['direction'] ?? 'asc';
            
            if ($secondaryField && 
                in_array($secondaryField, $allowedSortFields) && 
                in_array(strtolower($secondaryDirection), $allowedDirections) && 
                $secondaryField !== $sortBy) {
                $this->query->orderBy($secondaryField, strtolower($secondaryDirection));
            }
        }
    }

    public function withActiveBorrowings(): self
    {
        $this->query->whereHas('borrowings', function ($query) {
            $query->active();
        });
        
        return $this;
    }

    public function withoutActiveBorrowings(): self
    {
        $this->query->whereDoesntHave('borrowings', function ($query) {
            $query->active();
        });
        
        return $this;
    }

    public function withBorrowingsCount(): self
    {
        $this->query->withCount(['borrowings', 'borrowings as active_borrowings_count' => function ($query) {
            $query->active();
        }]);
        
        return $this;
    }

    public function popularBooks(int $minBorrowings = 5): self
    {
        $this->query->whereHas('borrowings', function ($query) use ($minBorrowings) {
            $query->havingRaw('COUNT(*) >= ?', [$minBorrowings]);
        }, '>=', $minBorrowings);
        
        return $this;
    }

    public function lowStockBooks(int $threshold = 2): self
    {
        $this->query->where('available_copies', '<=', $threshold)
                    ->where('available_copies', '>', 0);
        
        return $this;
    }

    public function searchByPartialMatch(string $field, string $value): self
    {
        $allowedFields = ['title', 'author', 'genre', 'isbn'];
        
        if (in_array($field, $allowedFields)) {
            $this->query->where($field, 'LIKE', '%' . $value . '%');
        }
        
        return $this;
    }
}