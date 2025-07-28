<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create a librarian
        User::create([
            'name' => 'John Librarian',
            'email' => 'librarian@library.com',
            'password' => Hash::make('password123'),
            'role' => 'librarian',
        ]);

        // Create demo members
        $members = [
            [
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'password' => Hash::make('password123'),
                'role' => 'member',
            ],
            [
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'password' => Hash::make('password123'),
                'role' => 'member',
            ],
            [
                'name' => 'Carol Williams',
                'email' => 'carol@example.com',
                'password' => Hash::make('password123'),
                'role' => 'member',
            ],
            [
                'name' => 'David Brown',
                'email' => 'david@example.com',
                'password' => Hash::make('password123'),
                'role' => 'member',
            ],
            [
                'name' => 'Emma Davis',
                'email' => 'emma@example.com',
                'password' => Hash::make('password123'),
                'role' => 'member',
            ],
        ];

        foreach ($members as $member) {
            User::create($member);
        }
    }
}