<?php

namespace App\Data\Repositories\Contracts;

use App\Models\Borrowing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BorrowingRepositoryInterface extends RepositoryInterface
{
    /**
     * Find active borrowings
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findActive(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find returned borrowings
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findReturned(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find overdue borrowings
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findOverdue(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find borrowings by user
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Find borrowings by book
     *
     * @param int $bookId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByBook(int $bookId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Find borrowings due soon
     *
     * @param int $days
     * @return Collection
     */
    public function findDueSoon(int $days = 3): Collection;

    /**
     * Find borrowings due today
     *
     * @return Collection
     */
    public function findDueToday(): Collection;

    /**
     * Get count of active borrowings
     *
     * @return int
     */
    public function getActiveBorrowingsCount(): int;

    /**
     * Get count of overdue borrowings
     *
     * @return int
     */
    public function getOverdueCount(): int;

    /**
     * Find user's active borrowing for specific book
     *
     * @param int $userId
     * @param int $bookId
     * @return Borrowing|null
     */
    public function findActiveBorrowingByUserAndBook(int $userId, int $bookId): ?Borrowing;

    /**
     * Get borrowing statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStatistics(string $startDate, string $endDate): array;

    /**
     * Get monthly borrowing trends
     *
     * @param int $months
     * @return Collection
     */
    public function getMonthlyTrends(int $months = 12): Collection;

    /**
     * Get user borrowing history
     *
     * @param int $userId
     * @param int $limit
     * @return Collection
     */
    public function getUserHistory(int $userId, int $limit = 10): Collection;

    /**
     * Get book borrowing history
     *
     * @param int $bookId
     * @param int $limit
     * @return Collection
     */
    public function getBookHistory(int $bookId, int $limit = 10): Collection;

    /**
     * Calculate user's total fines
     *
     * @param int $userId
     * @return float
     */
    public function calculateUserFines(int $userId): float;

    /**
     * Get average borrowing duration
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getAverageBorrowingDuration(string $startDate, string $endDate): float;

    /**
     * Get on-time return rate
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getOnTimeReturnRate(string $startDate, string $endDate): float;

    /**
     * Find borrowings by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator;

    /**
     * Update borrowing return date
     *
     * @param int $borrowingId
     * @param string $returnDate
     * @return bool
     */
    public function markAsReturned(int $borrowingId, string $returnDate): bool;

    /**
     * Extend borrowing due date
     *
     * @param int $borrowingId
     * @param int $days
     * @return bool
     */
    public function extendDueDate(int $borrowingId, int $days): bool;

    /**
     * Get borrowings by status and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Count active borrowings by user
     *
     * @param int $userId
     * @return int
     */
    public function countActiveBorrowingsByUser(int $userId): int;

    /**
     * Count active borrowings by book
     *
     * @param int $bookId
     * @return int
     */
    public function countActiveBorrowingsByBook(int $bookId): int;

    /**
     * Get recent borrowings
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecent(int $limit = 10): Collection;
}