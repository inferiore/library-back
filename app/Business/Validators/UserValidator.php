<?php

namespace App\Business\Validators;

use App\Models\User;
use App\Business\Exceptions\BusinessException;
use Illuminate\Support\Facades\Hash;

class UserValidator
{
    /**
     * Validate user registration data
     *
     * @param array $userData
     * @throws BusinessException
     */
    public static function validateRegistrationData(array $userData): void
    {
        // Validate required fields
        $requiredFields = ['name', 'email', 'password', 'role'];
        foreach ($requiredFields as $field) {
            if (empty($userData[$field])) {
                throw new BusinessException("Field '{$field}' is required");
            }
        }

        // Validate email format
        if (!self::isValidEmail($userData['email'])) {
            throw new BusinessException('Invalid email format');
        }

        // Validate email uniqueness
        self::validateUniqueEmail($userData['email']);

        // Validate password strength
        self::validatePasswordStrength($userData['password']);

        // Validate role
        self::validateRole($userData['role']);

        // Validate name format
        self::validateName($userData['name']);
    }

    /**
     * Validate email uniqueness
     *
     * @param string $email
     * @param int|null $excludeId
     * @throws BusinessException
     */
    public static function validateUniqueEmail(string $email, ?int $excludeId = null): void
    {
        $query = User::where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new BusinessException('Email already exists');
        }
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @throws BusinessException
     */
    public static function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 8) {
            throw new BusinessException('Password must be at least 8 characters long');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new BusinessException('Password must contain at least one uppercase letter');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new BusinessException('Password must contain at least one lowercase letter');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new BusinessException('Password must contain at least one number');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new BusinessException('Password must contain at least one special character');
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', '12345678', 'qwerty123', 'abc123456', 'password123'
        ];

        if (in_array(strtolower($password), $weakPasswords)) {
            throw new BusinessException('Password is too common, please choose a stronger password');
        }
    }

    /**
     * Validate user role
     *
     * @param string $role
     * @throws BusinessException
     */
    public static function validateRole(string $role): void
    {
        $allowedRoles = ['librarian', 'member'];
        
        if (!in_array($role, $allowedRoles)) {
            throw new BusinessException('Invalid role. Allowed roles: ' . implode(', ', $allowedRoles));
        }
    }

    /**
     * Validate user name
     *
     * @param string $name
     * @throws BusinessException
     */
    public static function validateName(string $name): void
    {
        if (strlen($name) < 2) {
            throw new BusinessException('Name must be at least 2 characters long');
        }

        if (strlen($name) > 100) {
            throw new BusinessException('Name cannot exceed 100 characters');
        }

        if (!preg_match('/^[a-zA-Z\s\'-]+$/', $name)) {
            throw new BusinessException('Name can only contain letters, spaces, hyphens, and apostrophes');
        }
    }

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    private static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate current password for password change
     *
     * @param User $user
     * @param string $currentPassword
     * @throws BusinessException
     */
    public static function validateCurrentPassword(User $user, string $currentPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new BusinessException('Current password is incorrect');
        }
    }

    /**
     * Validate new password is different from current
     *
     * @param User $user
     * @param string $newPassword
     * @throws BusinessException
     */
    public static function validatePasswordDifferent(User $user, string $newPassword): void
    {
        if (Hash::check($newPassword, $user->password)) {
            throw new BusinessException('New password must be different from current password');
        }
    }

    /**
     * Validate profile update data
     *
     * @param User $user
     * @param array $updateData
     * @throws BusinessException
     */
    public static function validateProfileUpdate(User $user, array $updateData): void
    {
        // Validate name if provided
        if (isset($updateData['name'])) {
            self::validateName($updateData['name']);
        }

        // Validate email if provided
        if (isset($updateData['email'])) {
            if (!self::isValidEmail($updateData['email'])) {
                throw new BusinessException('Invalid email format');
            }

            if ($updateData['email'] !== $user->email) {
                self::validateUniqueEmail($updateData['email'], $user->id);
            }
        }

        // Validate password if provided
        if (isset($updateData['password'])) {
            self::validatePasswordStrength($updateData['password']);
            self::validatePasswordDifferent($user, $updateData['password']);
        }

        // Don't allow role changes through profile update
        if (isset($updateData['role'])) {
            throw new BusinessException('Role cannot be changed through profile update');
        }
    }

    /**
     * Validate user can perform action based on role
     *
     * @param User $user
     * @param string $action
     * @throws BusinessException
     */
    public static function validateUserPermission(User $user, string $action): void
    {
        $permissions = [
            'create_book' => ['librarian'],
            'update_book' => ['librarian'],
            'delete_book' => ['librarian'],
            'return_book' => ['librarian'],
            'view_all_borrowings' => ['librarian'],
            'view_system_stats' => ['librarian'],
            'extend_overdue_borrowing' => ['librarian'],
            'borrow_book' => ['librarian', 'member'],
            'view_own_borrowings' => ['librarian', 'member'],
            'extend_borrowing' => ['librarian', 'member']
        ];

        if (!isset($permissions[$action])) {
            throw new BusinessException("Unknown action: {$action}");
        }

        if (!in_array($user->role, $permissions[$action])) {
            throw new BusinessException("Insufficient permissions for action: {$action}");
        }
    }

    /**
     * Validate user account status
     *
     * @param User $user
     * @throws BusinessException
     */
    public static function validateAccountStatus(User $user): void
    {
        // This would be implemented if we had account status/suspension features
        if (isset($user->status) && $user->status === 'suspended') {
            throw new BusinessException('Account is suspended');
        }

        if (isset($user->email_verified_at) && !$user->email_verified_at) {
            throw new BusinessException('Email verification required');
        }
    }

    /**
     * Validate login credentials format
     *
     * @param array $credentials
     * @throws BusinessException
     */
    public static function validateLoginCredentials(array $credentials): void
    {
        if (empty($credentials['email'])) {
            throw new BusinessException('Email is required');
        }

        if (empty($credentials['password'])) {
            throw new BusinessException('Password is required');
        }

        if (!self::isValidEmail($credentials['email'])) {
            throw new BusinessException('Invalid email format');
        }
    }

    /**
     * Validate user has no active borrowings for account deletion
     *
     * @param User $user
     * @throws BusinessException
     */
    public static function validateCanDeleteAccount(User $user): void
    {
        $activeBorrowings = $user->borrowings()->active()->count();
        
        if ($activeBorrowings > 0) {
            throw new BusinessException("Cannot delete account with {$activeBorrowings} active borrowings");
        }

        $overdueBorrowings = $user->borrowings()->overdue()->count();
        
        if ($overdueBorrowings > 0) {
            throw new BusinessException("Cannot delete account with {$overdueBorrowings} overdue borrowings");
        }
    }
}