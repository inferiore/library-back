<?php

namespace App\Business\Services;

use App\Business\Exceptions\BusinessException;
use App\Business\Exceptions\BookHasActiveBorrowingsException;
use App\Business\Exceptions\UnauthorizedException;
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;

class BookService extends BaseService
{
    protected BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }
    /**
     * Get paginated list of books with optional search
     *
     * @param array $params
     * @return array
     */
    public function getBooks(array $params = []): array
    {
        $this->logOperation('get_books', ['search' => $params['search'] ?? null]);

        $perPage = $params['per_page'] ?? 15;
        
        if (!empty($params['search'])) {
            $books = $this->bookRepository->search($params['search'], $params, $perPage);
        } else {
            $books = $this->bookRepository->findByCriteria($params, [], $perPage);
        }

        return $this->successResponse([
            'books' => $books->items(),
            'pagination' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ]
        ], 'Books retrieved successfully');
    }

    /**
     * Get a specific book by ID
     *
     * @param int $bookId
     * @return array
     * @throws BusinessException
     */
    public function getBook(int $bookId): array
    {
        $book = $this->bookRepository->find($bookId);
        $this->ensureModelExists($book, 'Book');

        $this->logOperation('get_book', ['book_id' => $bookId]);

        return $this->successResponse([
            'book' => $book
        ], 'Book retrieved successfully');
    }

    /**
     * Create a new book
     *
     * @param array $bookData
     * @return array
     * @throws BusinessException
     */
    public function createBook(array $bookData): array
    {
        $this->requireRole('librarian');
        $this->logOperation('create_book', ['title' => $bookData['title']]);

        return $this->executeTransaction(function () use ($bookData) {
            // Validate business rules
            $this->validateBusinessRules([
                'isbn_unique' => function() use ($bookData) {
                    if (isset($bookData['isbn']) && $this->bookRepository->findByISBN($bookData['isbn'])) {
                        return 'A book with this ISBN already exists';
                    }
                    return true;
                },
                'valid_copies' => fn() => ($bookData['total_copies'] ?? 0) > 0 
                    ? true : 'Total copies must be greater than 0'
            ], $bookData);

            $book = $this->bookRepository->create([
                'title' => $bookData['title'],
                'author' => $bookData['author'],
                'genre' => $bookData['genre'],
                'isbn' => $bookData['isbn'] ?? null,
                'total_copies' => $bookData['total_copies'],
                'available_copies' => $bookData['total_copies'],
            ]);

            return $this->successResponse([
                'book' => $book
            ], 'Book created successfully');
        });
    }

    /**
     * Update an existing book
     *
     * @param int $bookId
     * @param array $bookData
     * @return array
     * @throws BusinessException
     */
    public function updateBook(int $bookId, array $bookData): array
    {
        $this->requireRole('librarian');
        
        $book = $this->bookRepository->find($bookId);
        $this->ensureModelExists($book, 'Book');

        $this->logOperation('update_book', ['book_id' => $bookId, 'title' => $bookData['title'] ?? null]);

        return $this->executeTransaction(function () use ($book, $bookData, $bookId) {
            // Validate business rules
            $this->validateBusinessRules([
                'isbn_unique' => function() use ($bookData, $book) {
                    if (isset($bookData['isbn']) && $bookData['isbn'] !== $book->isbn) {
                        $existingBook = $this->bookRepository->findByISBN($bookData['isbn']);
                        if ($existingBook && $existingBook->id !== $book->id) {
                            return 'A book with this ISBN already exists';
                        }
                    }
                    return true;
                },
                'valid_copies' => function() use ($bookData, $book) {
                    if (isset($bookData['total_copies'])) {
                        $borrowedCopies = $book->borrowings()->active()->count();
                        if ($bookData['total_copies'] < $borrowedCopies) {
                            return "Cannot set total copies below borrowed copies ({$borrowedCopies})";
                        }
                    }
                    return true;
                }
            ], $bookData);

            // Update basic fields
            $updateFields = ['title', 'author', 'genre', 'isbn'];
            $updateData = array_intersect_key($bookData, array_flip($updateFields));
            
            $this->bookRepository->update($book->id, $updateData);

            // Handle total copies update
            if (isset($bookData['total_copies'])) {
                $borrowedCopies = $book->borrowings()->active()->count();
                $this->bookRepository->update($book->id, [
                    'total_copies' => $bookData['total_copies'],
                    'available_copies' => max(0, $bookData['total_copies'] - $borrowedCopies)
                ]);
            }

            return $this->successResponse([
                'book' => $this->bookRepository->find($bookId)
            ], 'Book updated successfully');
        });
    }

    /**
     * Delete a book
     *
     * @param int $bookId
     * @return array
     * @throws BusinessException
     */
    public function deleteBook(int $bookId): array
    {
        $this->requireRole('librarian');
        
        $book = $this->bookRepository->find($bookId);
        $this->ensureModelExists($book, 'Book');

        $this->logOperation('delete_book', ['book_id' => $bookId, 'title' => $book->title]);

        return $this->executeTransaction(function () use ($book) {
            // Validate business rules
            $this->validateBusinessRules([
                'no_active_borrowings' => function() use ($book) {
                    if ($book->borrowings()->active()->exists()) {
                        throw new BookHasActiveBorrowingsException();
                    }
                    return true;
                }
            ]);

            $this->bookRepository->delete($book->id);

            return $this->successResponse(null, 'Book deleted successfully');
        });
    }

    /**
     * Check if a book is available for borrowing
     *
     * @param int $bookId
     * @return array
     * @throws BusinessException
     */
    public function checkAvailability(int $bookId): array
    {
        $book = $this->bookRepository->find($bookId);
        $this->ensureModelExists($book, 'Book');

        $this->logOperation('check_availability', ['book_id' => $bookId]);

        $isAvailable = $book->isAvailable();
        $borrowedCopies = $book->borrowings()->active()->count();

        return $this->successResponse([
            'book_id' => $bookId,
            'title' => $book->title,
            'is_available' => $isAvailable,
            'total_copies' => $book->total_copies,
            'available_copies' => $book->available_copies,
            'borrowed_copies' => $borrowedCopies
        ], $isAvailable ? 'Book is available' : 'Book is not available');
    }

    /**
     * Get book statistics
     *
     * @return array
     * @throws BusinessException
     */
    public function getBookStatistics(): array
    {
        $this->requireRole('librarian');
        $this->logOperation('get_book_statistics');

        $summaryStats = $this->bookRepository->getSummaryStats();
        $genreStats = $this->bookRepository->getGenreStatistics();

        $stats = array_merge($summaryStats, [
            'genres' => $genreStats
        ]);

        return $this->successResponse($stats, 'Book statistics retrieved successfully');
    }

    /**
     * Search books by multiple criteria
     *
     * @param array $searchCriteria
     * @return array
     */
    public function searchBooks(array $searchCriteria): array
    {
        $this->logOperation('search_books', $searchCriteria);

        $perPage = $searchCriteria['per_page'] ?? 15;
        $sortBy = $searchCriteria['sort_by'] ?? 'title';
        $sortOrder = $searchCriteria['sort_order'] ?? 'asc';
        
        $sorting = [$sortBy => $sortOrder];
        
        if (!empty($searchCriteria['query'])) {
            $books = $this->bookRepository->search($searchCriteria['query'], $searchCriteria, $perPage);
        } else {
            $books = $this->bookRepository->findByCriteria($searchCriteria, $sorting, $perPage);
        }

        return $this->successResponse([
            'books' => $books->items(),
            'pagination' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
            'search_criteria' => $searchCriteria
        ], 'Search completed successfully');
    }
}