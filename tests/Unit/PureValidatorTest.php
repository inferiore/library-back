<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PureValidatorTest extends TestCase
{
    public function test_user_validator_exists()
    {
        $this->assertTrue(class_exists(\App\Business\Validators\UserValidator::class));
    }

    public function test_password_validation_short()
    {
        $this->expectException(\App\Business\Exceptions\BusinessException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters long');

        \App\Business\Validators\UserValidator::validatePasswordStrength('Short1!');
    }

    public function test_password_validation_success()
    {
        // This should not throw exception
        \App\Business\Validators\UserValidator::validatePasswordStrength('ValidPass123!');
        $this->assertTrue(true);
    }

    public function test_role_validation_success()
    {
        \App\Business\Validators\UserValidator::validateRole('librarian');
        \App\Business\Validators\UserValidator::validateRole('member');
        $this->assertTrue(true);
    }

    public function test_role_validation_failure()
    {
        $this->expectException(\App\Business\Exceptions\BusinessException::class);
        $this->expectExceptionMessage('Invalid role. Allowed roles: librarian, member');

        \App\Business\Validators\UserValidator::validateRole('admin');
    }
}