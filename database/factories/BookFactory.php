<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        $totalCopies = $this->faker->numberBetween(1, 10);

        return [
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'genre' => $this->faker->randomElement([
                'Fiction', 'Science Fiction', 'Fantasy', 'Mystery', 'Romance',
                'Thriller', 'Biography', 'Non-fiction', 'History', 'Science',
            ]),
            'isbn' => '978-' . $this->faker->numerify('##########'),
            'total_copies' => $totalCopies,
            'available_copies' => $totalCopies,
        ];
    }
}
