<?php

namespace App\Business\Services;

use App\Business\Exceptions\BookAlreadyBorrowedException;
use App\Business\Exceptions\BookAlreadyReturnedException;
use App\Business\Exceptions\BookNotAvailableException;
use App\Business\Exceptions\BusinessException;
use App\Business\Exceptions\UnauthorizedException;
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
        $this->ensureModelExists($borrowing, 'Borrowing');

        $user = auth()->user();

        // Members can only see their own borrowings
        if ($user->isMember() && $borrowing->user_id !== $user->id) {
            throw new UnauthorizedException('You can only view your own borrowings');
        }

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
        $this->ensureModelExists($book, 'Book');

        $borrower = $this->userRepository->find($borrowerUserId);
        $this->ensureModelExists($borrower, 'User');

        $this->logOperation('borrow_book', [
            'book_id' => $bookId,
            'borrower_id' => $borrowerUserId,
            'requested_by' => $currentUser->id
        ]);

        return $this->executeTransaction(function () use ($book, $borrower, $currentUser) {
            // Validate business rules
            $this->validateBusinessRules([
                'book_available' => function() use ($book) {
                    if (!$book->isAvailable()) {
                        throw new BookNotAvailableException($book->title);
                    }
                    return true;
                },
                'no_duplicate_borrowing' => function() use ($book, $borrower) {
                    $existingBorrowing = $this->borrowingRepository->findActiveBorrowingByUserAndBook($borrower->id, $book->id);
                    
                    if ($existingBorrowing) {
                        throw new BookAlreadyBorrowedException();
                    }
                    return true;
                },
                'borrower_limit' => function() use ($borrower) {
                    $activeBorrowings = $this->borrowingRepository->countActiveBorrowingsByUser($borrower->id);
                    $maxBorrowings = $borrower->isLibrarian() ? 10 : 5; // Business rule: different limits
                    
                    if ($activeBorrowings >= $maxBorrowings) {
                        return "User has reached maximum borrowing limit ({$maxBorrowings} books)";
                    }
                    return true;
                }
            ]);

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
        
        $borrowing = Borrowing::with(['user', 'book'])->find($borrowingId);
        $this->ensureModelExists($borrowing, 'Borrowing');

        $this->logOperation('return_book', [
            'borrowing_id' => $borrowingId,
            'book_id' => $borrowing->book_id,
            'user_id' => $borrowing->user_id
        ]);

        return $this->executeTransaction(function () use ($borrowing) {
            // Validate business rules
            $this->validateBusinessRules([
                'not_already_returned' => function() use ($borrowing) {
                    if ($borrowing->isReturned()) {
                        throw new BookAlreadyReturnedException();
                    }
                    return true;
                }
            ]);

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
        
        $borrowing = Borrowing::with(['user', 'book'])->find($borrowingId);
        $this->ensureModelExists($borrowing, 'Borrowing');

        $user = auth()->user();

        // Check authorization: user can extend their own borrowings, librarians can extend any
        if (!$user->isLibrarian() && $borrowing->user_id !== $user->id) {
            throw new UnauthorizedException('You can only extend your own borrowings');
        }

        $this->logOperation('extend_borrowing', [
            'borrowing_id' => $borrowingId,
            'days' => $days,
            'requested_by' => $user->id
        ]);

        return $this->executeTransaction(function () use ($borrowing, $days, $user) {
            // Validate business rules
            $this->validateBusinessRules([
                'not_returned' => function() use ($borrowing) {
                    if ($borrowing->isReturned()) {
                        return 'Cannot extend a returned borrowing';
                    }
                    return true;
                },
                'extension_limit' => function() use ($borrowing, $days) {
                    $maxExtensionDays = 14; // Business rule: max 2 weeks extension
                    $currentExtension = $borrowing->borrowed_at->diffInDays($borrowing->due_at) - 14; // Original is 14 days
                    
                    if (($currentExtension + $days) > $maxExtensionDays) {
                        return "Extension would exceed maximum limit of {$maxExtensionDays} days";
                    }
                    return true;
                },
                'librarian_permission' => function() use ($borrowing, $user) {
                    // Only librarians can extend overdue books
                    if ($borrowing->due_at < Carbon::now() && !$user->isLibrarian()) {
                        return 'Only librarians can extend overdue borrowings';
                    }
                    return true;
                }
            ]);

            $oldDueDate = $borrowing->due_at;
            $newDueDate = $borrowing->due_at->addDays($days);

            $borrowing->update([
                'due_at' => $newDueDate
            ]);

            return $this->successResponse([
                'borrowing' => $borrowing->fresh(['user', 'book']),
                'old_due_date' => $oldDueDate->format('Y-m-d H:i:s'),
                'new_due_date' => $newDueDate->format('Y-m-d H:i:s'),
                'extension_days' => $days
            ], 'Borrowing extended successfully');
        });
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

        $overdueBorrowings = Borrowing::with(['user', 'book'])
            ->overdue()
            ->orderBy('due_at')
            ->get();

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

        $query = Borrowing::query();

        // Apply date filters
        if (!empty($filters['from_date'])) {
            $query->where('borrowed_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('borrowed_at', '<=', $filters['to_date']);
        }

        $stats = [
            'total_borrowings' => $query->count(),
            'active_borrowings' => Borrowing::active()->count(),
            'returned_borrowings' => Borrowing::returned()->count(),
            'overdue_borrowings' => Borrowing::overdue()->count(),
            'borrowings_by_month' => Borrowing::selectRaw('YEAR(borrowed_at) as year, MONTH(borrowed_at) as month, COUNT(*) as count')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
            'most_active_borrowers' => User::withCount(['borrowings'])
                ->orderBy('borrowings_count', 'desc')
                ->limit(10)
                ->get(),
            'average_borrowing_duration' => Borrowing::returned()
                ->selectRaw('AVG(DATEDIFF(returned_at, borrowed_at)) as avg_days')
                ->value('avg_days')
        ];

        return $this->successResponse($stats, 'Borrowing statistics retrieved successfully');
    }
}