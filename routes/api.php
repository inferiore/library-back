<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BorrowingController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard routes with role protection
    Route::get('/dashboard/librarian', [DashboardController::class, 'librarianDashboard'])
        ->middleware('role:librarian');
    Route::get('/dashboard/member', [DashboardController::class, 'memberDashboard'])
        ->middleware('role:member');

    // Books
    Route::apiResource('books', BookController::class);

    // Borrowings
    Route::get('/borrowings', [BorrowingController::class, 'index']);
    Route::post('/borrowings', [BorrowingController::class, 'store']);
    Route::get('/borrowings/{borrowing}', [BorrowingController::class, 'show']);
    Route::patch('/borrowings/{borrowing}/return', [BorrowingController::class, 'returnBook']);

    // Legacy user endpoint
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
