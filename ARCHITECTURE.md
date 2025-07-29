# Library Management System - Architecture Design

## Overview
This document outlines the architecture design for implementing Business Logic Layer and Data Layer patterns in the Library Management System.

## Architecture Layers

```
┌─────────────────────────────────────────────────────────┐
│                    API Layer (HTTP)                     │
│  Controllers, Requests, Resources, Middleware           │
└─────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────┐
│                 Business Logic Layer                    │
│     Services, Validators, Exceptions, DTOs             │
└─────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────┐
│                    Data Layer                          │
│      Repositories, Query Objects, Models               │
└─────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────┐
│                  Database Layer                         │
│         MySQL, Migrations, Seeders                     │
└─────────────────────────────────────────────────────────┘
```

## Folder Structure

```
app/
├── Business/
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── BookService.php
│   │   ├── BorrowingService.php
│   │   └── DashboardService.php
│   ├── Validators/
│   │   ├── BookValidator.php
│   │   ├── BorrowingValidator.php
│   │   └── UserValidator.php
│   ├── Exceptions/
│   │   ├── BookNotAvailableException.php
│   │   ├── BookAlreadyBorrowedException.php
│   │   └── UnauthorizedException.php
│   └── DTOs/
│       ├── CreateBookDTO.php
│       ├── BorrowBookDTO.php
│       └── UserRegistrationDTO.php
├── Data/
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   ├── BookRepositoryInterface.php
│   │   │   ├── UserRepositoryInterface.php
│   │   │   └── BorrowingRepositoryInterface.php
│   │   ├── BookRepository.php
│   │   ├── UserRepository.php
│   │   └── BorrowingRepository.php
│   ├── QueryObjects/
│   │   ├── BookSearchQuery.php
│   │   ├── OverdueBorrowingsQuery.php
│   │   └── DashboardStatsQuery.php
│   └── AbstractRepository.php
└── Http/
    └── Controllers/ (existing, will be refactored)
```

## Business Logic Layer

### Services
- **AuthService**: Handle user registration, authentication, token management
- **BookService**: Manage book operations, availability checks, authorization
- **BorrowingService**: Handle borrowing business rules, date calculations, inventory updates
- **DashboardService**: Aggregate data, generate statistics and metrics

### Validators
- **Business-specific validations** separate from HTTP request validation
- **Domain rules** like "user cannot borrow same book twice"
- **Availability checks** and **authorization rules**

### Exceptions
- **Domain-specific exceptions** for better error handling
- **Meaningful error messages** for business rule violations
- **Consistent error responses** across the application

## Data Layer

### Repositories
- **Abstract data access** from business logic
- **Interface-based contracts** for testability
- **Complex query encapsulation**
- **Database optimization** and caching

### Query Objects
- **Complex database queries** as reusable objects
- **Search functionality** with multiple filters
- **Dashboard aggregations** and statistics
- **Reporting queries**

## Benefits

### Separation of Concerns
- **Controllers** handle HTTP concerns only
- **Services** contain pure business logic
- **Repositories** manage data persistence
- **Models** remain focused on data representation

### Testability
- **Unit testing** business logic in isolation
- **Repository mocking** for service tests
- **Database abstraction** for integration tests

### Maintainability
- **Single responsibility** for each component
- **Easy to extend** with new features
- **Clear dependencies** between layers

### Reusability
- **Business logic** can be reused across different interfaces
- **Repository patterns** work with different data sources
- **Service methods** can be called from multiple controllers

## Implementation Strategy

### Phase 1: Repository Layer
1. Create base repository interface and abstract class
2. Implement specific repositories for each model
3. Add query objects for complex operations

### Phase 2: Business Services
1. Extract business logic from controllers
2. Create service classes with clear responsibilities
3. Add business validators and exceptions

### Phase 3: Integration
1. Update controllers to use services
2. Configure dependency injection
3. Update tests to work with new architecture

### Phase 4: Optimization
1. Add caching where appropriate
2. Optimize database queries
3. Add comprehensive documentation