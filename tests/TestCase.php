<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Clean up any open transactions before parent tearDown
        try {
            while (\DB::transactionLevel() > 0) {
                \DB::rollBack();
            }
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
        
        parent::tearDown();
    }
}
