<?php

namespace App\Business\Validators;

use App\Models\Book;
use App\Models\User;
use App\Models\Borrowing;
use App\Business\Exceptions\BusinessException;
use App\Business\Exceptions\BookNotAvailableException;
use App\Business\Exceptions\BookAlreadyBorrowedException;
use Carbon\Carbon;

class BorrowingValidator
{
    /**
     * Validate user can borrow a book
     *
     * @param User $user
     * @param Book $book
     * @throws BusinessException
     */
    public static function validateCanBorrow(User $user, Book $book): void
    {
        // Check if book is available
        if (!$book->isAvailable()) {
            throw new BookNotAvailableException($book->title);
        }

        // Check if user already has this book borrowed
        $existingBorrowing = Borrowing::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->exists();

        if ($existingBorrowing) {
            throw new BookAlreadyBorrowedException();
        }

        // Check borrowing limit
        self::validateBorrowingLimit($user);
    }

    /**
     * Validate user hasn't exceeded borrowing limit
     *
     * @param User $user
     * @throws BusinessException
     */
    public static function validateBorrowingLimit(User $user): void
    {
        $activeBorrowings = $user->borrowings()->active()->count();
        $maxBorrowings = $user->isLibrarian() ? 10 : 5; // Business rule: different limits

        if ($activeBorrowings >= $maxBorrowings) {
            throw new BusinessException("User has reached maximum borrowing limit ({$maxBorrowings} books)");
        }
    }

    /**
     * Validate borrowing can be returned
     *
     * @param Borrowing $borrowing
     * @throws BusinessException
     */
    public static function validateCanReturn(Borrowing $borrowing): void
    {
        if ($borrowing->isReturned()) {
            throw new BusinessException('Book has already been returned');
        }
    }

    /**
     * Validate borrowing can be extended
     *
     * @param Borrowing $borrowing
     * @param int $days
     * @throws BusinessException
     */
    public static function validateCanExtend(Borrowing $borrowing, int $days): void
    {
        if ($borrowing->isReturned()) {
            throw new BusinessException('Cannot extend a returned borrowing');
        }

        // Check extension limit
        $maxExtensionDays = 14; // Business rule: max 2 weeks extension
        $currentExtension = $borrowing->borrowed_at->diffInDays($borrowing->due_at) - 14; // Original is 14 days
        
        if (($currentExtension + $days) > $maxExtensionDays) {
            throw new BusinessException("Extension would exceed maximum limit of {$maxExtensionDays} days");
        }

        // Check if borrowing is overdue and user is not librarian
        if ($borrowing->due_at < Carbon::now() && !auth()->user()->isLibrarian()) {
            throw new BusinessException('Only librarians can extend overdue borrowings');
        }
    }

    /**
     * Validate extension days
     *
     * @param int $days
     * @throws BusinessException
     */
    public static function validateExtensionDays(int $days): void
    {
        if ($days < 1 || $days > 14) {
            throw new BusinessException('Extension days must be between 1 and 14');
        }
    }

    /**
     * Validate user can access borrowing
     *
     * @param User $user
     * @param Borrowing $borrowing
     * @throws BusinessException
     */
    public static function validateBorrowingAccess(User $user, Borrowing $borrowing): void
    {
        if ($user->isMember() && $borrowing->user_id !== $user->id) {
            throw new BusinessException('You can only access your own borrowings');
        }
    }

    /**
     * Validate borrowing dates
     *
     * @param Carbon $borrowedAt
     * @param Carbon $dueAt
     * @throws BusinessException
     */
    public static function validateBorrowingDates(Carbon $borrowedAt, Carbon $dueAt): void
    {
        if ($dueAt <= $borrowedAt) {
            throw new BusinessException('Due date must be after borrowed date');
        }

        if ($borrowedAt->isFuture()) {
            throw new BusinessException('Borrowed date cannot be in the future');
        }

        $maxLoanPeriod = 30; // Business rule: max 30 days loan
        if ($borrowedAt->diffInDays($dueAt) > $maxLoanPeriod) {
            throw new BusinessException("Loan period cannot exceed {$maxLoanPeriod} days");
        }
    }

    /**
     * Validate borrowing filters for listing
     *
     * @param array $filters
     * @throws BusinessException
     */
    public static function validateBorrowingFilters(array $filters): void
    {
        if (isset($filters['status'])) {
            $allowedStatuses = ['active', 'returned', 'overdue'];
            if (!in_array($filters['status'], $allowedStatuses)) {
                throw new BusinessException('Invalid status filter. Allowed: ' . implode(', ', $allowedStatuses));
            }
        }

        if (isset($filters['per_page'])) {
            $perPage = (int) $filters['per_page'];
            if ($perPage < 1 || $perPage > 100) {
                throw new BusinessException('Per page must be between 1 and 100');
            }
        }

        if (isset($filters['user_id'])) {
            $userId = (int) $filters['user_id'];
            if ($userId < 1) {
                throw new BusinessException('Invalid user ID');
            }

            // Only librarians can filter by user_id
            if (!auth()->user()->isLibrarian()) {
                throw new BusinessException('Only librarians can filter by user ID');
            }
        }

        if (isset($filters['book_id'])) {
            $bookId = (int) $filters['book_id'];
            if ($bookId < 1) {
                throw new BusinessException('Invalid book ID');
            }
        }
    }

    /**
     * Validate statistics date range
     *
     * @param array $filters
     * @throws BusinessException
     */
    public static function validateStatisticsFilters(array $filters): void
    {
        if (isset($filters['from_date'])) {
            try {
                $fromDate = Carbon::parse($filters['from_date']);
            } catch (\Exception $e) {
                throw new BusinessException('Invalid from_date format');
            }

            if ($fromDate->isFuture()) {
                throw new BusinessException('From date cannot be in the future');
            }
        }

        if (isset($filters['to_date'])) {
            try {
                $toDate = Carbon::parse($filters['to_date']);
            } catch (\Exception $e) {
                throw new BusinessException('Invalid to_date format');
            }

            if ($toDate->isFuture()) {
                throw new BusinessException('To date cannot be in the future');
            }
        }

        if (isset($filters['from_date']) && isset($filters['to_date'])) {
            $fromDate = Carbon::parse($filters['from_date']);
            $toDate = Carbon::parse($filters['to_date']);

            if ($fromDate->gt($toDate)) {
                throw new BusinessException('From date must be before to date');
            }

            if ($fromDate->diffInDays($toDate) > 365) {
                throw new BusinessException('Date range cannot exceed 365 days');
            }
        }
    }

    /**
     * Validate fine calculation parameters
     *
     * @param Borrowing $borrowing
     * @return float
     */
    public static function calculateFine(Borrowing $borrowing): float
    {
        if (!$borrowing->due_at || $borrowing->due_at >= Carbon::now()) {
            return 0.0;
        }

        $daysOverdue = $borrowing->due_at->diffInDays(Carbon::now());
        $dailyFine = 1.00; // Business rule: $1 per day

        return $daysOverdue * $dailyFine;
    }

    /**
     * Validate renewal eligibility
     *
     * @param Borrowing $borrowing
     * @throws BusinessException
     */
    public static function validateRenewalEligibility(Borrowing $borrowing): void
    {
        if ($borrowing->isReturned()) {
            throw new BusinessException('Cannot renew a returned borrowing');
        }

        // Check if book has pending reservations
        // This would be implemented if we had a reservation system
        
        // Check renewal limit
        $renewalCount = $borrowing->renewal_count ?? 0;
        $maxRenewals = 2; // Business rule: max 2 renewals

        if ($renewalCount >= $maxRenewals) {
            throw new BusinessException("Maximum renewals ({$maxRenewals}) exceeded");
        }

        // Check if borrowing is significantly overdue
        if ($borrowing->due_at < Carbon::now()->subDays(7)) {
            throw new BusinessException('Cannot renew borrowings that are more than 7 days overdue');
        }
    }
}