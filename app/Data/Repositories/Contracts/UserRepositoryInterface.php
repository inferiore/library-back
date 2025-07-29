<?php

namespace App\Data\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find users by role
     *
     * @param string $role
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByRole(string $role, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get users with active borrowings
     *
     * @return Collection
     */
    public function getUsersWithActiveBorrowings(): Collection;

    /**
     * Get users with overdue borrowings
     *
     * @return Collection
     */
    public function getUsersWithOverdueBorrowings(): Collection;

    /**
     * Get most active borrowers
     *
     * @param int $limit
     * @param string $timeframe
     * @return Collection
     */
    public function getMostActiveBorrowers(int $limit = 10, string $timeframe = 'all'): Collection;

    /**
     * Search users by name or email
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchUsers(string $search, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get user borrowing statistics
     *
     * @param int $userId
     * @return array
     */
    public function getUserBorrowingStats(int $userId): array;

    /**
     * Find users by registration date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function findByRegistrationDate(string $startDate, string $endDate): Collection;

    /**
     * Get users who haven't borrowed recently
     *
     * @param int $days
     * @return Collection
     */
    public function getInactiveUsers(int $days = 90): Collection;

    /**
     * Update user last login
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin(int $userId): bool;

    /**
     * Get user role distribution
     *
     * @return array
     */
    public function getRoleDistribution(): array;

    /**
     * Find users with borrowing limits
     *
     * @param string $role
     * @return array
     */
    public function getBorrowingLimits(string $role): array;

    /**
     * Check email uniqueness
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function isEmailUnique(string $email, ?int $excludeId = null): bool;

    /**
     * Get user activity summary
     *
     * @param int $userId
     * @param int $days
     * @return array
     */
    public function getUserActivity(int $userId, int $days = 30): array;

    /**
     * Update user profile data
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateProfile(int $userId, array $data): bool;

    /**
     * Get users by multiple filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByFilters(array $filters, int $perPage = 15): LengthAwarePaginator;
}