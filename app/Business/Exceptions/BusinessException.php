<?php

namespace App\Business\Exceptions;

use Exception;

class BusinessException extends Exception
{
    protected $statusCode;
    protected $context;

    public function __construct(
        string $message = 'Business rule violation',
        int $statusCode = 422,
        Exception $previous = null,
        array $context = []
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->context = $context;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'context' => $this->context
        ], $this->statusCode);
    }
}