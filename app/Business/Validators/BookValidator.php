<?php

namespace App\Business\Validators;

use App\Models\Book;
use App\Business\Exceptions\BusinessException;

class BookValidator
{
    /**
     * Validate ISBN uniqueness
     *
     * @param string|null $isbn
     * @param int|null $excludeId
     * @throws BusinessException
     */
    public static function validateUniqueISBN(?string $isbn, ?int $excludeId = null): void
    {
        if (!$isbn) {
            return;
        }

        $query = Book::where('isbn', $isbn);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new BusinessException('A book with this ISBN already exists');
        }
    }

    /**
     * Validate total copies count
     *
     * @param int $totalCopies
     * @param int $borrowedCopies
     * @throws BusinessException
     */
    public static function validateTotalCopies(int $totalCopies, int $borrowedCopies = 0): void
    {
        if ($totalCopies <= 0) {
            throw new BusinessException('Total copies must be greater than 0');
        }

        if ($totalCopies < $borrowedCopies) {
            throw new BusinessException("Cannot set total copies below borrowed copies ({$borrowedCopies})");
        }
    }

    /**
     * Validate book availability for borrowing
     *
     * @param Book $book
     * @throws BusinessException
     */
    public static function validateAvailability(Book $book): void
    {
        if (!$book->isAvailable()) {
            throw new BusinessException("Book '{$book->title}' is not available for borrowing");
        }
    }

    /**
     * Validate book can be deleted
     *
     * @param Book $book
     * @throws BusinessException
     */
    public static function validateCanDelete(Book $book): void
    {
        if ($book->borrowings()->active()->exists()) {
            throw new BusinessException('Cannot delete book with active borrowings');
        }
    }

    /**
     * Validate book data completeness
     *
     * @param array $bookData
     * @throws BusinessException
     */
    public static function validateBookData(array $bookData): void
    {
        $requiredFields = ['title', 'author', 'genre', 'total_copies'];
        
        foreach ($requiredFields as $field) {
            if (empty($bookData[$field])) {
                throw new BusinessException("Field '{$field}' is required");
            }
        }

        // Validate specific field formats
        if (isset($bookData['isbn']) && !self::isValidISBN($bookData['isbn'])) {
            throw new BusinessException('Invalid ISBN format');
        }

        if (isset($bookData['total_copies']) && (!is_numeric($bookData['total_copies']) || $bookData['total_copies'] < 1)) {
            throw new BusinessException('Total copies must be a positive number');
        }
    }

    /**
     * Validate ISBN format (basic validation)
     *
     * @param string $isbn
     * @return bool
     */
    private static function isValidISBN(string $isbn): bool
    {
        // Remove hyphens and spaces
        $isbn = preg_replace('/[\s-]/', '', $isbn);
        
        // Check if it's 10 or 13 digits
        return preg_match('/^(?:\d{10}|\d{13})$/', $isbn);
    }

    /**
     * Validate genre is from allowed list
     *
     * @param string $genre
     * @throws BusinessException
     */
    public static function validateGenre(string $genre): void
    {
        $allowedGenres = [
            'Fiction', 'Non-Fiction', 'Mystery', 'Romance', 'Sci-Fi', 
            'Fantasy', 'Biography', 'History', 'Science', 'Technology',
            'Health', 'Business', 'Education', 'Children', 'Young Adult'
        ];

        if (!in_array($genre, $allowedGenres)) {
            throw new BusinessException('Invalid genre. Allowed genres: ' . implode(', ', $allowedGenres));
        }
    }

    /**
     * Validate search parameters
     *
     * @param array $searchParams
     * @throws BusinessException
     */
    public static function validateSearchParams(array $searchParams): void
    {
        if (isset($searchParams['per_page'])) {
            $perPage = (int) $searchParams['per_page'];
            if ($perPage < 1 || $perPage > 100) {
                throw new BusinessException('Per page must be between 1 and 100');
            }
        }

        if (isset($searchParams['sort_by'])) {
            $allowedSortFields = ['title', 'author', 'genre', 'created_at', 'updated_at'];
            if (!in_array($searchParams['sort_by'], $allowedSortFields)) {
                throw new BusinessException('Invalid sort field');
            }
        }

        if (isset($searchParams['sort_order'])) {
            if (!in_array(strtolower($searchParams['sort_order']), ['asc', 'desc'])) {
                throw new BusinessException('Sort order must be asc or desc');
            }
        }
    }
}