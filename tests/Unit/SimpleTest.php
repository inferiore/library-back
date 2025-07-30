<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase
{
    public function test_basic_assertion()
    {
        $this->assertTrue(true);
        $this->assertEquals(2, 1 + 1);
    }

    public function test_string_operations()
    {
        $string = 'Hello World';
        $this->assertStringContainsString('World', $string);
        $this->assertEquals(11, strlen($string));
    }
}