<?php

namespace App\Business\Exceptions;

class BookNotAvailableException extends BusinessException
{
    public function __construct(string $bookTitle = null)
    {
        $message = $bookTitle 
            ? "Book '{$bookTitle}' is not available for borrowing"
            : 'Book is not available for borrowing';
            
        parent::__construct($message, 422);
    }
}