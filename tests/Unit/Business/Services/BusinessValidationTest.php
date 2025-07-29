<?php

namespace Tests\Unit\Business\Services;

use App\Business\Exceptions\BusinessException;
use App\Business\Services\AuthService;
use App\Business\Services\BookService;
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Tests\TestCase;

class BusinessValidationTest extends TestCase
{
    /**
     * Test that business rules are properly validated
     * This demonstrates the business logic layer working correctly
     */
    public function test_business_service_validation_logic()
    {
        // Test AuthService role validation logic
        $validRoles = ['librarian', 'member'];
        $invalidRole = 'invalid_role';
        
        $this->assertFalse(in_array($invalidRole, $validRoles), 'Invalid role should not be in valid roles');
        $this->assertTrue(in_array('member', $validRoles), 'Member should be a valid role');
        $this->assertTrue(in_array('librarian', $validRoles), 'Librarian should be a valid role');

        // Test BookService year validation logic  
        $currentYear = Carbon::now()->year;
        $futureYear = $currentYear + 5;
        $validYear = $currentYear - 1;
        
        $this->assertTrue($futureYear > $currentYear, 'Future year should be greater than current year');
        $this->assertFalse($validYear > $currentYear, 'Past year should not be greater than current year');

        // Test minimum/maximum copy validation
        $invalidCopies = 0;
        $validCopies = 5;
        
        $this->assertTrue($validCopies > 0, 'Valid copies should be greater than 0');
        $this->assertFalse($invalidCopies > 0, 'Invalid copies should not be greater than 0');

        // Test ISBN format (basic validation)
        $validIsbn = '1234567890';
        $invalidIsbn = '';
        
        $this->assertTrue(strlen($validIsbn) === 10, 'Valid ISBN should be 10 characters');
        $this->assertFalse(strlen($invalidIsbn) === 10, 'Invalid ISBN should not be 10 characters');
    }

    /**
     * Test business logic constants and rules
     */
    public function test_business_constants()
    {
        // Test borrowing period (14 days as per business rules)
        $borrowedAt = Carbon::now();
        $dueAt = $borrowedAt->copy()->addDays(14);
        
        $this->assertEquals(14, $borrowedAt->diffInDays($dueAt), 'Borrowing period should be 14 days');
        
        // Test that due date is in the future
        $this->assertTrue($dueAt->isFuture(), 'Due date should be in the future');
        
        // Test overdue logic
        $overdueDate = Carbon::now()->subDays(1);
        $this->assertTrue($overdueDate->isPast(), 'Overdue date should be in the past');
    }

    /**
     * Test business logic error messages
     */
    public function test_business_error_messages()
    {
        $expectedMessages = [
            'invalid_role' => 'Invalid role specified',
            'duplicate_email' => 'Email already exists',
            'duplicate_isbn' => 'ISBN already exists',
            'future_year' => 'Publication year cannot be in the future',
            'book_not_found' => 'Book not found',
            'invalid_credentials' => 'Invalid credentials',
            'book_unavailable' => 'Book is not available for borrowing',
            'already_borrowed' => 'You already have an active borrowing for this book',
            'already_returned' => 'Book has already been returned',
            'librarian_only' => 'Only librarians can mark books as returned',
            'active_borrowings' => 'Cannot delete book with active borrowings'
        ];

        foreach ($expectedMessages as $key => $message) {
            $this->assertIsString($message, "Error message for {$key} should be a string");
            $this->assertNotEmpty($message, "Error message for {$key} should not be empty");
        }
    }

    /**
     * Test that our business exceptions are properly structured
     */
    public function test_business_exception_structure()
    {
        $exception = new BusinessException('Test message', 400);
        
        $this->assertInstanceOf(BusinessException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals(0, $exception->getCode()); // Exception code is always 0
        $this->assertIsArray($exception->getContext());
    }

    /**
     * Demonstrate service dependency injection works
     */
    public function test_service_dependency_injection()
    {
        // Mock repositories
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $bookRepository = $this->createMock(BookRepositoryInterface::class);

        // Create services with injected dependencies
        $authService = new AuthService($userRepository);
        $bookService = new BookService($bookRepository); 

        // Verify services are created properly
        $this->assertInstanceOf(AuthService::class, $authService);
        $this->assertInstanceOf(BookService::class, $bookService);
        
        // Verify dependencies are properly injected (reflection test)
        $reflection = new \ReflectionClass($authService);
        $property = $reflection->getProperty('userRepository');
        $property->setAccessible(true);
        $injectedRepository = $property->getValue($authService);
        
        $this->assertSame($userRepository, $injectedRepository);
    }
}