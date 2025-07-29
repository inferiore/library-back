<?php

namespace App\Business\Exceptions;

class BookHasActiveBorrowingsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Cannot delete book with active borrowings',
            422
        );
    }
}