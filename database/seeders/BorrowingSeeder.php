<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BorrowingSeeder extends Seeder
{
    public function run(): void
    {
        // Get members (not librarians) and books
        $members = User::where('role', 'member')->get();
        $books = Book::all();

        if ($members->isEmpty() || $books->isEmpty()) {
            $this->command->warn('No members or books found. Please run UserSeeder and BookSeeder first.');

            return;
        }

        // Create some active borrowings
        $activeBorrowings = [
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'The Great Gatsby')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(5),
                'due_at' => Carbon::now()->addDays(9), // Due in 9 days
                'returned_at' => null,
            ],
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', '1984')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(3),
                'due_at' => Carbon::now()->addDays(11), // Due in 11 days
                'returned_at' => null,
            ],
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'Harry Potter and the Philosopher\'s Stone')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(10),
                'due_at' => Carbon::now()->addDays(4), // Due in 4 days
                'returned_at' => null,
            ],
        ];

        // Create some overdue borrowings
        $overdueBorrowings = [
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'To Kill a Mockingbird')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(20),
                'due_at' => Carbon::now()->subDays(6), // Overdue by 6 days
                'returned_at' => null,
            ],
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'The Catcher in the Rye')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(25),
                'due_at' => Carbon::now()->subDays(11), // Overdue by 11 days
                'returned_at' => null,
            ],
        ];

        // Create some returned borrowings (history)
        $returnedBorrowings = [
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'Pride and Prejudice')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(30),
                'due_at' => Carbon::now()->subDays(16),
                'returned_at' => Carbon::now()->subDays(18), // Returned early
            ],
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'The Lord of the Rings')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(45),
                'due_at' => Carbon::now()->subDays(31),
                'returned_at' => Carbon::now()->subDays(29), // Returned on time
            ],
            [
                'user_id' => $members->random()->id,
                'book_id' => $books->where('title', 'The Hobbit')->first()?->id ?? $books->random()->id,
                'borrowed_at' => Carbon::now()->subDays(35),
                'due_at' => Carbon::now()->subDays(21),
                'returned_at' => Carbon::now()->subDays(15), // Returned late
            ],
        ];

        // Create all borrowings
        $allBorrowings = array_merge($activeBorrowings, $overdueBorrowings, $returnedBorrowings);

        foreach ($allBorrowings as $borrowingData) {
            // Skip if user_id or book_id is null
            if (! $borrowingData['user_id'] || ! $borrowingData['book_id']) {
                continue;
            }

            $borrowing = Borrowing::create($borrowingData);

            // Update book available copies for active borrowings
            if (is_null($borrowing->returned_at)) {
                $book = Book::find($borrowing->book_id);
                if ($book && $book->available_copies > 0) {
                    $book->decrement('available_copies');
                }
            }
        }

        $this->command->info('Created ' . count($allBorrowings) . ' borrowing records:');
        $this->command->info('- ' . count($activeBorrowings) . ' active borrowings');
        $this->command->info('- ' . count($overdueBorrowings) . ' overdue borrowings');
        $this->command->info('- ' . count($returnedBorrowings) . ' returned borrowings');
    }
}
