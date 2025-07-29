<?php

namespace App\Data\Repositories;

use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BookRepository extends AbstractRepository implements BookRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Book::class;
    }

    /**
     * Search books by title, author, or genre
     *
     * @param string $search
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(string $search, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where(function ($query) use ($search) {
            $query->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('author', 'LIKE', "%{$search}%")
                  ->orWhere('genre', 'LIKE', "%{$search}%")
                  ->orWhere('isbn', 'LIKE', "%{$search}%");
        });

        $this->applyFilters($filters);
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find available books
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findAvailable(int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where('available_copies', '>', 0);
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find books by genre
     *
     * @param string $genre
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByGenre(string $genre, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where('genre', $genre);
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find books by author
     *
     * @param string $author
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByAuthor(string $author, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where('author', 'LIKE', "%{$author}%");
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find book by ISBN
     *
     * @param string $isbn
     * @return Book|null
     */
    public function findByISBN(string $isbn): ?Book
    {
        $this->applyRelations();
        $result = $this->query->where('isbn', $isbn)->first();
        $this->resetQuery();
        
        return $result;
    }


    /**
     * Get books with low stock
     *
     * @param int $threshold
     * @return Collection
     */
    public function getLowStock(int $threshold = 2): Collection
    {
        $this->query->where('available_copies', '<=', $threshold)
                    ->where('available_copies', '>', 0);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get genre statistics
     *
     * @return Collection
     */
    public function getGenreStatistics(): Collection
    {
        $result = $this->query->selectRaw('genre, COUNT(*) as count, SUM(total_copies) as total_copies, SUM(available_copies) as available_copies')
                             ->groupBy('genre')
                             ->orderBy('count', 'desc')
                             ->get();
        
        $this->resetQuery();
        return $result;
    }

    /**
     * Get books added in date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate): Collection
    {
        $this->query->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc');
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Update book availability
     *
     * @param int $bookId
     * @param int $change
     * @return bool
     */
    public function updateAvailability(int $bookId, int $change): bool
    {
        $result = $this->query->where('id', $bookId)
                             ->increment('available_copies', $change);
        
        $this->resetQuery();
        return $result > 0;
    }

    /**
     * Get books that can be deleted (no active borrowings)
     *
     * @return Collection
     */
    public function getDeletableBooks(): Collection
    {
        $this->query->whereDoesntHave('borrowings', function ($query) {
            $query->active();
        });
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find books with active borrowings
     *
     * @return Collection
     */
    public function getBooksWithActiveBorrowings(): Collection
    {
        $this->query->whereHas('borrowings', function ($query) {
            $query->active();
        })->withCount(['borrowings' => function ($query) {
            $query->active();
        }]);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get total copies count
     *
     * @return int
     */
    public function getTotalCopiesCount(): int
    {
        $result = $this->query->sum('total_copies');
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get available copies count
     *
     * @return int
     */
    public function getAvailableCopiesCount(): int
    {
        $result = $this->query->sum('available_copies');
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get books by multiple criteria
     *
     * @param array $criteria
     * @param array $sorting
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByCriteria(array $criteria, array $sorting = [], int $perPage = 15): LengthAwarePaginator
    {
        $this->applyCriteria($criteria);
        $this->applySorting($sorting);
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Apply filters to query
     *
     * @param array $filters
     */
    protected function applyFilters(array $filters): void
    {
        if (!empty($filters['genre'])) {
            $this->query->where('genre', $filters['genre']);
        }

        if (!empty($filters['author'])) {
            $this->query->where('author', 'LIKE', '%' . $filters['author'] . '%');
        }

        if (isset($filters['available_only']) && $filters['available_only']) {
            $this->query->where('available_copies', '>', 0);
        }

        if (isset($filters['min_copies'])) {
            $this->query->where('total_copies', '>=', $filters['min_copies']);
        }

        if (isset($filters['max_copies'])) {
            $this->query->where('total_copies', '<=', $filters['max_copies']);
        }
    }

    /**
     * Apply sorting to query
     *
     * @param array $sorting
     */
    protected function applySorting(array $sorting): void
    {
        if (empty($sorting)) {
            $this->query->orderBy('title', 'asc');
            return;
        }

        foreach ($sorting as $field => $direction) {
            $allowedFields = ['title', 'author', 'genre', 'created_at', 'updated_at', 'total_copies', 'available_copies'];
            $allowedDirections = ['asc', 'desc'];

            if (in_array($field, $allowedFields) && in_array(strtolower($direction), $allowedDirections)) {
                $this->query->orderBy($field, $direction);
            }
        }
    }

    /**
     * Get books summary statistics
     *
     * @return array
     */
    public function getSummaryStats(): array
    {
        $stats = $this->query->selectRaw('
            COUNT(*) as total_books,
            SUM(total_copies) as total_copies,
            SUM(available_copies) as available_copies,
            AVG(total_copies) as avg_copies_per_book,
            COUNT(DISTINCT genre) as total_genres,
            COUNT(DISTINCT author) as total_authors
        ')->first();

        $this->resetQuery();

        return [
            'total_books' => $stats->total_books ?? 0,
            'total_copies' => $stats->total_copies ?? 0,
            'available_copies' => $stats->available_copies ?? 0,
            'borrowed_copies' => ($stats->total_copies ?? 0) - ($stats->available_copies ?? 0),
            'avg_copies_per_book' => round($stats->avg_copies_per_book ?? 0, 2),
            'total_genres' => $stats->total_genres ?? 0,
            'total_authors' => $stats->total_authors ?? 0,
            'utilization_rate' => $stats->total_copies > 0 
                ? round((($stats->total_copies - $stats->available_copies) / $stats->total_copies) * 100, 2) 
                : 0
        ];
    }

    /**
     * Find books needing attention (low stock or high demand)
     *
     * @return array
     */
    public function getBooksNeedingAttention(): array
    {
        // Low stock books
        $lowStock = $this->getLowStock(2);

        // High demand books (all copies borrowed)
        $this->query->where('available_copies', 0)
                    ->where('total_copies', '>', 0);
        $highDemand = $this->query->get();
        $this->resetQuery();

        // Books with many reservations would go here if we had a reservation system

        return [
            'low_stock' => $lowStock,
            'high_demand' => $highDemand,
            'attention_needed' => $lowStock->count() + $highDemand->count()
        ];
    }
}