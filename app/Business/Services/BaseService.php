<?php

namespace App\Business\Services;

use App\Business\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    /**
     * Execute a database transaction with proper error handling
     *
     * @param callable $callback
     * @return mixed
     * @throws BusinessException
     */
    protected function executeTransaction(callable $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (\Exception $e) {
            Log::error('Service transaction failed: ' . $e->getMessage(), [
                'service' => static::class,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new BusinessException(
                'Operation failed due to system error',
                500,
                $e
            );
        }
    }

    /**
     * Validate business rules before executing operations
     *
     * @param array $rules
     * @param array $data
     * @throws BusinessException
     */
    protected function validateBusinessRules(array $rules, array $data = []): void
    {
        foreach ($rules as $rule => $validator) {
            if (is_callable($validator)) {
                $result = $validator($data);
                if ($result !== true) {
                    throw new BusinessException($result ?: "Validation failed for rule: {$rule}");
                }
            }
        }
    }

    /**
     * Log business operation
     *
     * @param string $operation
     * @param array $context
     */
    protected function logOperation(string $operation, array $context = []): void
    {
        Log::info("Business operation: {$operation}", array_merge([
            'service' => static::class,
            'user_id' => auth()->id()
        ], $context));
    }

    /**
     * Check if user has required role
     *
     * @param string $role
     * @throws BusinessException
     */
    protected function requireRole(string $role): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new BusinessException('Authentication required', 401);
        }

        if (!$user->hasRole($role)) {
            throw new BusinessException("Access denied. Required role: {$role}", 403);
        }
    }

    /**
     * Check if user is authenticated
     *
     * @throws BusinessException
     */
    protected function requireAuth(): void
    {
        if (!auth()->check()) {
            throw new BusinessException('Authentication required', 401);
        }
    }

    /**
     * Format success response data
     *
     * @param mixed $data
     * @param string $message
     * @return array
     */
    protected function successResponse($data, string $message = 'Operation completed successfully'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Validate model exists
     *
     * @param Model|null $model
     * @param string $type
     * @throws BusinessException
     */
    protected function ensureModelExists(?Model $model, string $type): void
    {
        if (!$model) {
            throw new BusinessException("{$type} not found", 404);
        }
    }
}