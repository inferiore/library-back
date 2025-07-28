<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BorrowingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = Borrowing::with(['user', 'book']);

        if ($request->user()->isMember()) {
            $query->where('user_id', $request->user()->id);
        }

        $borrowings = $query->latest()->paginate(15);

        return response()->json([
            'message' => 'Borrowings retrieved successfully',
            'data' => $borrowings,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $user = $request->user();
        $book = Book::findOrFail($request->book_id);

        // Check if user is member
        if (!$user->isMember()) {
            return response()->json([
                'message' => 'Only members can borrow books',
            ], 403);
        }

        // Check if book is available
        if (!$book->isAvailable()) {
            return response()->json([
                'message' => 'Book is not available for borrowing',
            ], 422);
        }

        // Check if user already has this book borrowed
        $existingBorrowing = Borrowing::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->exists();

        if ($existingBorrowing) {
            return response()->json([
                'message' => 'You have already borrowed this book',
            ], 422);
        }

        $borrowedAt = Carbon::now();
        $dueAt = $borrowedAt->copy()->addWeeks(2);

        $borrowing = Borrowing::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
        ]);

        // Decrease available copies
        $book->decrement('available_copies');

        $borrowing->load(['user', 'book']);

        return response()->json([
            'message' => 'Book borrowed successfully',
            'data' => $borrowing,
        ], 201);
    }

    public function show(Borrowing $borrowing)
    {
        $user = request()->user();

        if ($user->isMember() && $borrowing->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $borrowing->load(['user', 'book']);

        return response()->json([
            'message' => 'Borrowing retrieved successfully',
            'data' => $borrowing,
        ]);
    }

    public function returnBook(Request $request, Borrowing $borrowing)
    {
        if (!$request->user()->isLibrarian()) {
            return response()->json([
                'message' => 'Only librarians can mark books as returned',
            ], 403);
        }

        if ($borrowing->isReturned()) {
            return response()->json([
                'message' => 'Book has already been returned',
            ], 422);
        }

        $borrowing->update([
            'returned_at' => Carbon::now(),
        ]);

        // Increase available copies
        $borrowing->book->increment('available_copies');

        $borrowing->load(['user', 'book']);

        return response()->json([
            'message' => 'Book returned successfully',
            'data' => $borrowing,
        ]);
    }
}