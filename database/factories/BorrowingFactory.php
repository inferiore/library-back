<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class BorrowingFactory extends Factory
{
    public function definition(): array
    {
        $borrowedAt = $this->faker->dateTimeBetween('-2 months', 'now');
        $dueAt = (clone $borrowedAt)->modify('+2 weeks');
        
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
            'returned_at' => $this->faker->boolean(70) ? null : $this->faker->dateTimeBetween($borrowedAt, 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => null,
        ]);
    }

    public function returned(): static
    {
        return $this->state(function (array $attributes) {
            $returnedAt = $this->faker->dateTimeBetween($attributes['borrowed_at'], 'now');
            return [
                'returned_at' => $returnedAt,
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $borrowedAt = $this->faker->dateTimeBetween('-2 months', '-3 weeks');
            $dueAt = (clone $borrowedAt)->modify('+2 weeks');
            
            return [
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'returned_at' => null,
            ];
        });
    }
}