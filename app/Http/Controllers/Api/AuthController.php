<?php

namespace App\Http\Controllers\Api;

use App\Business\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    /**
     * Register a new user
     *
     * Creates a new user account with the specified role (librarian or member).
     *
     * @group Authentication
     */
    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => $result['message'],
            'user' => new UserResource($result['data']['user']),
            'access_token' => $result['data']['access_token'],
            'token_type' => $result['data']['token_type'],
        ], 201);
    }

    /**
     * User login
     *
     * Authenticate a user and return an access token.
     *
     * @group Authentication
     */
    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->only('email', 'password'));

        return response()->json([
            'message' => $result['message'],
            'user' => new UserResource($result['data']['user']),
            'access_token' => $result['data']['access_token'],
            'token_type' => $result['data']['token_type'],
        ]);
    }

    /**
     * User logout
     *
     * Revoke the current access token.
     *
     * @group Authentication
     */
    public function logout(Request $request)
    {
        $result = $this->authService->logout();

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    /**
     * Get current user profile
     *
     * Retrieve the authenticated user's profile information.
     *
     * @group Authentication
     */
    public function me(Request $request)
    {
        $result = $this->authService->getCurrentUser();

        return response()->json([
            'user' => new UserResource($result['data']['user']),
        ]);
    }
}
