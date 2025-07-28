<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view books
    }

    public function view(User $user, Book $book): bool
    {
        return true; // All authenticated users can view a book
    }

    public function create(User $user): bool
    {
        return $user->isLibrarian();
    }

    public function update(User $user, Book $book): bool
    {
        return $user->isLibrarian();
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->isLibrarian();
    }

    public function restore(User $user, Book $book): bool
    {
        return $user->isLibrarian();
    }

    public function forceDelete(User $user, Book $book): bool
    {
        return $user->isLibrarian();
    }
}