<?php

namespace App\Http\Controllers\Api;

use App\Business\Services\BorrowingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Borrowing\BorrowBookRequest;
use App\Http\Resources\BorrowingResource;
use Illuminate\Http\Request;

class BorrowingController extends Controller
{
    protected BorrowingService $borrowingService;

    public function __construct(BorrowingService $borrowingService)
    {
        $this->borrowingService = $borrowingService;
    }
    /**
     * List borrowings
     *
     * Retrieve borrowing records. Members see only their own borrowings,
     * librarians see all borrowings.
     *
     * @group Borrowings
     */
    public function index(Request $request)
    {
        $params = $request->only(['status', 'user_id', 'book_id', 'per_page']);
        $result = $this->borrowingService->getBorrowings($params);

        return response()->json([
            'message' => $result['message'],
            'data' => BorrowingResource::collection($result['data']['borrowings'])->additional([
                'pagination' => $result['data']['pagination'],
            ]),
        ]);
    }

    /**
     * Borrow a book
     *
     * Create a new borrowing record. Only members can borrow books.
     * Books must be available and users cannot borrow the same book multiple times.
     *
     * @group Borrowings
     */
    public function store(BorrowBookRequest $request)
    {
        $result = $this->borrowingService->borrowBook($request->book_id);

        return response()->json([
            'message' => $result['message'],
            'data' => new BorrowingResource($result['data']['borrowing']),
        ], 201);
    }

    /**
     * Get borrowing details
     *
     * Retrieve details of a specific borrowing record. Members can only see their own borrowings.
     *
     * @group Borrowings
     */
    public function show($id)
    {
        $result = $this->borrowingService->getBorrowing($id);

        return response()->json([
            'message' => $result['message'],
            'data' => new BorrowingResource($result['data']['borrowing']),
        ]);
    }

    /**
     * Return a book
     *
     * Mark a borrowing record as returned. Only librarians can perform this action.
     *
     * @group Borrowings
     */
    public function returnBook($id)
    {
        $result = $this->borrowingService->returnBook($id);

        return response()->json([
            'message' => $result['message'],
            'data' => new BorrowingResource($result['data']['borrowing']),
        ]);
    }
}
