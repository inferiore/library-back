<?php

namespace App\Business\Services;

use App\Business\Exceptions\BusinessException;
use App\Business\Exceptions\UnauthorizedException;
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Data\Repositories\Contracts\BorrowingRepositoryInterface;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Carbon\Carbon;

class DashboardService extends BaseService
{
    protected BookRepositoryInterface $bookRepository;
    protected BorrowingRepositoryInterface $borrowingRepository;
    protected UserRepositoryInterface $userRepository;

    public function __construct(
        BookRepositoryInterface $bookRepository,
        BorrowingRepositoryInterface $borrowingRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->bookRepository = $bookRepository;
        $this->borrowingRepository = $borrowingRepository;
        $this->userRepository = $userRepository;
    }
    /**
     * Get role-specific dashboard data
     *
     * @return array
     * @throws BusinessException
     */
    public function getDashboardData(): array
    {
        $this->requireAuth();
        
        $user = auth()->user();
        $this->logOperation('get_dashboard_data', ['user_role' => $user->role]);

        return $user->isLibrarian() 
            ? $this->getLibrarianDashboard()
            : $this->getMemberDashboard();
    }

    /**
     * Get librarian dashboard with system overview
     *
     * @return array
     * @throws BusinessException
     */
    public function getLibrarianDashboard(): array
    {
        $this->requireRole('librarian');
        $this->logOperation('get_librarian_dashboard');

        // Basic statistics using repositories
        $bookStats = $this->bookRepository->getSummaryStats();
        $borrowingStats = $this->borrowingRepository->getPerformanceMetrics();
        $userStats = $this->userRepository->getSummaryStats();
        
        $stats = [
            'total_books' => $bookStats['total_books'],
            'total_copies' => $bookStats['total_copies'],
            'total_borrowed_books' => $borrowingStats['active_borrowings'],
            'available_copies' => $bookStats['available_copies'],
            'total_members' => $userStats['members'],
            'books_due_today' => $this->borrowingRepository->findDueToday()->count(),
            'overdue_books' => $borrowingStats['overdue_borrowings'],
            'members_with_overdue_books' => $this->userRepository->getUsersWithOverdueBorrowings()->count()
        ];

        // Get data using repositories
        $membersWithOverdueBooks = $this->userRepository->getUsersWithOverdueBorrowings();
        $recentBorrowings = $this->borrowingRepository->getRecent(10);
        $booksDueSoon = $this->borrowingRepository->findDueSoon(3);
        $monthlyTrends = $this->borrowingRepository->getMonthlyTrends(12);
        $genreDistribution = $this->bookRepository->getGenreStatistics();

        return $this->successResponse([
            'stats' => $stats,
            'members_with_overdue_books' => $membersWithOverdueBooks,
            'recent_borrowings' => $recentBorrowings,
            'books_due_soon' => $booksDueSoon,
            'monthly_trends' => $monthlyTrends,
            'genre_distribution' => $genreDistribution
        ], 'Librarian dashboard data retrieved successfully');
    }

    /**
     * Get member dashboard with personal borrowing information
     *
     * @return array
     * @throws BusinessException
     */
    public function getMemberDashboard(): array
    {
        $this->requireAuth();
        
        $user = auth()->user();
        
        if (!$user->isMember()) {
            throw new UnauthorizedException('Member access required');
        }

        $this->logOperation('get_member_dashboard', ['user_id' => $user->id]);

        // User's borrowing statistics
        $activeBorrowings = $user->borrowings()
            ->active()
            ->with('book')
            ->get();

        $overdueBorrowings = $user->borrowings()
            ->overdue()
            ->with('book')
            ->get();

        $dueSoonBorrowings = $user->borrowings()
            ->active()
            ->whereBetween('due_at', [Carbon::now(), Carbon::now()->addDays(3)])
            ->with('book')
            ->orderBy('due_at')
            ->get();

        $borrowingHistory = $user->borrowings()
            ->with('book')
            ->latest()
            ->limit(10)
            ->get();

        // Calculate fines for overdue books
        $totalFines = $overdueBorrowings->sum(function ($borrowing) {
            $daysOverdue = $borrowing->due_at->diffInDays(Carbon::now());
            return $daysOverdue * 1.00; // $1 per day fine
        });

        // Personal statistics
        $stats = [
            'active_borrowings' => $activeBorrowings->count(),
            'overdue_books' => $overdueBorrowings->count(),
            'books_due_soon' => $dueSoonBorrowings->count(),
            'total_books_borrowed' => $user->borrowings()->count(),
            'books_returned' => $user->borrowings()->returned()->count(),
            'total_fines' => $totalFines,
            'favorite_genres' => $this->getUserFavoriteGenres($user->id),
            'reading_streak_days' => $this->calculateReadingStreak($user->id)
        ];

        // Recommended books based on borrowing history
        $recommendedBooks = $this->getRecommendedBooks($user->id);

        return $this->successResponse([
            'stats' => $stats,
            'active_borrowings' => $activeBorrowings,
            'overdue_books' => $overdueBorrowings,
            'due_soon' => $dueSoonBorrowings,
            'borrowing_history' => $borrowingHistory,
            'recommended_books' => $recommendedBooks
        ], 'Member dashboard data retrieved successfully');
    }

    /**
     * Get system-wide statistics for reporting
     *
     * @param array $filters
     * @return array
     * @throws BusinessException
     */
    public function getSystemStatistics(array $filters = []): array
    {
        $this->requireRole('librarian');
        $this->logOperation('get_system_statistics', $filters);

        $dateFrom = $filters['date_from'] ?? Carbon::now()->subYear();
        $dateTo = $filters['date_to'] ?? Carbon::now();

        // Overall system metrics
        $systemMetrics = [
            'total_books' => Book::count(),
            'total_copies' => Book::sum('total_copies'),
            'total_users' => User::count(),
            'total_librarians' => User::where('role', 'librarian')->count(),
            'total_members' => User::where('role', 'member')->count(),
            'total_borrowings' => Borrowing::whereBetween('borrowed_at', [$dateFrom, $dateTo])->count(),
            'active_borrowings' => Borrowing::active()->count(),
            'overdue_borrowings' => Borrowing::overdue()->count(),
            'utilization_rate' => $this->calculateUtilizationRate()
        ];

        // Performance metrics
        $performanceMetrics = [
            'average_borrowing_duration' => Borrowing::returned()
                ->whereBetween('borrowed_at', [$dateFrom, $dateTo])
                ->selectRaw('AVG(DATEDIFF(returned_at, borrowed_at)) as avg_days')
                ->value('avg_days'),
            'on_time_return_rate' => $this->calculateOnTimeReturnRate($dateFrom, $dateTo),
            'most_active_borrowers' => User::withCount(['borrowings' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('borrowed_at', [$dateFrom, $dateTo]);
            }])
                ->orderBy('borrowings_count', 'desc')
                ->limit(10)
                ->get(),
            'least_borrowed_books' => Book::withCount(['borrowings' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('borrowed_at', [$dateFrom, $dateTo]);
            }])
                ->orderBy('borrowings_count', 'asc')
                ->limit(10)
                ->get()
        ];

        return $this->successResponse([
            'system_metrics' => $systemMetrics,
            'performance_metrics' => $performanceMetrics,
            'date_range' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d')
            ]
        ], 'System statistics retrieved successfully');
    }

    /**
     * Get user's favorite genres based on borrowing history
     *
     * @param int $userId
     * @return array
     */
    private function getUserFavoriteGenres(int $userId): array
    {
        return Borrowing::where('user_id', $userId)
            ->join('books', 'borrowings.book_id', '=', 'books.id')
            ->selectRaw('books.genre, COUNT(*) as count')
            ->groupBy('books.genre')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->pluck('count', 'genre')
            ->toArray();
    }

    /**
     * Calculate user's reading streak in days
     *
     * @param int $userId
     * @return int
     */
    private function calculateReadingStreak(int $userId): int
    {
        $borrowings = Borrowing::where('user_id', $userId)
            ->returned()
            ->orderBy('returned_at', 'desc')
            ->limit(30)
            ->pluck('returned_at')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->values();

        if ($borrowings->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $currentDate = Carbon::today();

        foreach ($borrowings as $borrowingDate) {
            $date = Carbon::parse($borrowingDate);
            
            if ($date->format('Y-m-d') === $currentDate->format('Y-m-d')) {
                $streak++;
                $currentDate->subDay();
            } elseif ($date->format('Y-m-d') === $currentDate->format('Y-m-d')) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get book recommendations based on user's borrowing history
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getRecommendedBooks(int $userId)
    {
        // Get user's favorite genres
        $favoriteGenres = $this->getUserFavoriteGenres($userId);
        
        if (empty($favoriteGenres)) {
            // If no history, recommend popular books
            return Book::withCount('borrowings')
                ->where('available_copies', '>', 0)
                ->orderBy('borrowings_count', 'desc')
                ->limit(5)
                ->get();
        }

        // Get books from favorite genres that user hasn't borrowed
        $borrowedBookIds = Borrowing::where('user_id', $userId)
            ->pluck('book_id')
            ->toArray();

        return Book::whereIn('genre', array_keys($favoriteGenres))
            ->whereNotIn('id', $borrowedBookIds)
            ->where('available_copies', '>', 0)
            ->withCount('borrowings')
            ->orderBy('borrowings_count', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Calculate system utilization rate
     *
     * @return float
     */
    private function calculateUtilizationRate(): float
    {
        $totalCopies = Book::sum('total_copies');
        $borrowedCopies = Borrowing::active()->count();

        if ($totalCopies === 0) {
            return 0;
        }

        return round(($borrowedCopies / $totalCopies) * 100, 2);
    }

    /**
     * Calculate on-time return rate
     *
     * @param Carbon $dateFrom
     * @param Carbon $dateTo
     * @return float
     */
    private function calculateOnTimeReturnRate(Carbon $dateFrom, Carbon $dateTo): float
    {
        $totalReturned = Borrowing::returned()
            ->whereBetween('borrowed_at', [$dateFrom, $dateTo])
            ->count();

        $onTimeReturned = Borrowing::returned()
            ->whereBetween('borrowed_at', [$dateFrom, $dateTo])
            ->whereRaw('returned_at <= due_at')
            ->count();

        if ($totalReturned === 0) {
            return 0;
        }

        return round(($onTimeReturned / $totalReturned) * 100, 2);
    }
}