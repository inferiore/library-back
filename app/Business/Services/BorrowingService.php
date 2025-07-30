<?php

namespace App\Business\Services;

use App\Business\Exceptions\BookAlreadyBorrowedException;
use App\Business\Exceptions\BookAlreadyReturnedException;
use App\Business\Exceptions\BookNotAvailableException;
use App\Business\Exceptions\BusinessException;
use App\Business\Exceptions\UnauthorizedException;
use App\Business\Validators\BorrowingValidator;
use App\Business\Validators\ModelValidator;
use App\Business\Validators\UserValidator;
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Data\Repositories\Contracts\BorrowingRepositoryInterface;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Carbon\Carbon;

class BorrowingService extends BaseService
{
    protected BorrowingRepositoryInterface $borrowingRepository;
    protected BookRepositoryInterface $bookRepository;
    protected UserRepositoryInterface $userRepository;

    public function __construct(
        BorrowingRepositoryInterface $borrowingRepository,
        BookRepositoryInterface $bookRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->borrowingRepository = $borrowingRepository;
        $this->bookRepository = $bookRepository;
        $this->userRepository = $userRepository;
    }
    /**
     * Get paginated list of borrowings based on user role
     *
     * @param array $params
     * @return array
     * @throws BusinessException
     */
    public function getBorrowings(array $params = []): array
    {
        $this->requireAuth();
        
        $user = auth()->user();
        $this->logOperation('get_borrowings', ['user_role' => $user->role]);

        // Members can only see their own borrowings
        if ($user->isMember()) {
            $params['user_id'] = $user->id;
        }

        // Validate filters using dedicated validator
        BorrowingValidator::validateBorrowingFilters($params);
        
        $perPage = $params['per_page'] ?? 15;
        $borrowings = $this->borrowingRepository->with(['user', 'book'])->findByFilters($params, $perPage);

        return $this->successResponse([
            'borrowings' => $borrowings->items(),
            'pagination' => [
                'current_page' => $borrowings->currentPage(),
                'last_page' => $borrowings->lastPage(),
                'per_page' => $borrowings->perPage(),
                'total' => $borrowings->total(),
            ]
        ], 'Borrowings retrieved successfully');
    }

    /**
     * Get a specific borrowing by ID
     *
     * @param int $borrowingId
     * @return array
     * @throws BusinessException
     */
    public function getBorrowing(int $borrowingId): array
    {
        $this->requireAuth();
        
        $borrowing = $this->borrowingRepository->with(['user', 'book'])->find($borrowingId);
        ModelValidator::validateExists($borrowing, 'Borrowing');

        $user = auth()->user();

        // Validate access using dedicated validator
        BorrowingValidator::validateBorrowingAccess($user, $borrowing);

        $this->logOperation('get_borrowing', ['borrowing_id' => $borrowingId]);

        return $this->successResponse([
            'borrowing' => $borrowing
        ], 'Borrowing retrieved successfully');
    }

    /**
     * Borrow a book
     *
     * @param int $bookId
     * @param int|null $userId
     * @return array
     * @throws BusinessException
     */
    public function borrowBook(int $bookId, ?int $userId = null): array
    {
        $this->requireAuth();
        
        $currentUser = auth()->user();
        $borrowerUserId = $userId ?? $currentUser->id;

        // Only librarians can borrow books for other users
        if ($userId && $userId !== $currentUser->id) {
            $this->requireRole('librarian');
        }

        $book = $this->bookRepository->find($bookId);
        ModelValidator::validateExists($book, 'Book');

        $borrower = $this->userRepository->find($borrowerUserId);
        ModelValidator::validateExists($borrower, 'User');

        $this->logOperation('borrow_book', [
            'book_id' => $bookId,
            'borrower_id' => $borrowerUserId,
            'requested_by' => $currentUser->id
        ]);

        return $this->executeTransaction(function () use ($book, $borrower, $currentUser) {
            // Validate using dedicated validators
            BorrowingValidator::validateCanBorrow($borrower, $book);

            // Calculate dates
            $borrowedAt = Carbon::now();
            $dueAt = $borrowedAt->copy()->addWeeks(2); // 2-week loan period

            // Create borrowing record
            $borrowing = $this->borrowingRepository->create([
                'user_id' => $borrower->id,
                'book_id' => $book->id,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
            ]);

            // Update book availability
            $this->bookRepository->updateAvailability($book->id, -1);

            $borrowing->load(['user', 'book']);

            return $this->successResponse([
                'borrowing' => $borrowing,
                'due_date' => $dueAt->format('Y-m-d H:i:s'),
                'days_until_due' => $borrowedAt->diffInDays($dueAt)
            ], 'Book borrowed successfully');
        });
    }

    /**
     * Return a borrowed book
     *
     * @param int $borrowingId
     * @return array
     * @throws BusinessException
     */
    public function returnBook(int $borrowingId): array
    {
        $this->requireRole('librarian');
        
        $borrowing = $this->borrowingRepository->with(['user', 'book'])->find($borrowingId);
        ModelValidator::validateExists($borrowing, 'Borrowing');

        $this->logOperation('return_book', [
            'borrowing_id' => $borrowingId,
            'book_id' => $borrowing->book_id,
            'user_id' => $borrowing->user_id
        ]);

        return $this->executeTransaction(function () use ($borrowing) {
            // Validate using dedicated validator
            BorrowingValidator::validateCanReturn($borrowing);

            $returnedAt = Carbon::now();
            $wasOverdue = $borrowing->due_at < $returnedAt;
            $daysOverdue = $wasOverdue ? $borrowing->due_at->diffInDays($returnedAt) : 0;

            // Update borrowing record
            $borrowing->update([
                'returned_at' => $returnedAt,
            ]);

            // Update book availability
            $borrowing->book->increment('available_copies');

            // Calculate fine if overdue (business rule: $1 per day)
            $fine = $wasOverdue ? $daysOverdue * 1.00 : 0;

            return $this->successResponse([
                'borrowing' => $borrowing->fresh(['user', 'book']),
                'returned_at' => $returnedAt->format('Y-m-d H:i:s'),
                'was_overdue' => $wasOverdue,
                'days_overdue' => $daysOverdue,
                'fine_amount' => $fine
            ], 'Book returned successfully');
        });
    }

    /**
     * Extend borrowing period
     *
     * @param int $borrowingId
     * @param int $days
     * @return array
     * @throws BusinessException
     */
    public function extendBorrowing(int $borrowingId, int $days = 7): array
    {
        $this->requireAuth();
        
        $borrowing = $this->borrowingRepository->with(['user', 'book'])->find($borrowingId);
        ModelValidator::validateExists($borrowing, 'Borrowing');

        $user = auth()->user();

        // Validate access using dedicated validator
        BorrowingValidator::validateBorrowingAccess($user, $borrowing);

        $this->logOperation('extend_borrowing', [
            'borrowing_id' => $borrowingId,
            'days' => $days,
            'requested_by' => $user->id
        ]);

        // Validate using dedicated validators
        BorrowingValidator::validateExtensionDays($days);
        BorrowingValidator::validateCanExtend($borrowing, $days);

        $oldDueDate = $borrowing->due_at;
        $newDueDate = $borrowing->due_at->addDays($days);

        // Single write operation - no transaction needed
        $borrowing->update([
            'due_at' => $newDueDate
        ]);

        return $this->successResponse([
            'borrowing' => $borrowing->fresh(['user', 'book']),
            'old_due_date' => $oldDueDate->format('Y-m-d H:i:s'),
            'new_due_date' => $newDueDate->format('Y-m-d H:i:s'),
            'extension_days' => $days
        ], 'Borrowing extended successfully');
    }

    /**
     * Get overdue borrowings
     *
     * @return array
     * @throws BusinessException
     */
    public function getOverdueBorrowings(): array
    {
        $this->requireRole('librarian');
        $this->logOperation('get_overdue_borrowings');

        $overdueBorrowings = $this->borrowingRepository->getOverdueCollection();

        $overdueStats = [
            'total_overdue' => $overdueBorrowings->count(),
            'total_fine_amount' => $overdueBorrowings->sum(function ($borrowing) {
                $daysOverdue = $borrowing->due_at->diffInDays(Carbon::now());
                return $daysOverdue * 1.00; // $1 per day fine
            }),
            'average_days_overdue' => $overdueBorrowings->avg(function ($borrowing) {
                return $borrowing->due_at->diffInDays(Carbon::now());
            })
        ];

        return $this->successResponse([
            'overdue_borrowings' => $overdueBorrowings,
            'statistics' => $overdueStats
        ], 'Overdue borrowings retrieved successfully');
    }

    /**
     * Get borrowing statistics
     *
     * @param array $filters
     * @return array
     * @throws BusinessException
     */
    public function getBorrowingStatistics(array $filters = []): array
    {
        $this->requireRole('librarian');
        $this->logOperation('get_borrowing_statistics', $filters);

        // Validate filters using dedicated validator
        BorrowingValidator::validateStatisticsFilters($filters);

        // Get statistics using repository methods
        $statistics = $this->borrowingRepository->getStatistics(
            $filters['from_date'] ?? '1900-01-01', 
            $filters['to_date'] ?? now()->format('Y-m-d')
        );

        $stats = [
            'total_borrowings' => $statistics['total_borrowings'],
            'active_borrowings' => $statistics['active_borrowings'],
            'returned_borrowings' => $statistics['returned_borrowings'],
            'overdue_borrowings' => $statistics['overdue_borrowings'],
            'borrowings_by_month' => $this->borrowingRepository->getMonthlyTrends(12),
            'most_active_borrowers' => $this->userRepository->getMostActiveBorrowers(10),
            'average_borrowing_duration' => $statistics['average_duration_days']
        ];

        return $this->successResponse($stats, 'Borrowing statistics retrieved successfully');
    }
}