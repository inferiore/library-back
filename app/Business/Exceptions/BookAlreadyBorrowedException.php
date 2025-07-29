<?php

namespace App\Business\Exceptions;

class BookAlreadyBorrowedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'You already have an active borrowing for this book',
            422
        );
    }
}