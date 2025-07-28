<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            [
                'title' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'genre' => 'Fiction',
                'isbn' => '978-0-7432-7356-5',
                'total_copies' => 5,
                'available_copies' => 5,
            ],
            [
                'title' => 'To Kill a Mockingbird',
                'author' => 'Harper Lee',
                'genre' => 'Fiction',
                'isbn' => '978-0-06-112008-4',
                'total_copies' => 3,
                'available_copies' => 3,
            ],
            [
                'title' => '1984',
                'author' => 'George Orwell',
                'genre' => 'Dystopian Fiction',
                'isbn' => '978-0-452-28423-4',
                'total_copies' => 4,
                'available_copies' => 4,
            ],
            [
                'title' => 'Pride and Prejudice',
                'author' => 'Jane Austen',
                'genre' => 'Romance',
                'isbn' => '978-0-14-143951-8',
                'total_copies' => 2,
                'available_copies' => 2,
            ],
            [
                'title' => 'The Catcher in the Rye',
                'author' => 'J.D. Salinger',
                'genre' => 'Fiction',
                'isbn' => '978-0-316-76948-0',
                'total_copies' => 3,
                'available_copies' => 3,
            ],
            [
                'title' => 'Harry Potter and the Philosopher\'s Stone',
                'author' => 'J.K. Rowling',
                'genre' => 'Fantasy',
                'isbn' => '978-0-7475-3269-9',
                'total_copies' => 6,
                'available_copies' => 6,
            ],
            [
                'title' => 'The Lord of the Rings',
                'author' => 'J.R.R. Tolkien',
                'genre' => 'Fantasy',
                'isbn' => '978-0-544-00341-5',
                'total_copies' => 4,
                'available_copies' => 4,
            ],
            [
                'title' => 'The Hobbit',
                'author' => 'J.R.R. Tolkien',
                'genre' => 'Fantasy',
                'isbn' => '978-0-547-92822-7',
                'total_copies' => 3,
                'available_copies' => 3,
            ],
            [
                'title' => 'Brave New World',
                'author' => 'Aldous Huxley',
                'genre' => 'Science Fiction',
                'isbn' => '978-0-06-085052-4',
                'total_copies' => 2,
                'available_copies' => 2,
            ],
            [
                'title' => 'The Alchemist',
                'author' => 'Paulo Coelho',
                'genre' => 'Adventure Fiction',
                'isbn' => '978-0-06-112241-5',
                'total_copies' => 4,
                'available_copies' => 4,
            ],
        ];

        foreach ($books as $book) {
            Book::create($book);
        }
    }
}