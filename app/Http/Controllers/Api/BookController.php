<?php

namespace App\Http\Controllers\Api;

use App\Business\Services\BookService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
use Illuminate\Http\Request;

class BookController extends Controller
{
    protected BookService $bookService;

    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }
    /**
     * List all books
     *
     * Retrieve a paginated list of all books. Supports search by title, author, or genre.
     *
     * @group Books
     * @queryParam search string Search books by title, author, or genre. Example: Laravel
     */
    public function index(Request $request)
    {
        $params = $request->only(['search', 'genre', 'author', 'available_only', 'per_page']);
        $result = $this->bookService->getBooks($params);

        return response()->json([
            'message' => $result['message'],
            'data' => BookResource::collection($result['data']['books'])->additional([
                'pagination' => $result['data']['pagination'],
            ]),
        ]);
    }

    /**
     * Create a new book
     *
     * Add a new book to the library. Only librarians can perform this action.
     *
     * @group Books
     */
    public function store(StoreBookRequest $request)
    {
        $result = $this->bookService->createBook($request->validated());

        return response()->json([
            'message' => $result['message'],
            'data' => new BookResource($result['data']['book']),
        ], 201);
    }

    /**
     * Get book details
     *
     * Retrieve detailed information about a specific book.
     *
     * @group Books
     */
    public function show($id)
    {
        $result = $this->bookService->getBook($id);

        return response()->json([
            'message' => $result['message'],
            'data' => new BookResource($result['data']['book']),
        ]);
    }

    /**
     * Update book information
     *
     * Update book details. Only librarians can perform this action.
     *
     * @group Books
     */
    public function update(UpdateBookRequest $request, $id)
    {
        $result = $this->bookService->updateBook($id, $request->validated());

        return response()->json([
            'message' => $result['message'],
            'data' => new BookResource($result['data']['book']),
        ]);
    }

    /**
     * Delete a book
     *
     * Remove a book from the library. Only librarians can perform this action.
     * Books with active borrowings cannot be deleted.
     *
     * @group Books
     */
    public function destroy($id)
    {
        $result = $this->bookService->deleteBook($id);

        return response()->json([
            'message' => $result['message'],
        ]);
    }
}
