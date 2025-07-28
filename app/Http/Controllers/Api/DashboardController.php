<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isLibrarian()) {
            return $this->librarianDashboard();
        } else {
            return $this->memberDashboard($user);
        }
    }

    private function librarianDashboard()
    {
        $totalBooks = Book::count();
        $totalBorrowedBooks = Borrowing::active()->count();
        $booksDueToday = Borrowing::active()
            ->whereDate('due_at', Carbon::today())
            ->count();

        // Members with overdue books
        $membersWithOverdueBooks = User::whereHas('borrowings', function ($query) {
            $query->overdue();
        })->with(['borrowings' => function ($query) {
            $query->overdue()->with('book');
        }])->get();

        // Recent borrowings
        $recentBorrowings = Borrowing::with(['user', 'book'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'message' => 'Librarian dashboard data retrieved successfully',
            'data' => [
                'stats' => [
                    'total_books' => $totalBooks,
                    'total_borrowed_books' => $totalBorrowedBooks,
                    'books_due_today' => $booksDueToday,
                    'members_with_overdue_books' => $membersWithOverdueBooks->count(),
                ],
                'members_with_overdue_books' => $membersWithOverdueBooks,
                'recent_borrowings' => $recentBorrowings,
            ],
        ]);
    }

    private function memberDashboard(User $user)
    {
        $activeBorrowings = $user->borrowings()
            ->active()
            ->with('book')
            ->get();

        $overdueBorrowings = $user->borrowings()
            ->overdue()
            ->with('book')
            ->get();

        $borrowingHistory = $user->borrowings()
            ->with('book')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'message' => 'Member dashboard data retrieved successfully',
            'data' => [
                'stats' => [
                    'active_borrowings' => $activeBorrowings->count(),
                    'overdue_books' => $overdueBorrowings->count(),
                    'total_books_borrowed' => $user->borrowings()->count(),
                ],
                'active_borrowings' => $activeBorrowings,
                'overdue_books' => $overdueBorrowings,
                'borrowing_history' => $borrowingHistory,
            ],
        ]);
    }
}