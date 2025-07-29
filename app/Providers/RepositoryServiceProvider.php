<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Repository Contracts
use App\Data\Repositories\Contracts\BookRepositoryInterface;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
use App\Data\Repositories\Contracts\BorrowingRepositoryInterface;

// Repository Implementations
use App\Data\Repositories\BookRepository;
use App\Data\Repositories\UserRepository;
use App\Data\Repositories\BorrowingRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array
     */
    public $bindings = [
        BookRepositoryInterface::class => BookRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        BorrowingRepositoryInterface::class => BorrowingRepository::class,
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // The bindings are automatically registered by Laravel
        // when the $bindings property is defined above
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}