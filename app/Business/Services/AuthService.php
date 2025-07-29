<?php

namespace App\Business\Services;

use App\Business\Exceptions\BusinessException;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService extends BaseService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    /**
     * Register a new user
     *
     * @param array $userData
     * @return array
     * @throws BusinessException
     */
    public function register(array $userData): array
    {
        $this->logOperation('user_registration', ['email' => $userData['email'], 'role' => $userData['role']]);

        return $this->executeTransaction(function () use ($userData) {
            // Validate business rules
            $this->validateBusinessRules([
                'valid_role' => fn() => in_array($userData['role'], ['librarian', 'member']) 
                    ? true : 'Invalid role specified',
                'unique_email' => fn() => $this->userRepository->isEmailUnique($userData['email']) 
                    ? true : 'Email already exists'
            ], $userData);

            // Create user
            $user = $this->userRepository->create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'role' => $userData['role'],
            ]);

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 'User registered successfully');
        });
    }

    /**
     * Authenticate user and generate token
     *
     * @param array $credentials
     * @return array
     * @throws BusinessException
     */
    public function login(array $credentials): array
    {
        $this->logOperation('user_login', ['email' => $credentials['email']]);

        // Validate business rules
        $this->validateBusinessRules([
            'valid_credentials' => function() use ($credentials) {
                if (!Auth::attempt($credentials)) {
                    throw ValidationException::withMessages([
                        'email' => ['The provided credentials are incorrect.'],
                    ]);
                }
                return true;
            }
        ]);

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    /**
     * Revoke current user token
     *
     * @return array
     * @throws BusinessException
     */
    public function logout(): array
    {
        $this->requireAuth();
        
        $this->logOperation('user_logout');

        auth()->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Successfully logged out');
    }

    /**
     * Get current authenticated user profile
     *
     * @return array
     * @throws BusinessException
     */
    public function getCurrentUser(): array
    {
        $this->requireAuth();

        return $this->successResponse([
            'user' => auth()->user()
        ], 'User profile retrieved successfully');
    }

    /**
     * Update user profile
     *
     * @param array $userData
     * @return array
     * @throws BusinessException
     */
    public function updateProfile(array $userData): array
    {
        $this->requireAuth();
        
        $user = auth()->user();
        $this->logOperation('profile_update', ['user_id' => $user->id]);

        return $this->executeTransaction(function () use ($user, $userData) {
            // Validate business rules
            $this->validateBusinessRules([
                'unique_email' => function() use ($userData, $user) {
                    if (isset($userData['email']) && $userData['email'] !== $user->email) {
                        return $this->userRepository->isEmailUnique($userData['email'], $user->id) 
                            ? true : 'Email already exists';
                    }
                    return true;
                }
            ], $userData);

            // Update allowed fields
            $allowedFields = ['name', 'email'];
            $updateData = array_intersect_key($userData, array_flip($allowedFields));
            
            if (isset($userData['password'])) {
                $updateData['password'] = Hash::make($userData['password']);
            }

            $this->userRepository->updateProfile($user->id, $updateData);

            return $this->successResponse([
                'user' => $user->fresh()
            ], 'Profile updated successfully');
        });
    }

    /**
     * Change user password
     *
     * @param string $currentPassword
     * @param string $newPassword
     * @return array
     * @throws BusinessException
     */
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        $this->requireAuth();
        
        $user = auth()->user();
        $this->logOperation('password_change', ['user_id' => $user->id]);

        return $this->executeTransaction(function () use ($user, $currentPassword, $newPassword) {
            // Validate business rules
            $this->validateBusinessRules([
                'current_password_valid' => fn() => Hash::check($currentPassword, $user->password) 
                    ? true : 'Current password is incorrect',
                'password_different' => fn() => !Hash::check($newPassword, $user->password) 
                    ? true : 'New password must be different from current password'
            ]);

            $this->userRepository->updateProfile($user->id, [
                'password' => Hash::make($newPassword)
            ]);

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            return $this->successResponse(null, 'Password changed successfully. Please login again.');
        });
    }
}