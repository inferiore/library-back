<?php

namespace App\Business\Exceptions;

class UnauthorizedException extends BusinessException
{
    public function __construct(string $message = 'Unauthorized access')
    {
        parent::__construct($message, 403);
    }
}