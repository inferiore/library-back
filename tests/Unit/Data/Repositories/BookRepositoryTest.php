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
        $book = Book::factory()->create(['isbn' => '9781234567890']);

        $result = $this->bookRepository->findByISBN('9781234567890');

        $this->assertNotNull($result);
        $this->assertEquals($book->id, $result->id);
        $this->assertEquals('9781234567890', $result->isbn);
    }

    public function test_returns_null_when_book_not_found_by_isbn()
    {
        $result = $this->bookRepository->findByISBN('9999999999999');

        $this->assertNull($result);
    }

    public function test_can_find_available_books()
    {
        Book::factory()->create(['available_copies' => 0]);
        Book::factory()->create(['available_copies' => 5]);
        Book::factory()->create(['available_copies' => 3]);

        $result = $this->bookRepository->findAvailable();

        $this->assertEquals(2, $result->total());
        $this->assertTrue($result->items()[0]->available_copies > 0);
        $this->assertTrue($result->items()[1]->available_copies > 0);
    }

    public function test_can_find_books_by_genre()
    {
        Book::factory()->create(['genre' => 'Fiction']);
        Book::factory()->create(['genre' => 'Fiction']);
        Book::factory()->create(['genre' => 'Science']);

        $result = $this->bookRepository->findByGenre('Fiction');

        $this->assertEquals(2, $result->total());
        foreach ($result->items() as $book) {
            $this->assertEquals('Fiction', $book->genre);
        }
    }

    public function test_can_find_books_by_author()
    {
        Book::factory()->create(['author' => 'J.K. Rowling']);
        Book::factory()->create(['author' => 'Stephen King']);
        Book::factory()->create(['author' => 'J.R.R. Tolkien']);

        $result = $this->bookRepository->findByAuthor('J.K.');

        $this->assertEquals(1, $result->total());
        $this->assertStringContainsString('J.K.', $result->items()[0]->author);
    }

    public function test_can_search_books()
    {
        Book::factory()->create([
            'title' => 'Harry Potter',
            'author' => 'J.K. Rowling',
            'genre' => 'Fantasy'
        ]);
        Book::factory()->create([
            'title' => 'The Hobbit',
            'author' => 'J.R.R. Tolkien',
            'genre' => 'Fantasy'
        ]);
        Book::factory()->create([
            'title' => 'IT',
            'author' => 'Stephen King',
            'genre' => 'Horror'
        ]);

        // Search by title
        $result = $this->bookRepository->search('Harry');
        $this->assertEquals(1, $result->total());

        // Search by author
        $result = $this->bookRepository->search('Tolkien');
        $this->assertEquals(1, $result->total());

        // Search by genre
        $result = $this->bookRepository->search('Fantasy');
        $this->assertEquals(2, $result->total());
    }

    public function test_can_get_low_stock_books()
    {
        Book::factory()->create(['available_copies' => 0]);
        Book::factory()->create(['available_copies' => 1]);
        Book::factory()->create(['available_copies' => 2]);
        Book::factory()->create(['available_copies' => 5]);

        $result = $this->bookRepository->getLowStock(2);

        $this->assertEquals(2, $result->count()); // Only books with 1 and 2 copies (0 is excluded)
        foreach ($result as $book) {
            $this->assertLessThanOrEqual(2, $book->available_copies);
            $this->assertGreaterThan(0, $book->available_copies);
        }
    }

    public function test_can_get_genre_statistics()
    {
        Book::factory()->create(['genre' => 'Fiction', 'total_copies' => 10, 'available_copies' => 8]);
        Book::factory()->create(['genre' => 'Fiction', 'total_copies' => 5, 'available_copies' => 3]);
        Book::factory()->create(['genre' => 'Science', 'total_copies' => 7, 'available_copies' => 7]);

        $result = $this->bookRepository->getGenreStatistics();

        $this->assertEquals(2, $result->count());
        
        $fiction = $result->where('genre', 'Fiction')->first();
        $this->assertEquals(2, $fiction->count);
        $this->assertEquals(15, $fiction->total_copies);
        $this->assertEquals(11, $fiction->available_copies);

        $science = $result->where('genre', 'Science')->first();
        $this->assertEquals(1, $science->count);
        $this->assertEquals(7, $science->total_copies);
    }

    public function test_can_get_books_by_date_range()
    {
        // Create books with specific dates
        Book::factory()->create(['created_at' => '2025-01-01 12:00:00']); // Outside range
        Book::factory()->create(['created_at' => '2025-01-15 12:00:00']); // Inside range
        Book::factory()->create(['created_at' => '2025-01-20 12:00:00']); // Inside range

        $result = $this->bookRepository->getByDateRange('2025-01-10', '2025-01-25');

        $this->assertEquals(2, $result->count());
    }

    public function test_can_update_book_availability()
    {
        $book = Book::factory()->create(['available_copies' => 5]);

        $result = $this->bookRepository->updateAvailability($book->id, -1);

        $this->assertTrue($result);
        $book->refresh();
        $this->assertEquals(4, $book->available_copies);
    }

    public function test_update_availability_returns_false_for_nonexistent_book()
    {
        $result = $this->bookRepository->updateAvailability(999, -1);

        $this->assertFalse($result);
    }

    public function test_can_get_deletable_books()
    {
        $bookWithBorrowing = Book::factory()->create();
        $bookWithoutBorrowing = Book::factory()->create();
        
        // Create an active borrowing for the first book
        $user = User::factory()->create();
        Borrowing::factory()->create([
            'book_id' => $bookWithBorrowing->id,
            'user_id' => $user->id,
            'returned_at' => null // Explicitly set as active
        ]);

        $result = $this->bookRepository->getDeletableBooks();

        $this->assertEquals(1, $result->count());
        $this->assertEquals($bookWithoutBorrowing->id, $result->first()->id);
    }

    public function test_can_get_books_with_active_borrowings()
    {
        $bookWithBorrowing = Book::factory()->create();
        $bookWithoutBorrowing = Book::factory()->create();
        
        $user = User::factory()->create();
        Borrowing::factory()->create([
            'book_id' => $bookWithBorrowing->id,
            'user_id' => $user->id,
            'returned_at' => null // Active borrowing
        ]);

        $result = $this->bookRepository->getBooksWithActiveBorrowings();

        $this->assertEquals(1, $result->count());
        $this->assertEquals($bookWithBorrowing->id, $result->first()->id);
    }

    public function test_can_get_total_copies_count()
    {
        Book::factory()->create(['total_copies' => 10]);
        Book::factory()->create(['total_copies' => 5]);
        Book::factory()->create(['total_copies' => 3]);

        $result = $this->bookRepository->getTotalCopiesCount();

        $this->assertEquals(18, $result);
    }

    public function test_can_get_available_copies_count()
    {
        Book::factory()->create(['available_copies' => 8]);
        Book::factory()->create(['available_copies' => 3]);
        Book::factory()->create(['available_copies' => 2]);

        $result = $this->bookRepository->getAvailableCopiesCount();

        $this->assertEquals(13, $result);
    }

    public function test_can_find_books_by_criteria()
    {
        Book::factory()->create([
            'title' => 'Harry Potter',
            'genre' => 'Fantasy',
            'available_copies' => 5
        ]);
        Book::factory()->create([
            'title' => 'Lord of the Rings',
            'genre' => 'Fantasy',
            'available_copies' => 3
        ]);
        Book::factory()->create([
            'title' => 'IT',
            'genre' => 'Horror',
            'available_copies' => 2
        ]);

        $result = $this->bookRepository->findByCriteria([
            'genre' => 'Fantasy'
        ]);

        $this->assertEquals(2, $result->total());
        foreach ($result->items() as $book) {
            $this->assertEquals('Fantasy', $book->genre);
        }
    }

    public function test_apply_filters_method()
    {
        Book::factory()->create([
            'title' => 'Book A',
            'author' => 'Author A',
            'genre' => 'Fiction',
            'available_copies' => 5
        ]);
        Book::factory()->create([
            'title' => 'Book B',
            'author' => 'Author B',
            'genre' => 'Science',
            'available_copies' => 0
        ]);

        // Test filtering by available only
        $result = $this->bookRepository->findByCriteria(['available_only' => true]);
        $this->assertEquals(1, $result->total());

        // Test multiple filters
        $result = $this->bookRepository->findByCriteria([
            'genre' => 'Fiction',
            'available_only' => true
        ]);
        $this->assertEquals(1, $result->total());
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
        Book::factory()->create(['title' => 'Existing Book']);

        $result = $this->bookRepository->search('NonExistent');

        $this->assertEquals(0, $result->total());
        $this->assertEmpty($result->items());
    }

    public function test_get_low_stock_with_custom_threshold()
    {
        Book::factory()->create(['available_copies' => 1]);
        Book::factory()->create(['available_copies' => 3]);
        Book::factory()->create(['available_copies' => 5]);

        $result = $this->bookRepository->getLowStock(3);

        $this->assertEquals(2, $result->count());
    }

    public function test_get_genre_statistics_with_no_books()
    {
        $result = $this->bookRepository->getGenreStatistics();

        $this->assertEquals(0, $result->count());
    }

    public function test_get_summary_stats()
    {
        Book::factory()->create(['total_copies' => 10, 'available_copies' => 8]);
        Book::factory()->create(['total_copies' => 5, 'available_copies' => 0]);

        $result = $this->bookRepository->getSummaryStats();

        $this->assertArrayHasKey('total_books', $result);
        $this->assertArrayHasKey('total_copies', $result);
        $this->assertArrayHasKey('available_copies', $result);
        $this->assertArrayHasKey('borrowed_copies', $result);
        $this->assertArrayHasKey('avg_copies_per_book', $result);
        $this->assertArrayHasKey('total_genres', $result);
        $this->assertArrayHasKey('total_authors', $result);
        $this->assertArrayHasKey('utilization_rate', $result);
        
        $this->assertEquals(2, $result['total_books']);
        $this->assertEquals(15, $result['total_copies']);
        $this->assertEquals(8, $result['available_copies']);
        $this->assertEquals(7, $result['borrowed_copies']); // 15 - 8 = 7
    }

    public function test_get_total_count()
    {
        Book::factory()->count(5)->create();

        $result = $this->bookRepository->getTotalCount();

        $this->assertEquals(5, $result);
    }
}