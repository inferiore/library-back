<?php

namespace Tests\Unit\Business\Services;

use App\Business\Services\BookService;
use App\Business\Exceptions\BusinessException;
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use Mockery;

class BookServiceUnitTest extends TestCase
{
    use DatabaseTransactions;
    
    protected BookService $bookService;
    protected $bookRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations for testing
        $this->artisan('migrate', ['--database' => 'sqlite']);
        
        $this->bookRepositoryMock = Mockery::mock(BookRepositoryInterface::class);
        $this->bookService = new BookService($this->bookRepositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_books_without_search()
    {
        $books = [
            ['id' => 1, 'title' => 'Book 1'],
            ['id' => 2, 'title' => 'Book 2']
        ];
        
        $paginator = new LengthAwarePaginator($books, 2, 15, 1);

        $this->bookRepositoryMock
            ->shouldReceive('findByCriteria')
            ->once()
            ->with([], [], 15)
            ->andReturn($paginator);

        $result = $this->bookService->getBooks();

        $this->assertTrue($result['success']);
        $this->assertEquals('Books retrieved successfully', $result['message']);
        $this->assertEquals($books, $result['data']['books']);
        $this->assertArrayHasKey('pagination', $result['data']);
    }

    public function test_get_books_with_search()
    {
        $params = ['search' => 'Harry Potter'];
        $books = [['id' => 1, 'title' => 'Harry Potter']];
        $paginator = new LengthAwarePaginator($books, 1, 15, 1);

        $this->bookRepositoryMock
            ->shouldReceive('search')
            ->once()
            ->with('Harry Potter', $params, 15)
            ->andReturn($paginator);

        $result = $this->bookService->getBooks($params);

        $this->assertTrue($result['success']);
        $this->assertEquals($books, $result['data']['books']);
    }

    public function test_get_specific_book()
    {
        $book = Book::factory()->make(['id' => 1, 'title' => 'Test Book']);
        $bookId = 1;

        $this->bookRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($bookId)
            ->andReturn($book);

        $result = $this->bookService->getBook($bookId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Book retrieved successfully', $result['message']);
        $this->assertEquals($book, $result['data']['book']);
    }

    public function test_get_book_throws_exception_when_not_found()
    {
        $bookId = 999;

        $this->bookRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($bookId)
            ->andReturn(null);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Book not found');

        $this->bookService->getBook($bookId);
    }

    public function test_create_book_as_librarian()
    {
        $user = User::factory()->create(['role' => 'librarian']);
        $this->actingAs($user);

        $bookData = [
            'title' => 'New Book',
            'author' => 'Author Name',
            'genre' => 'Fiction',
            'isbn' => '9781234567890',
            'total_copies' => 5
        ];

        $createdBook = Book::factory()->make($bookData);

        $this->bookRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->with([
                'title' => 'New Book',
                'author' => 'Author Name',
                'genre' => 'Fiction',
                'isbn' => '9781234567890',
                'total_copies' => 5,
                'available_copies' => 5,
            ])
            ->andReturn($createdBook);

        $result = $this->bookService->createBook($bookData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Book created successfully', $result['message']);
        $this->assertEquals($createdBook, $result['data']['book']);
    }

    public function test_create_book_throws_exception_for_non_librarian()
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->actingAs($user);

        $bookData = ['title' => 'Test Book'];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Access denied. Required role: librarian');

        $this->bookService->createBook($bookData);
    }

    public function test_update_book_basic_fields_only()
    {
        $user = User::factory()->create(['role' => 'librarian']);
        $this->actingAs($user);

        $book = Book::factory()->make(['id' => 1]);
        $bookId = 1;
        $bookData = [
            'title' => 'Updated Title',
            'author' => 'Updated Author'
        ];

        $this->bookRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($bookId)
            ->andReturn($book);

        $this->bookRepositoryMock
            ->shouldReceive('update')
            ->once()
            ->with($bookId, $bookData);

        $this->bookRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($bookId)
            ->andReturn($book);

        $result = $this->bookService->updateBook($bookId, $bookData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Book updated successfully', $result['message']);
        $this->assertEquals($book, $result['data']['book']);
    }

    public function test_update_book_with_total_copies_validates_correctly()
    {
        $user = User::factory()->create(['role' => 'librarian']);
        $this->actingAs($user);

        $book = Book::factory()->create([
            'title' => 'Original Title',
            'total_copies' => 5,
            'available_copies' => 5
        ]);
        $bookId = $book->id;
        $bookData = [
            'title' => 'Updated Title',
            'total_copies' => 10
        ];

        // Use real repository for this test since transaction logic is complex
        $realBookRepository = app(BookRepositoryInterface::class);
        $bookService = new BookService($realBookRepository);

        $result = $bookService->updateBook($bookId, $bookData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Book updated successfully', $result['message']);
        
        // Verify book was updated
        $book->refresh();
        $this->assertEquals('Updated Title', $book->title);
        $this->assertEquals(10, $book->total_copies);
        $this->assertEquals(10, $book->available_copies);
    }

    public function test_delete_book_as_librarian()
    {
        $user = User::factory()->create(['role' => 'librarian']);
        $this->actingAs($user);

        $book = Book::factory()->make(['id' => 1]);
        $bookId = 1;

        $this->bookRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($bookId)
            ->andReturn($book);

        $this->bookRepositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with($bookId);

        $result = $this->bookService->deleteBook($bookId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Book deleted successfully', $result['message']);
        $this->assertNull($result['data']);
    }

    public function test_check_availability_returns_available_status()
    {
        $book = Book::factory()->make([
            'id' => 1,
            'title' => 'Test Book',
            'total_copies' => 5,
            'available_copies' => 3
        ]);
        $bookId = 1;

        $book = Mockery::mock(Book::class);
        $book->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $book->shouldReceive('getAttribute')->with('title')->andReturn('Test Book');
        $book->shouldReceive('getAttribute')->with('total_copies')->andReturn(5);
        $book->shouldReceive('getAttribute')->with('available_copies')->andReturn(3);
        
        // Mock the borrowings relationship
        $borrowingsRelation = Mockery::mock();
        $borrowingsRelation->shouldReceive('active')->andReturn(Mockery::mock());
        $borrowingsRelation->active()->shouldReceive('count')->andReturn(2);
        
        $book->shouldReceive('borrowings')->andReturn($borrowingsRelation);
        $book->shouldReceive('isAvailable')->andReturn(true);

        $this->bookRepositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($bookId)
            ->andReturn($book);

        $result = $this->bookService->checkAvailability($bookId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Book is available', $result['message']);
        $this->assertEquals($bookId, $result['data']['book_id']);
        $this->assertEquals('Test Book', $result['data']['title']);
        $this->assertTrue($result['data']['is_available']);
        $this->assertEquals(5, $result['data']['total_copies']);
        $this->assertEquals(3, $result['data']['available_copies']);
        $this->assertEquals(2, $result['data']['borrowed_copies']);
    }

    public function test_get_book_statistics_as_librarian()
    {
        $user = User::factory()->create(['role' => 'librarian']);
        $this->actingAs($user);

        $summaryStats = ['total_books' => 100, 'available_books' => 80];
        $genreStats = new Collection([
            (object)['genre' => 'Fiction', 'count' => 30],
            (object)['genre' => 'Non-Fiction', 'count' => 25]
        ]);

        $this->bookRepositoryMock
            ->shouldReceive('getSummaryStats')
            ->once()
            ->andReturn($summaryStats);

        $this->bookRepositoryMock
            ->shouldReceive('getGenreStatistics')
            ->once()
            ->andReturn($genreStats);

        $result = $this->bookService->getBookStatistics();

        $this->assertTrue($result['success']);
        $this->assertEquals('Book statistics retrieved successfully', $result['message']);
        $this->assertEquals(100, $result['data']['total_books']);
        $this->assertEquals(80, $result['data']['available_books']);
        $this->assertEquals($genreStats, $result['data']['genres']);
    }

    public function test_search_books_with_query()
    {
        $searchCriteria = [
            'query' => 'Harry Potter',
            'sort_by' => 'title',
            'sort_order' => 'asc'
        ];
        
        $books = [['id' => 1, 'title' => 'Harry Potter']];
        $paginator = new LengthAwarePaginator($books, 1, 15, 1);

        $this->bookRepositoryMock
            ->shouldReceive('search')
            ->once()
            ->with('Harry Potter', $searchCriteria, 15)
            ->andReturn($paginator);

        $result = $this->bookService->searchBooks($searchCriteria);

        $this->assertTrue($result['success']);
        $this->assertEquals('Search completed successfully', $result['message']);
        $this->assertEquals($books, $result['data']['books']);
        $this->assertEquals($searchCriteria, $result['data']['search_criteria']);
    }

    public function test_search_books_without_query_uses_criteria()
    {
        $searchCriteria = [
            'genre' => 'Fiction',
            'sort_by' => 'created_at',
            'sort_order' => 'desc'
        ];
        
        $books = [['id' => 1, 'title' => 'Fiction Book']];
        $paginator = new LengthAwarePaginator($books, 1, 15, 1);

        $this->bookRepositoryMock
            ->shouldReceive('findByCriteria')
            ->once()
            ->with($searchCriteria, ['created_at' => 'desc'], 15)
            ->andReturn($paginator);

        $result = $this->bookService->searchBooks($searchCriteria);

        $this->assertTrue($result['success']);
        $this->assertEquals($books, $result['data']['books']);
    }

    public function test_requires_librarian_role_for_restricted_operations()
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->actingAs($user);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Access denied. Required role: librarian');

        $this->bookService->createBook(['title' => 'Test']);
    }
}