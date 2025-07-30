<?php

namespace App\Business\Services;

use App\Business\Exceptions\BusinessException;
use App\Business\Exceptions\UnauthorizedException;
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Data\Repositories\Contracts\BorrowingRepositoryInterface;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
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

        // Get basic counts using repository methods
        $totalBooks = $this->bookRepository->getTotalCount();
        $activeBorrowings = $this->borrowingRepository->getActiveBorrowingsCount();
        $overdueBorrowings = $this->borrowingRepository->getOverdueCount();
        $totalMembers = $this->userRepository->getMembersCount();
        
        // Get books due today
        $booksDueToday = $this->borrowingRepository->findDueToday()->count();

        // Get users with overdue books
        $usersWithOverdueBooks = $this->userRepository->getUsersWithOverdueBorrowings();

        // Get recent borrowings
        $recentBorrowings = $this->borrowingRepository->getRecent(10);

        $stats = [
            'total_books' => $totalBooks,
            'total_borrowed_books' => $activeBorrowings,
            'books_due_today' => $booksDueToday,
            'overdue_books' => $overdueBorrowings,
            'total_members' => $totalMembers,
            'members_with_overdue_books' => $usersWithOverdueBooks->count()
        ];

        return $this->successResponse([
            'stats' => $stats,
            'members_with_overdue_books' => $usersWithOverdueBooks,
            'recent_borrowings' => $recentBorrowings
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
        $this->logOperation('get_member_dashboard', ['user_id' => $user->id]);

        // Get user's borrowing data using repository
        $userStats = $this->userRepository->getUserBorrowingStats($user->id);
        $activeBorrowings = $this->borrowingRepository->getUserActiveBorrowings($user->id);
        $overdueBorrowings = $this->borrowingRepository->getUserOverdueBorrowings($user->id);
        $borrowingHistory = $this->borrowingRepository->getUserHistory($user->id, 10);

        // Calculate basic statistics
        $stats = [
            'active_borrowings' => $userStats['active_borrowings'],
            'overdue_books' => $userStats['overdue_borrowings'],
            'total_books_borrowed' => $userStats['total_borrowings']
        ];

        return $this->successResponse([
            'stats' => $stats,
            'active_borrowings' => $activeBorrowings,
            'overdue_books' => $overdueBorrowings,
            'borrowing_history' => $borrowingHistory
        ], 'Member dashboard data retrieved successfully');
    }
}