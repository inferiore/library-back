<?php

namespace Tests\Feature\Book;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_anyone_can_view_books()
    {
        $user = User::factory()->create(['role' => 'member']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/books');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'genre',
                        'isbn',
                        'total_copies',
                        'available_copies',
                        'is_available',
                    ]
                ]
            ]);
    }

    public function test_librarian_can_create_book()
    {
        $librarian = User::factory()->create(['role' => 'librarian']);
        $token = $librarian->createToken('test-token')->plainTextToken;

        $bookData = [
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name,
            'genre' => $this->faker->word,
            'isbn' => '978-' . $this->faker->numerify('##########'),
            'total_copies' => 5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/books', $bookData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'title',
                    'author',
                    'genre',
                    'isbn',
                    'total_copies',
                    'available_copies',
                ]
            ]);

        $this->assertDatabaseHas('books', [
            'title' => $bookData['title'],
            'isbn' => $bookData['isbn'],
            'available_copies' => 5,
        ]);
    }

    public function test_member_cannot_create_book()
    {
        $member = User::factory()->create(['role' => 'member']);
        $token = $member->createToken('test-token')->plainTextToken;

        $bookData = [
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name,
            'genre' => $this->faker->word,
            'isbn' => '978-' . $this->faker->numerify('##########'),
            'total_copies' => 5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/books', $bookData);

        $response->assertStatus(403);
    }

    public function test_librarian_can_update_book()
    {
        $librarian = User::factory()->create(['role' => 'librarian']);
        $token = $librarian->createToken('test-token')->plainTextToken;
        $book = Book::factory()->create();

        $updateData = [
            'title' => 'Updated Title',
            'total_copies' => 10,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/books/{$book->id}", $updateData);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'total_copies' => 10,
        ]);
    }

    public function test_member_cannot_update_book()
    {
        $member = User::factory()->create(['role' => 'member']);
        $token = $member->createToken('test-token')->plainTextToken;
        $book = Book::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/books/{$book->id}", ['title' => 'Updated Title']);

        $response->assertStatus(403);
    }

    public function test_librarian_can_delete_book()
    {
        $librarian = User::factory()->create(['role' => 'librarian']);
        $token = $librarian->createToken('test-token')->plainTextToken;
        $book = Book::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/books/{$book->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_can_search_books()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        Book::factory()->create(['title' => 'Laravel for Beginners']);
        Book::factory()->create(['author' => 'John Laravel']);
        Book::factory()->create(['genre' => 'Laravel Programming']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/books?search=Laravel');

        $response->assertStatus(200);
        $data = $response->json()['data'];
        $this->assertCount(3, $data);
    }

    public function test_book_creation_requires_valid_data()
    {
        $librarian = User::factory()->create(['role' => 'librarian']);
        $token = $librarian->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/books', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'author', 'genre', 'isbn', 'total_copies']);
    }
}