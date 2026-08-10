<?php
namespace App\Tests\Utils;

use App\Utils\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_COOKIE[Session::COOKIE]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[Session::COOKIE]);
    }

    public function testNoCookieMeansNoProfile(): void
    {
        $this->assertNull(Session::currentProfileId());
    }

    public function testDigitsAreRead(): void
    {
        $_COOKIE[Session::COOKIE] = '3';

        $this->assertSame(3, Session::currentProfileId());
    }

    public function testNonNumericValuesAreRefused(): void
    {
        foreach (['', 'abc', '2; DROP TABLE profiles', '-1', '1.5', ' 2'] as $hostile) {
            $_COOKIE[Session::COOKIE] = $hostile;
            $this->assertNull(Session::currentProfileId(), "Valeur acceptee a tort : {$hostile}");
        }
    }
}
