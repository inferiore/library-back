<?php

namespace App\Data\Repositories;

use App\Data\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class UserRepository extends AbstractRepository implements UserRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return User::class;
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        $this->applyRelations();
        $result = $this->query->where('email', $email)->first();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Find users by role
     *
     * @param string $role
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByRole(string $role, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where('role', $role);
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get users with active borrowings
     *
     * @return Collection
     */
    public function getUsersWithActiveBorrowings(): Collection
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
     * Get users with overdue borrowings
     *
     * @return Collection
     */
    public function getUsersWithOverdueBorrowings(): Collection
    {
        $this->query->whereHas('borrowings', function ($query) {
            $query->overdue();
        })->with(['borrowings' => function ($query) {
            $query->overdue()->with('book');
        }]);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get most active borrowers
     *
     * @param int $limit
     * @param string $timeframe
     * @return Collection
     */
    public function getMostActiveBorrowers(int $limit = 10, string $timeframe = 'all'): Collection
    {
        $this->query->withCount(['borrowings' => function ($query) use ($timeframe) {
            if ($timeframe !== 'all') {
                $date = match ($timeframe) {
                    'month' => Carbon::now()->subMonth(),
                    'quarter' => Carbon::now()->subQuarter(),
                    'year' => Carbon::now()->subYear(),
                    default => Carbon::now()->subMonth()
                };
                $query->where('borrowed_at', '>=', $date);
            }
        }])
        ->orderBy('borrowings_count', 'desc')
        ->limit($limit);
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Search users by name or email
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchUsers(string $search, int $perPage = 15): LengthAwarePaginator
    {
        $this->query->where(function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
        });

        $this->applyRelations();
        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get user borrowing statistics
     *
     * @param int $userId
     * @return array
     */
    public function getUserBorrowingStats(int $userId): array
    {
        $user = $this->find($userId);
        if (!$user) {
            return [];
        }

        $totalBorrowings = $user->borrowings()->count();
        $activeBorrowings = $user->borrowings()->active()->count();
        $returnedBorrowings = $user->borrowings()->returned()->count();
        $overdueBorrowings = $user->borrowings()->overdue()->count();

        // Calculate average borrowing duration for returned books
        $avgDuration = $user->borrowings()
            ->returned()
            ->selectRaw('AVG(DATEDIFF(returned_at, borrowed_at)) as avg_days')
            ->value('avg_days');

        // Get favorite genres
        $favoriteGenres = $user->borrowings()
            ->join('books', 'borrowings.book_id', '=', 'books.id')
            ->selectRaw('books.genre, COUNT(*) as count')
            ->groupBy('books.genre')
            ->orderBy('count', 'desc')
            ->limit(3)
            ->pluck('count', 'genre')
            ->toArray();

        return [
            'total_borrowings' => $totalBorrowings,
            'active_borrowings' => $activeBorrowings,
            'returned_borrowings' => $returnedBorrowings,
            'overdue_borrowings' => $overdueBorrowings,
            'average_duration_days' => round($avgDuration ?? 0, 1),
            'favorite_genres' => $favoriteGenres,
            'on_time_return_rate' => $returnedBorrowings > 0 
                ? round((($returnedBorrowings - $overdueBorrowings) / $returnedBorrowings) * 100, 2) 
                : 0
        ];
    }

    /**
     * Find users by registration date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function findByRegistrationDate(string $startDate, string $endDate): Collection
    {
        $this->query->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc');
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Get users who haven't borrowed recently
     *
     * @param int $days
     * @return Collection
     */
    public function getInactiveUsers(int $days = 90): Collection
    {
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->query->where(function ($query) use ($cutoffDate) {
            $query->whereDoesntHave('borrowings')
                  ->orWhereHas('borrowings', function ($subQuery) use ($cutoffDate) {
                      $subQuery->where('borrowed_at', '<', $cutoffDate);
                  }, '<', 1);
        });
        
        $this->applyRelations();
        $result = $this->query->get();
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Update user last login (if we track this)
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        $result = $this->query->where('id', $userId)
                             ->update(['last_login_at' => Carbon::now()]);
        
        $this->resetQuery();
        return $result > 0;
    }

    /**
     * Get user role distribution
     *
     * @return array
     */
    public function getRoleDistribution(): array
    {
        $distribution = $this->query->selectRaw('role, COUNT(*) as count')
                                   ->groupBy('role')
                                   ->pluck('count', 'role')
                                   ->toArray();
        
        $this->resetQuery();
        return $distribution;
    }

    /**
     * Find users with borrowing limits
     *
     * @param string $role
     * @return array
     */
    public function getBorrowingLimits(string $role): array
    {
        $limit = $role === 'librarian' ? 10 : 5;
        
        return [
            'role' => $role,
            'max_borrowings' => $limit,
            'users_at_limit' => $this->query->where('role', $role)
                                           ->whereHas('borrowings', function ($query) {
                                               $query->active();
                                           }, '>=', $limit)
                                           ->count()
        ];
    }

    /**
     * Check email uniqueness
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function isEmailUnique(string $email, ?int $excludeId = null): bool
    {
        $this->query->where('email', $email);
        
        if ($excludeId) {
            $this->query->where('id', '!=', $excludeId);
        }
        
        $exists = $this->query->exists();
        $this->resetQuery();
        
        return !$exists;
    }

    /**
     * Get user activity summary
     *
     * @param int $userId
     * @param int $days
     * @return array
     */
    public function getUserActivity(int $userId, int $days = 30): array
    {
        $user = $this->find($userId);
        if (!$user) {
            return [];
        }

        $cutoffDate = Carbon::now()->subDays($days);

        $recentBorrowings = $user->borrowings()
            ->where('borrowed_at', '>=', $cutoffDate)
            ->count();

        $recentReturns = $user->borrowings()
            ->where('returned_at', '>=', $cutoffDate)
            ->count();

        $currentlyOverdue = $user->borrowings()
            ->overdue()
            ->count();

        return [
            'user_id' => $userId,
            'period_days' => $days,
            'recent_borrowings' => $recentBorrowings,
            'recent_returns' => $recentReturns,
            'currently_overdue' => $currentlyOverdue,
            'activity_level' => $this->calculateActivityLevel($recentBorrowings, $days)
        ];
    }

    /**
     * Update user profile data
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $allowedFields = ['name', 'email', 'password'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (empty($updateData)) {
            return false;
        }

        $result = $this->query->where('id', $userId)->update($updateData);
        $this->resetQuery();
        
        return $result > 0;
    }

    /**
     * Get users by multiple filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $this->applyUserFilters($filters);
        $this->applyRelations();

        $result = $this->query->paginate($perPage);
        $this->resetQuery();
        
        return $result;
    }

    /**
     * Apply user-specific filters
     *
     * @param array $filters
     */
    protected function applyUserFilters(array $filters): void
    {
        if (!empty($filters['role'])) {
            $this->query->where('role', $filters['role']);
        }

        if (!empty($filters['search'])) {
            $this->query->where(function ($query) use ($filters) {
                $query->where('name', 'LIKE', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['has_active_borrowings'])) {
            if ($filters['has_active_borrowings']) {
                $this->query->whereHas('borrowings', function ($query) {
                    $query->active();
                });
            } else {
                $this->query->whereDoesntHave('borrowings', function ($query) {
                    $query->active();
                });
            }
        }

        if (isset($filters['has_overdue_borrowings'])) {
            if ($filters['has_overdue_borrowings']) {
                $this->query->whereHas('borrowings', function ($query) {
                    $query->overdue();
                });
            }
        }

        if (!empty($filters['registered_after'])) {
            $this->query->where('created_at', '>=', $filters['registered_after']);
        }

        if (!empty($filters['registered_before'])) {
            $this->query->where('created_at', '<=', $filters['registered_before']);
        }
    }

    /**
     * Calculate user activity level
     *
     * @param int $borrowings
     * @param int $days
     * @return string
     */
    protected function calculateActivityLevel(int $borrowings, int $days): string
    {
        $rate = $borrowings / max($days, 1) * 30; // Normalize to per month

        return match (true) {
            $rate >= 4 => 'very_high',
            $rate >= 2 => 'high',
            $rate >= 1 => 'medium',
            $rate > 0 => 'low',
            default => 'inactive'
        };
    }

    /**
     * Get user registration trends
     *
     * @param int $months
     * @return Collection
     */
    public function getRegistrationTrends(int $months = 12): Collection
    {
        $result = $this->query->selectRaw('
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                role,
                COUNT(*) as count
            ')
            ->where('created_at', '>=', Carbon::now()->subMonths($months))
            ->groupBy('year', 'month', 'role')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $this->resetQuery();
        return $result;
    }

    /**
     * Get users summary statistics
     *
     * @return array
     */
    public function getSummaryStats(): array
    {
        $totalUsers = $this->query->count();
        $this->resetQuery();

        $roleDistribution = $this->getRoleDistribution();
        
        $usersWithActiveBorrowings = $this->query->whereHas('borrowings', function ($query) {
            $query->active();
        })->count();
        $this->resetQuery();

        $usersWithOverdue = $this->query->whereHas('borrowings', function ($query) {
            $query->overdue();
        })->count();
        $this->resetQuery();

        return [
            'total_users' => $totalUsers,
            'librarians' => $roleDistribution['librarian'] ?? 0,
            'members' => $roleDistribution['member'] ?? 0,
            'users_with_active_borrowings' => $usersWithActiveBorrowings,
            'users_with_overdue_books' => $usersWithOverdue,
            'active_user_percentage' => $totalUsers > 0 
                ? round(($usersWithActiveBorrowings / $totalUsers) * 100, 2) 
                : 0
        ];
    }
}