<?php

namespace App\Http\Controllers\Api;

use App\Business\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }
    /**
     * Get dashboard data
     *
     * Retrieve role-specific dashboard information. Librarians see system overview,
     * members see their borrowing information.
     *
     * @group Dashboard
     */
    public function index(Request $request)
    {
        $result = $this->dashboardService->getDashboardData();

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Get librarian dashboard data
     *
     * Retrieve system overview dashboard information for librarians only.
     * Includes statistics about total books, borrowed books, due dates, and member activity.
     *
     * @group Dashboard
     * @authenticated
     * @response 200 {
     *   "message": "Librarian dashboard data retrieved successfully",
     *   "data": {
     *     "stats": {
     *       "total_books": 150,
     *       "total_borrowed_books": 45,
     *       "books_due_today": 5,
     *       "members_with_overdue_books": 3
     *     }
     *   }
     * }
     * @response 403 {"message": "Access denied. Required role: librarian"}
     */
    public function librarianDashboard()
    {
        $result = $this->dashboardService->getLibrarianDashboard();

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Get member dashboard data
     *
     * Retrieve personal borrowing dashboard information for members only.
     * Shows active borrowings, overdue books, and borrowing history.
     *
     * @group Dashboard
     * @authenticated
     * @response 200 {
     *   "message": "Member dashboard data retrieved successfully", 
     *   "data": {
     *     "stats": {
     *       "active_borrowings": 3,
     *       "overdue_books": 1,
     *       "total_books_borrowed": 25
     *     }
     *   }
     * }
     * @response 403 {"message": "Access denied. Required role: member"}
     */
    public function memberDashboard(Request $request)
    {
        $result = $this->dashboardService->getMemberDashboard();

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }
}
