<?php

namespace Tests\Unit\Business\Services;

use App\Business\Services\AuthService;
use App\Business\Exceptions\BusinessException;
use App\Data\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Mockery;

class AuthServiceUnitTest extends TestCase
{
    use DatabaseTransactions;
    
    protected AuthService $authService;
    protected $userRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations for testing
        $this->artisan('migrate', ['--database' => 'sqlite']);
        
        $this->userRepositoryMock = Mockery::mock(UserRepositoryInterface::class);
        $this->authService = new AuthService($this->userRepositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_creates_user_successfully()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'role' => 'member'
        ];

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'created-user@example.com', // Different email to avoid conflict
            'role' => 'member'
        ]);

        // Mock repository create to return the created user
        $this->userRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['name'] === 'John Doe' 
                    && $data['email'] === 'john@example.com'
                    && Hash::check('Password123!', $data['password'])
                    && $data['role'] === 'member';
            }))
            ->andReturn($user);

        $result = $this->authService->register($userData);

        $this->assertTrue($result['success']);
        $this->assertEquals('User registered successfully', $result['message']);
        $this->assertEquals($user->id, $result['data']['user']->id);
        $this->assertArrayHasKey('access_token', $result['data']);
        $this->assertEquals('Bearer', $result['data']['token_type']);
    }

    public function test_register_throws_exception_on_validation_failure()
    {
        $userData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'role' => 'invalid-role'
        ];

        $this->expectException(BusinessException::class);

        $this->authService->register($userData);
    }

    public function test_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $result = $this->authService->login($credentials);

        $this->assertTrue($result['success']);
        $this->assertEquals('Login successful', $result['message']);
        $this->assertEquals($user->id, $result['data']['user']->id);
        $this->assertArrayHasKey('access_token', $result['data']);
        $this->assertEquals('Bearer', $result['data']['token_type']);
    }

    public function test_login_throws_exception_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password')
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ];

        $this->expectException(ValidationException::class);

        $this->authService->login($credentials);
    }

    public function test_logout_revokes_current_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        
        // Set the current access token for the user
        $user->withAccessToken($token->accessToken);
        $this->actingAs($user, 'sanctum');

        $result = $this->authService->logout();

        $this->assertTrue($result['success']);
        $this->assertEquals('Successfully logged out', $result['message']);
        $this->assertNull($result['data']);
        
        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id
        ]);
    }

    public function test_get_current_user_returns_authenticated_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $result = $this->authService->getCurrentUser();

        $this->assertTrue($result['success']);
        $this->assertEquals('User profile retrieved successfully', $result['message']);
        $this->assertEquals($user->id, $result['data']['user']->id);
    }

    public function test_update_profile_successfully()
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com'
        ]);
        
        $this->actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];

        $this->userRepositoryMock
            ->shouldReceive('updateProfile')
            ->once()
            ->with($user->id, $updateData)
            ->andReturn(true);

        $result = $this->authService->updateProfile($updateData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Profile updated successfully', $result['message']);
    }

    public function test_change_password_successfully()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPass123!')
        ]);
        
        $this->actingAs($user);

        $this->userRepositoryMock
            ->shouldReceive('updateProfile')
            ->once()
            ->with($user->id, Mockery::on(function ($data) {
                return isset($data['password']) && Hash::check('NewPass123!', $data['password']);
            }))
            ->andReturn(true);

        $result = $this->authService->changePassword('CurrentPass123!', 'NewPass123!');

        $this->assertTrue($result['success']);
        $this->assertEquals('Password changed successfully. Please login again.', $result['message']);
        $this->assertNull($result['data']);
    }

    public function test_change_password_throws_exception_for_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!')
        ]);
        
        $this->actingAs($user);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        $this->authService->changePassword('WrongPassword', 'NewPass123!');
    }

    public function test_requires_authentication_for_protected_methods()
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Authentication required');

        $this->authService->logout();
    }
}