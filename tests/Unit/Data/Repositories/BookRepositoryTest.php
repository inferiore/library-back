<?php

namespace Tests\Unit\Data\Repositories;

use App\Data\Repositories\BookRepository;
use App\Models\Book;
use App\Models\User;
use App\Models\Borrowing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected BookRepository $bookRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookRepository = new BookRepository();
    }

    public function test_can_find_book_by_isbn()
    {
        $book = Book::factory()->create([
            'isbn' => '9781234567890',
            'title' => 'Test Book'
        ]);

        $foundBook = $this->bookRepository->findByISBN('9781234567890');

        $this->assertNotNull($foundBook);
        $this->assertEquals($book->id, $foundBook->id);
        $this->assertEquals('9781234567890', $foundBook->isbn);
    }

    public function test_returns_null_when_book_not_found_by_isbn()
    {
        $result = $this->bookRepository->findByISBN('9999999999999');

        $this->assertNull($result);
    }

    public function test_can_find_available_books()
    {
        Book::factory()->create(['available_copies' => 0]);
        Book::factory()->count(3)->create(['available_copies' => 2]);

        $availableBooks = $this->bookRepository->findAvailable();

        $this->assertEquals(3, $availableBooks->total());
    }

    public function test_can_find_books_by_genre()
    {
        Book::factory()->count(2)->create(['genre' => 'Science Fiction']);
        Book::factory()->count(3)->create(['genre' => 'Fantasy']);
        Book::factory()->create(['genre' => 'Mystery']);

        $sciFiBooks = $this->bookRepository->findByGenre('Science Fiction');
        $fantasyBooks = $this->bookRepository->findByGenre('Fantasy');

        $this->assertEquals(2, $sciFiBooks->total());
        $this->assertEquals(3, $fantasyBooks->total());
    }

    public function test_can_find_books_by_author()
    {
        Book::factory()->create([
            'author' => 'John Smith',
            'title' => 'Book One'
        ]);
        
        Book::factory()->create([
            'author' => 'Jane Smith',
            'title' => 'Book Two'
        ]);
        
        Book::factory()->create([
            'author' => 'Bob Johnson',
            'title' => 'Book Three'
        ]);

        $smithBooks = $this->bookRepository->findByAuthor('Smith');
        $johnBooks = $this->bookRepository->findByAuthor('John');

        $this->assertEquals(2, $smithBooks->total());
        $this->assertEquals(2, $johnBooks->total()); // John Smith + Bob Johnson
    }

    public function test_can_search_books()
    {
        Book::factory()->create([
            'title' => 'The Great Adventure',
            'author' => 'John Doe',
            'genre' => 'Adventure'
        ]);
        
        Book::factory()->create([
            'title' => 'Mystery Novel',
            'author' => 'Jane Smith',
            'genre' => 'Mystery'
        ]);

        $results = $this->bookRepository->search('Great');
        $this->assertEquals(1, $results->total());

        $results = $this->bookRepository->search('John');
        $this->assertEquals(1, $results->total());

        $results = $this->bookRepository->search('Mystery');
        $this->assertEquals(1, $results->total());
    }

    public function test_can_get_low_stock_books()
    {
        Book::factory()->create(['available_copies' => 0]);
        Book::factory()->create(['available_copies' => 1]);
        Book::factory()->create(['available_copies' => 2]);
        Book::factory()->create(['available_copies' => 3]);

        $lowStockBooks = $this->bookRepository->getLowStock(2);

        $this->assertEquals(2, $lowStockBooks->count()); // Only books with 1 and 2 copies
    }

    public function test_can_get_genre_statistics()
    {
        Book::factory()->count(3)->create([
            'genre' => 'Science Fiction',
            'total_copies' => 5,
            'available_copies' => 3
        ]);
        
        Book::factory()->count(2)->create([
            'genre' => 'Fantasy',
            'total_copies' => 4,
            'available_copies' => 2
        ]);

        $stats = $this->bookRepository->getGenreStatistics();

        $sciFiStats = $stats->where('genre', 'Science Fiction')->first();
        $fantasyStats = $stats->where('genre', 'Fantasy')->first();

        $this->assertEquals(3, $sciFiStats->count);
        $this->assertEquals(15, $sciFiStats->total_copies);
        $this->assertEquals(9, $sciFiStats->available_copies);

        $this->assertEquals(2, $fantasyStats->count);
        $this->assertEquals(8, $fantasyStats->total_copies);
        $this->assertEquals(4, $fantasyStats->available_copies);
    }

    public function test_can_get_books_by_date_range()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        Book::factory()->create(['created_at' => Carbon::now()->subDays(10)]); // Outside range
        Book::factory()->create(['created_at' => Carbon::now()->subDays(5)]);  // Inside range
        Book::factory()->create(['created_at' => Carbon::now()->subDays(3)]);  // Inside range

        $books = $this->bookRepository->getByDateRange(
            $startDate->toDateString(),
            $endDate->toDateString()
        );

        $this->assertEquals(2, $books->count());
    }

    public function test_can_update_book_availability()
    {
        $book = Book::factory()->create(['available_copies' => 5]);

        $result = $this->bookRepository->updateAvailability($book->id, -2);

        $this->assertTrue($result);
        
        $updatedBook = $this->bookRepository->find($book->id);
        $this->assertEquals(3, $updatedBook->available_copies);
    }

    public function test_update_availability_returns_false_for_nonexistent_book()
    {
        $result = $this->bookRepository->updateAvailability(999999, 1);

        $this->assertFalse($result);
    }

    public function test_can_get_deletable_books()
    {
        $user = User::factory()->create();
        
        $bookWithBorrowing = Book::factory()->create();
        $bookWithoutBorrowing = Book::factory()->create();
        
        // Create active borrowing
        Borrowing::factory()->create([
            'book_id' => $bookWithBorrowing->id,
            'user_id' => $user->id,
            'returned_at' => null
        ]);

        $deletableBooks = $this->bookRepository->getDeletableBooks();

        $this->assertEquals(1, $deletableBooks->count());
        $this->assertEquals($bookWithoutBorrowing->id, $deletableBooks->first()->id);
    }

    public function test_can_get_books_with_active_borrowings()
    {
        $user = User::factory()->create();
        
        $bookWithBorrowing = Book::factory()->create();
        $bookWithoutBorrowing = Book::factory()->create();
        
        // Create active borrowing
        Borrowing::factory()->create([
            'book_id' => $bookWithBorrowing->id,
            'user_id' => $user->id,
            'returned_at' => null
        ]);

        $booksWithBorrowings = $this->bookRepository->getBooksWithActiveBorrowings();

        $this->assertEquals(1, $booksWithBorrowings->count());
        $this->assertEquals($bookWithBorrowing->id, $booksWithBorrowings->first()->id);
        $this->assertEquals(1, $booksWithBorrowings->first()->borrowings_count);
    }

    public function test_can_get_total_copies_count()
    {
        Book::factory()->create(['total_copies' => 5]);
        Book::factory()->create(['total_copies' => 3]);
        Book::factory()->create(['total_copies' => 7]);

        $totalCopies = $this->bookRepository->getTotalCopiesCount();

        $this->assertEquals(15, $totalCopies);
    }

    public function test_can_get_available_copies_count()
    {
        Book::factory()->create(['available_copies' => 3]);
        Book::factory()->create(['available_copies' => 2]);
        Book::factory()->create(['available_copies' => 4]);

        $availableCopies = $this->bookRepository->getAvailableCopiesCount();

        $this->assertEquals(9, $availableCopies);
    }

    public function test_can_find_books_by_criteria()
    {
        Book::factory()->create([
            'genre' => 'Science Fiction',
            'author' => 'Isaac Asimov',
            'available_copies' => 5
        ]);
        
        Book::factory()->create([
            'genre' => 'Fantasy',
            'author' => 'J.R.R. Tolkien',
            'available_copies' => 0
        ]);

        $results = $this->bookRepository->findByCriteria([
            'genre' => 'Science Fiction'
        ]);
        $this->assertEquals(1, $results->total());

        $results = $this->bookRepository->findByCriteria([
            'available_only' => true
        ]);
        $this->assertEquals(1, $results->total());
    }

    public function test_apply_filters_method()
    {
        Book::factory()->create([
            'genre' => 'Science Fiction',
            'author' => 'Isaac Asimov',
            'total_copies' => 10,
            'available_copies' => 5
        ]);
        
        Book::factory()->create([
            'genre' => 'Fantasy',
            'author' => 'J.R.R. Tolkien',
            'total_copies' => 3,
            'available_copies' => 0
        ]);

        // Test genre filter
        $results = $this->bookRepository->findByCriteria(['genre' => 'Science Fiction']);
        $this->assertEquals(1, $results->total());

        // Test author filter
        $results = $this->bookRepository->findByCriteria(['author' => 'Asimov']);
        $this->assertEquals(1, $results->total());

        // Test available_only filter
        $results = $this->bookRepository->findByCriteria(['available_only' => true]);
        $this->assertEquals(1, $results->total());

        // Test min_copies filter
        $results = $this->bookRepository->findByCriteria(['min_copies' => 5]);
        $this->assertEquals(1, $results->total());

        // Test max_copies filter
        $results = $this->bookRepository->findByCriteria(['max_copies' => 5]);
        $this->assertEquals(1, $results->total());
    }

    public function test_repository_inherits_from_abstract_repository()
    {
        $this->assertInstanceOf(\App\Data\Repositories\AbstractRepository::class, $this->bookRepository);
    }

    public function test_model_method_returns_book_class()
    {
        $this->assertEquals(Book::class, $this->bookRepository->model());
    }

    public function test_search_with_empty_results()
    {
        Book::factory()->create(['title' => 'Test Book']);

        $results = $this->bookRepository->search('nonexistent');

        $this->assertEquals(0, $results->total());
    }

    public function test_get_low_stock_with_custom_threshold()
    {
        Book::factory()->create(['available_copies' => 1]);
        Book::factory()->create(['available_copies' => 3]);
        Book::factory()->create(['available_copies' => 5]);

        $lowStockBooks = $this->bookRepository->getLowStock(3);

        $this->assertEquals(2, $lowStockBooks->count()); // Books with 1 and 3 copies
    }

    public function test_get_genre_statistics_with_no_books()
    {
        $stats = $this->bookRepository->getGenreStatistics();

        $this->assertEquals(0, $stats->count());
    }
}