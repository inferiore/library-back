<?php

namespace App\Business\Validators;

use App\Business\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;

class ModelValidator
{
    /**
     * Validate that a model exists
     *
     * @param Model|null $model
     * @param string $type
     * @throws BusinessException
     */
    public static function validateExists(?Model $model, string $type): void
    {
        if (!$model) {
            throw new BusinessException("{$type} not found", 404);
        }
    }

    /**
     * Validate that a model exists by ID using repository
     *
     * @param mixed $repository
     * @param int $id
     * @param string $type
     * @return Model
     * @throws BusinessException
     */
    public static function validateExistsById($repository, int $id, string $type): Model
    {
        $model = $repository->find($id);
        
        if (!$model) {
            throw new BusinessException("{$type} not found", 404);
        }
        
        return $model;
    }

    /**
     * Validate that a model exists with a specific condition
     *
     * @param string $modelClass
     * @param array $conditions
     * @param string $type
     * @return Model
     * @throws BusinessException
     */
    public static function validateExistsByCondition(string $modelClass, array $conditions, string $type): Model
    {
        $query = $modelClass::query();
        
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        
        $model = $query->first();
        
        if (!$model) {
            $conditionString = implode(', ', array_map(
                fn($key, $value) => "{$key}: {$value}", 
                array_keys($conditions), 
                $conditions
            ));
            throw new BusinessException("{$type} not found with conditions: {$conditionString}", 404);
        }
        
        return $model;
    }

    /**
     * Validate that multiple models exist
     *
     * @param array $models Array of models to validate
     * @param array $types Array of type names corresponding to models
     * @throws BusinessException
     */
    public static function validateMultipleExist(array $models, array $types): void
    {
        if (count($models) !== count($types)) {
            throw new BusinessException('Models and types arrays must have the same length');
        }

        foreach ($models as $index => $model) {
            self::validateExists($model, $types[$index]);
        }
    }

    /**
     * Validate that a model exists and belongs to a specific user
     *
     * @param Model|null $model
     * @param int $userId
     * @param string $type
     * @param string $userIdField
     * @throws BusinessException
     */
    public static function validateExistsAndBelongsToUser(
        ?Model $model, 
        int $userId, 
        string $type, 
        string $userIdField = 'user_id'
    ): void {
        self::validateExists($model, $type);
        
        if ($model->{$userIdField} !== $userId) {
            throw new BusinessException("{$type} does not belong to the specified user", 403);
        }
    }

    /**
     * Validate that a model exists and has a specific status
     *
     * @param Model|null $model
     * @param string $expectedStatus
     * @param string $type
     * @param string $statusField
     * @throws BusinessException
     */
    public static function validateExistsWithStatus(
        ?Model $model, 
        string $expectedStatus, 
        string $type, 
        string $statusField = 'status'
    ): void {
        self::validateExists($model, $type);
        
        if ($model->{$statusField} !== $expectedStatus) {
            throw new BusinessException(
                "{$type} must have status '{$expectedStatus}', current status: '{$model->{$statusField}}'", 
                400
            );
        }
    }

    /**
     * Validate that a model exists and is not soft deleted
     *
     * @param Model|null $model
     * @param string $type
     * @throws BusinessException
     */
    public static function validateExistsAndNotDeleted(?Model $model, string $type): void
    {
        self::validateExists($model, $type);
        
        if (method_exists($model, 'trashed') && $model->trashed()) {
            throw new BusinessException("{$type} has been deleted", 410);
        }
    }

    /**
     * Validate that a collection is not empty
     *
     * @param mixed $collection
     * @param string $type
     * @throws BusinessException
     */
    public static function validateCollectionNotEmpty($collection, string $type): void
    {
        if (!$collection || (is_countable($collection) && count($collection) === 0)) {
            throw new BusinessException("No {$type} found", 404);
        }
    }

    /**
     * Validate that a model has required relationships loaded
     *
     * @param Model $model
     * @param array $relationships
     * @param string $type
     * @throws BusinessException
     */
    public static function validateRelationshipsLoaded(Model $model, array $relationships, string $type): void
    {
        foreach ($relationships as $relationship) {
            if (!$model->relationLoaded($relationship)) {
                throw new BusinessException(
                    "{$type} must have '{$relationship}' relationship loaded", 
                    500
                );
            }
        }
    }

    /**
     * Validate model state for specific operation
     *
     * @param Model $model
     * @param callable $validator
     * @param string $operation
     * @param string $type
     * @throws BusinessException
     */
    public static function validateModelState(
        Model $model, 
        callable $validator, 
        string $operation, 
        string $type
    ): void {
        $result = $validator($model);
        
        if ($result !== true) {
            $message = is_string($result) 
                ? $result 
                : "{$type} is not in valid state for {$operation}";
            throw new BusinessException($message, 400);
        }
    }
}