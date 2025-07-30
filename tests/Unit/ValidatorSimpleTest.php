<?php

namespace Tests\Unit;

use App\Business\Validators\UserValidator;
use App\Business\Exceptions\BusinessException;
use Tests\TestCase;

class ValidatorSimpleTest extends TestCase
{
    public function test_validates_password_strength_minimum_length()
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters long');

        UserValidator::validatePasswordStrength('Short1!');
    }

    public function test_validates_password_strength_success()
    {
        UserValidator::validatePasswordStrength('ValidPass123!');
        $this->assertTrue(true);
    }

    public function test_validates_role_success()
    {
        UserValidator::validateRole('librarian');
        UserValidator::validateRole('member');
        $this->assertTrue(true);
    }

    public function test_validates_invalid_role()
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Invalid role. Allowed roles: librarian, member');

        UserValidator::validateRole('admin');
    }
}