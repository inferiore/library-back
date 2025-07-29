<?php

namespace App\Data\Repositories;

use App\Data\Repositories\Contracts\BorrowingRepositoryInterface;
use App\Models\Borrowing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class BorrowingRepository extends AbstractRepository implements BorrowingRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Borrowing::class;
    }

    /**
     * Find active borrowings
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findActive(int $perPage = 15): LengthAwarePaginator
    {
        $this->query->active();
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find returned borrowings
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findReturned(int $perPage = 15): LengthAwarePaginator
    {
        $this->query->returned();
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find overdue borrowings
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findOverdue(int $perPage = 15): LengthAwarePaginator
    {
        $this->query->overdue();
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find borrowings by user
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where('user_id', $userId)
                    ->latest();
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find borrowings by book
     *
     * @param int $bookId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByBook(int $bookId, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where('book_id', $bookId)
                    ->latest();
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find borrowings due soon
     *
     * @param int $days
     * @return Collection
     */
    public function findDueSoon(int $days = 3): Collection
    {
        $this->query->active()
                    ->whereBetween('due_at', [Carbon::now(), Carbon::now()->addDays($days)])
                    ->orderBy('due_at');
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find borrowings due today
     *
     * @return Collection
     */
    public function findDueToday(): Collection
    {
        $this->query->active()
                    ->whereDate('due_at', Carbon::today())
                    ->orderBy('due_at');
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find user's active borrowing for specific book
     *
     * @param int $userId
     * @param int $bookId
     * @return Borrowing|null
     */
    public function findActiveBorrowingByUserAndBook(int $userId, int $bookId): ?Borrowing
    {
        $this->query->where('user_id', $userId)
                    ->where('book_id', $bookId)
                    ->active();
        
        $this->applyRelations();
        $result = $this->query->first();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get borrowing statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStatistics(string $startDate, string $endDate): array
    {
        $baseQuery = $this->query->whereBetween('borrowed_at', [$startDate, $endDate]);

        $totalBorrowings = (clone $baseQuery)->count();
        $activeBorrowings = (clone $baseQuery)->whereNull('returned_at')->count();
        $returnedBorrowings = (clone $baseQuery)->whereNotNull('returned_at')->count();
        $overdueBorrowings = (clone $baseQuery)->overdue()->count();

        // Average borrowing duration for returned books
        $avgDuration = (clone $baseQuery)
            ->whereNotNull('returned_at')
            ->selectRaw('AVG(DATEDIFF(returned_at, borrowed_at)) as avg_days')
            ->value('avg_days');

        // On-time return rate
        $onTimeReturns = (clone $baseQuery)
            ->whereNotNull('returned_at')
            ->whereRaw('returned_at <= due_at')
            ->count();

        $this->resetQuery();

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_borrowings' => $totalBorrowings,
            'active_borrowings' => $activeBorrowings,
            'returned_borrowings' => $returnedBorrowings,
            'overdue_borrowings' => $overdueBorrowings,
            'average_duration_days' => round($avgDuration ?? 0, 1),
            'on_time_return_rate' => $returnedBorrowings > 0 
                ? round(($onTimeReturns / $returnedBorrowings) * 100, 2) 
                : 0,
            'return_rate' => $totalBorrowings > 0 
                ? round(($returnedBorrowings / $totalBorrowings) * 100, 2) 
                : 0
        ];
    }

    /**
     * Get monthly borrowing trends
     *
     * @param int $months
     * @return Collection
     */
    public function getMonthlyTrends(int $months = 12): Collection
    {
        $result = $this->query->selectRaw('
                YEAR(borrowed_at) as year,
                MONTH(borrowed_at) as month,
                COUNT(*) as total_borrowings,
                COUNT(CASE WHEN returned_at IS NOT NULL THEN 1 END) as returned_borrowings,
                COUNT(CASE WHEN returned_at IS NULL AND due_at < NOW() THEN 1 END) as overdue_borrowings
            ')
            ->where('borrowed_at', '>=', Carbon::now()->subMonths($months))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $this->resetQuery();
        return $result;
    }

    /**
     * Get user borrowing history
     *
     * @param int $userId
     * @param int $limit
     * @return Collection
     */
    public function getUserHistory(int $userId, int $limit = 10): Collection
    {
        $this->query->where('user_id', $userId)
                    ->latest()
                    ->limit($limit);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get book borrowing history
     *
     * @param int $bookId
     * @param int $limit
     * @return Collection
     */
    public function getBookHistory(int $bookId, int $limit = 10): Collection
    {
        $this->query->where('book_id', $bookId)
                    ->latest()
                    ->limit($limit);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Calculate user's total fines
     *
     * @param int $userId
     * @return float
     */
    public function calculateUserFines(int $userId): float
    {
        $overdueBorrowings = $this->query->where('user_id', $userId)
                                        ->overdue()
                                        ->get();
        
        $this->resetQuery();

        $totalFines = 0;
        foreach ($overdueBorrowings as $borrowing) {
            $daysOverdue = $borrowing->due_at->diffInDays(Carbon::now());
            $totalFines += $daysOverdue * 1.00; // $1 per day fine
        }

        return round($totalFines, 2);
    }

    /**
     * Get average borrowing duration
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getAverageBorrowingDuration(string $startDate, string $endDate): float
    {
        $avgDuration = $this->query->whereBetween('borrowed_at', [$startDate, $endDate])
                                  ->whereNotNull('returned_at')
                                  ->selectRaw('AVG(DATEDIFF(returned_at, borrowed_at)) as avg_days')
                                  ->value('avg_days');

        $this->resetQuery();
        return round($avgDuration ?? 0, 1);
    }

    /**
     * Get on-time return rate
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getOnTimeReturnRate(string $startDate, string $endDate): float
    {
        $totalReturned = $this->query->whereBetween('borrowed_at', [$startDate, $endDate])
                                    ->whereNotNull('returned_at')
                                    ->count();

        $this->resetQuery();

        $onTimeReturned = $this->query->whereBetween('borrowed_at', [$startDate, $endDate])
                                     ->whereNotNull('returned_at')
                                     ->whereRaw('returned_at <= due_at')
                                     ->count();

        $this->resetQuery();

        return $totalReturned > 0 ? round(($onTimeReturned / $totalReturned) * 100, 2) : 0;
    }

    /**
     * Find borrowings by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->whereBetween('borrowed_at', [$startDate, $endDate])
                    ->latest();
        
        $this->applyRelations();
        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Update borrowing return date
     *
     * @param int $borrowingId
     * @param string $returnDate
     * @return bool
     */
    public function markAsReturned(int $borrowingId, string $returnDate): bool
    {
        $result = $this->query->where('id', $borrowingId)
                             ->update(['returned_at' => $returnDate]);
        
        $this->resetQuery();
        return $result > 0;
    }

    /**
     * Extend borrowing due date
     *
     * @param int $borrowingId
     * @param int $days
     * @return bool
     */
    public function extendDueDate(int $borrowingId, int $days): bool
    {
        $borrowing = $this->find($borrowingId);
        if (!$borrowing) {
            return false;
        }

        $newDueDate = Carbon::parse($borrowing->due_at)->addDays($days);
        
        $result = $this->query->where('id', $borrowingId)
                             ->update(['due_at' => $newDueDate]);
        
        $this->resetQuery();
        return $result > 0;
    }

    /**
     * Get borrowings by status and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $this->applyBorrowingFilters($filters);
        $this->applyRelations();

        $result = $this->query->latest()->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Count active borrowings by user
     *
     * @param int $userId
     * @return int
     */
    public function countActiveBorrowingsByUser(int $userId): int
    {
        $result = $this->query->where('user_id', $userId)
                             ->active()
                             ->count();
        
        $this->resetQuery();
        return $result;
    }

    /**
     * Count active borrowings by book
     *
     * @param int $bookId
     * @return int
     */
    public function countActiveBorrowingsByBook(int $bookId): int
    {
        $result = $this->query->where('book_id', $bookId)
                             ->active()
                             ->count();
        
        $this->resetQuery();
        return $result;
    }

    /**
     * Get recent borrowings
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecent(int $limit = 10): Collection
    {
        $this->query->latest()
                    ->limit($limit);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Apply borrowing-specific filters
     *
     * @param array $filters
     */
    protected function applyBorrowingFilters(array $filters): void
    {
        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'active' => $this->query->active(),
                'returned' => $this->query->returned(),
                'overdue' => $this->query->overdue(),
                default => null
            };
        }

        if (!empty($filters['user_id'])) {
            $this->query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['book_id'])) {
            $this->query->where('book_id', $filters['book_id']);
        }

        if (!empty($filters['borrowed_from'])) {
            $this->query->where('borrowed_at', '>=', $filters['borrowed_from']);
        }

        if (!empty($filters['borrowed_to'])) {
            $this->query->where('borrowed_at', '<=', $filters['borrowed_to']);
        }

        if (!empty($filters['due_from'])) {
            $this->query->where('due_at', '>=', $filters['due_from']);
        }

        if (!empty($filters['due_to'])) {
            $this->query->where('due_at', '<=', $filters['due_to']);
        }

        if (isset($filters['is_overdue']) && $filters['is_overdue']) {
            $this->query->where('due_at', '<', Carbon::now())
                        ->whereNull('returned_at');
        }

        if (isset($filters['due_soon_days'])) {
            $days = (int) $filters['due_soon_days'];
            $this->query->active()
                        ->whereBetween('due_at', [Carbon::now(), Carbon::now()->addDays($days)]);
        }
    }

    /**
     * Get borrowing patterns analysis
     *
     * @param int $days
     * @return array
     */
    public function getBorrowingPatterns(int $days = 30): array
    {
        $cutoffDate = Carbon::now()->subDays($days);

        // Peak borrowing hours
        $hourlyPattern = $this->query->where('borrowed_at', '>=', $cutoffDate)
                                    ->selectRaw('HOUR(borrowed_at) as hour, COUNT(*) as count')
                                    ->groupBy('hour')
                                    ->orderBy('count', 'desc')
                                    ->get();

        // Peak borrowing days of week
        $dailyPattern = $this->query->where('borrowed_at', '>=', $cutoffDate)
                                   ->selectRaw('DAYOFWEEK(borrowed_at) as day_of_week, COUNT(*) as count')
                                   ->groupBy('day_of_week')
                                   ->orderBy('count', 'desc')
                                   ->get();

        // Most popular borrowing duration
        $durationPattern = $this->query->whereNotNull('returned_at')
                                      ->where('borrowed_at', '>=', $cutoffDate)
                                      ->selectRaw('DATEDIFF(returned_at, borrowed_at) as duration, COUNT(*) as count')
                                      ->groupBy('duration')
                                      ->orderBy('count', 'desc')
                                      ->limit(10)
                                      ->get();

        $this->resetQuery();

        return [
            'analysis_period_days' => $days,
            'peak_hours' => $hourlyPattern,
            'peak_days' => $dailyPattern,
            'common_durations' => $durationPattern
        ];
    }

    /**
     * Get borrowing performance metrics
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        $totalBorrowings = $this->query->count();
        $this->resetQuery();

        $activeBorrowings = $this->query->active()->count();
        $this->resetQuery();

        $overdueBorrowings = $this->query->overdue()->count();
        $this->resetQuery();

        $returnedBorrowings = $this->query->returned()->count();
        $this->resetQuery();

        // Average processing time (if we tracked when books were actually checked out vs requested)
        $avgBorrowingDuration = $this->query->returned()
                                           ->selectRaw('AVG(DATEDIFF(returned_at, borrowed_at)) as avg_days')
                                           ->value('avg_days');
        $this->resetQuery();

        return [
            'total_borrowings' => $totalBorrowings,
            'active_borrowings' => $activeBorrowings,
            'returned_borrowings' => $returnedBorrowings,
            'overdue_borrowings' => $overdueBorrowings,
            'overdue_rate' => $activeBorrowings > 0 
                ? round(($overdueBorrowings / $activeBorrowings) * 100, 2) 
                : 0,
            'return_rate' => $totalBorrowings > 0 
                ? round(($returnedBorrowings / $totalBorrowings) * 100, 2) 
                : 0,
            'average_borrowing_duration' => round($avgBorrowingDuration ?? 0, 1),
            'system_utilization' => [
                'total_transactions' => $totalBorrowings,
                'active_transactions' => $activeBorrowings,
                'completed_transactions' => $returnedBorrowings
            ]
        ];
    }
}