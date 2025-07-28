<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
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
            'data' => BookResource::collection($books->items())->additional([
                'pagination' => [
                    'current_page' => $books->currentPage(),
                    'last_page' => $books->lastPage(),
                    'per_page' => $books->perPage(),
                    'total' => $books->total(),
                ]
            ]),
        ]);
    }

    public function store(StoreBookRequest $request)
    {
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
            'data' => new BookResource($book),
        ], 201);
    }

    public function show(Book $book)
    {
        return response()->json([
            'message' => 'Book retrieved successfully',
            'data' => new BookResource($book),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
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
            'data' => new BookResource($book->fresh()),
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