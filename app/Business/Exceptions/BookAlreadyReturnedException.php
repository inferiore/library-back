<?php

namespace App\Business\Exceptions;

class BookAlreadyReturnedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Book has already been returned',
            422
        );
    }
}