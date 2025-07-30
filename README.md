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
- Docker and Docker Compose
- PHP 8.2 or higher (for local development)
- Composer (for local development)

### Docker Setup 
Deployment with Docker
1. Clone the Repositories
   First, clone both the backend and frontend repositories:

# Clone the backend repository
``` bash
# Clone the backend repository
git clone https://github.com/inferiore/library-back.git library

# Clone the frontend repository
git clone https://github.com/inferiore/library-front.git library-react
```

```
Your directory structure should look like:
    ├── library/           # Backend (Laravel API)
    └── library-react/     # Frontend (React App)
```

### 1. Start the Full Stack

Navigate to the backend directory and start all services:

```bash
cd library
docker-compose up --build
```

This will start:
- **MySQL Database** on port `3306`
- **Laravel Backend API** on port `8000`
- **React Frontend** on port `3000`

### 2. Access the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **Database**: localhost:3306

 
### 3. Run database migrations
   ```bash
   docker-compose exec app php artisan migrate
   ```

### 4. (Optional) Seed demo data
   ```bash
   docker-compose exec app php artisan db:seed
   ```

## Demo Credentials

### Librarian Account
- **Email**: `librarian@library.com`
- **Password**: `Password123!`

### Member Accounts
- **Email**: `alice@example.com` | **Password**: `Password123!`
- **Email**: `bob@example.com` | **Password**: `Password123!`
- **Email**: `carol@example.com` | **Password**: `Password123!`
- **Email**: `david@example.com` | **Password**: `Password123!`
- **Email**: `emma@example.com` | **Password**: `Password123!`


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

### Docker Environment
```bash
docker-compose exec app php artisan test
```

The test suite includes:
- Authentication tests (registration, login, logout)
- Book management tests (CRUD operations, authorization)
- Feature tests for all major functionality
- Database factories for test data generation

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
