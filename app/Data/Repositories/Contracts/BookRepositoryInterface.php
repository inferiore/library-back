<?php

namespace App\Data\Repositories\Contracts;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BookRepositoryInterface extends RepositoryInterface
{
    /**
     * Search books by title, author, or genre
     *
     * @param string $search
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(string $search, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find available books
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findAvailable(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find books by genre
     *
     * @param string $genre
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByGenre(string $genre, int $perPage = 15): LengthAwarePaginator;

    /**
     * Find books by author
     *
     * @param string $author
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByAuthor(string $author, int $perPage = 15): LengthAwarePaginator;

    /**
     * Find book by ISBN
     *
     * @param string $isbn
     * @return Book|null
     */
    public function findByISBN(string $isbn): ?Book;


    /**
     * Get books with low stock
     *
     * @param int $threshold
     * @return Collection
     */
    public function getLowStock(int $threshold = 2): Collection;

    /**
     * Get genre statistics
     *
     * @return Collection
     */
    public function getGenreStatistics(): Collection;

    /**
     * Get books added in date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate): Collection;

    /**
     * Update book availability
     *
     * @param int $bookId
     * @param int $change
     * @return bool
     */
    public function updateAvailability(int $bookId, int $change): bool;

    /**
     * Get books that can be deleted (no active borrowings)
     *
     * @return Collection
     */
    public function getDeletableBooks(): Collection;

    /**
     * Find books with active borrowings
     *
     * @return Collection
     */
    public function getBooksWithActiveBorrowings(): Collection;

    /**
     * Get total copies count
     *
     * @return int
     */
    public function getTotalCopiesCount(): int;

    /**
     * Get available copies count
     *
     * @return int
     */
    public function getAvailableCopiesCount(): int;

    /**
     * Get books by multiple criteria
     *
     * @param array $criteria
     * @param array $sorting
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByCriteria(array $criteria, array $sorting = [], int $perPage = 15): LengthAwarePaginator;

    public function getTotalCount();
}
