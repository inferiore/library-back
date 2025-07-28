<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = Book::query();

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $books = $query->paginate(15);

        return response()->json([
            'message' => 'Books retrieved successfully',
            'data' => $books,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Book::class);

        $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'genre' => 'required|string|max:255',
            'isbn' => 'required|string|unique:books,isbn',
            'total_copies' => 'required|integer|min:1',
        ]);

        $book = Book::create([
            'title' => $request->title,
            'author' => $request->author,
            'genre' => $request->genre,
            'isbn' => $request->isbn,
            'total_copies' => $request->total_copies,
            'available_copies' => $request->total_copies,
        ]);

        return response()->json([
            'message' => 'Book created successfully',
            'data' => $book,
        ], 201);
    }

    public function show(Book $book)
    {
        return response()->json([
            'message' => 'Book retrieved successfully',
            'data' => $book,
        ]);
    }

    public function update(Request $request, Book $book)
    {
        $this->authorize('update', $book);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'author' => 'sometimes|required|string|max:255',
            'genre' => 'sometimes|required|string|max:255',
            'isbn' => 'sometimes|required|string|unique:books,isbn,' . $book->id,
            'total_copies' => 'sometimes|required|integer|min:1',
        ]);

        $book->update($request->only([
            'title', 'author', 'genre', 'isbn', 'total_copies'
        ]));

        // Adjust available copies if total copies changed
        if ($request->has('total_copies')) {
            $borrowedCopies = $book->borrowings()->active()->count();
            $book->available_copies = max(0, $request->total_copies - $borrowedCopies);
            $book->save();
        }

        return response()->json([
            'message' => 'Book updated successfully',
            'data' => $book->fresh(),
        ]);
    }

    public function destroy(Book $book)
    {
        $this->authorize('delete', $book);

        if ($book->borrowings()->active()->exists()) {
            return response()->json([
                'message' => 'Cannot delete book with active borrowings',
            ], 422);
        }

        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully',
        ]);
    }
}