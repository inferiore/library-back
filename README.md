# Library Management System API

A comprehensive RESTful API for managing a library system built with Laravel 12. The system supports role-based access control with two user types: Librarians and Members.

## Features

### Authentication & Authorization
- User registration and login with JWT tokens (Laravel Sanctum)
- Role-based access control (Librarian/Member)
- Secure logout functionality

### Book Management
- Full CRUD operations for books (Librarian only)
- Search functionality by title, author, or genre
- Track total and available copies
- ISBN validation

### Borrowing System
- Members can borrow available books
- 2-week borrowing period with due date tracking
- Prevent duplicate borrowing of the same book
- Librarians can mark books as returned
- Automatic copy availability tracking

### Dashboard
- **Librarian Dashboard**: Total books, borrowed books, books due today, overdue members
- **Member Dashboard**: Active borrowings, overdue books, borrowing history

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (default) or MySQL/PostgreSQL

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd library
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate --seed
   ```

5. **Start the development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000`

## Demo Credentials

### Librarian Account
- **Email**: `librarian@library.com`
- **Password**: `password123`

### Member Accounts
- **Email**: `alice@example.com` | **Password**: `password123`
- **Email**: `bob@example.com` | **Password**: `password123`
- **Email**: `carol@example.com` | **Password**: `password123`
- **Email**: `david@example.com` | **Password**: `password123`
- **Email**: `emma@example.com` | **Password**: `password123`

## API Endpoints

### Authentication
```
POST   /api/register          # User registration
POST   /api/login             # User login
POST   /api/logout            # User logout (authenticated)
GET    /api/me                # Get user profile (authenticated)
```

### Dashboard
```
GET    /api/dashboard         # Role-specific dashboard data
```

### Books
```
GET    /api/books             # List all books (with search)
POST   /api/books             # Create book (Librarian only)
GET    /api/books/{id}        # Show book details
PUT    /api/books/{id}        # Update book (Librarian only)
DELETE /api/books/{id}        # Delete book (Librarian only)
```

### Borrowings
```
GET    /api/borrowings        # List borrowings (filtered by role)
POST   /api/borrowings        # Borrow a book (Member only)
GET    /api/borrowings/{id}   # Show borrowing details
PATCH  /api/borrowings/{id}/return  # Return book (Librarian only)
```

## API Usage Examples

### Authentication
```bash
# Register
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "member"
  }'

# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "librarian@library.com",
    "password": "password123"
  }'
```

### Books (with Authentication)
```bash
# List books with search
curl -X GET "http://localhost:8000/api/books?search=gatsby" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Create book (Librarian only)
curl -X POST http://localhost:8000/api/books \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "New Book",
    "author": "Author Name",
    "genre": "Fiction",
    "isbn": "978-1234567890",
    "total_copies": 3
  }'
```

### Borrowing
```bash
# Borrow a book (Member only)
curl -X POST http://localhost:8000/api/borrowings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "book_id": 1
  }'

# Return a book (Librarian only)
curl -X PATCH http://localhost:8000/api/borrowings/1/return \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "message": "Operation successful",
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Business Rules

### Book Borrowing
- Only members can borrow books
- Books must be available (available_copies > 0)
- Members cannot borrow the same book multiple times
- Borrowing period is 2 weeks from the borrowed date
- Available copies automatically decrease when borrowed

### Book Management
- Only librarians can create, update, or delete books
- Books with active borrowings cannot be deleted
- ISBN must be unique across all books

### Authorization
- All endpoints require authentication except registration and login
- Role-based permissions are enforced throughout the system
- Members can only see their own borrowing records
- Librarians have full access to all system data

## Database Schema

### Users Table
- `id`, `name`, `email`, `password`, `role` (librarian/member)

### Books Table
- `id`, `title`, `author`, `genre`, `isbn`, `total_copies`, `available_copies`

### Borrowings Table
- `id`, `user_id`, `book_id`, `borrowed_at`, `due_at`, `returned_at`

## API Documentation

The API is documented using **Scramble**, which automatically generates OpenAPI documentation from your Laravel code.

### Access Documentation
Once the server is running, visit: `http://localhost:8000/docs/api`

### Features
- Interactive API documentation with "Try It" functionality
- Automatic schema generation from Laravel Request classes and Resources
- Authentication support for testing endpoints directly in the browser

## Postman Collection

Import the provided Postman collection to test the API:
1. Import `Library-Management-API.postman_collection.json` into Postman
2. The collection includes:
   - All API endpoints organized by functionality
   - Pre-configured authentication
   - Example requests with proper data
   - Automatic token management

## Testing

Run the test suite:
```bash
php artisan test
```

The test suite includes:
- Authentication tests (registration, login, logout)
- Book management tests (CRUD operations, authorization)
- Feature tests for all major functionality
- Database factories for test data generation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).