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

    public function testIsHttpsIsFalseOnPlainLocalDev(): void
    {
        unset($_SERVER['HTTPS']);

        $this->assertFalse(Session::isHttps());
    }

    public function testIsHttpsIsTrueBehindHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue(Session::isHttps());

        unset($_SERVER['HTTPS']);
    }

    public function testStartHardensSessionCookieIniSettingsBeforeStarting(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SERVER['HTTPS'] = 'on';

        Session::start();

        $this->assertSame('1', ini_get('session.cookie_httponly'));
        $this->assertSame('Lax', ini_get('session.cookie_samesite'));
        $this->assertSame('1', ini_get('session.cookie_secure'));

        session_write_close();
        unset($_SERVER['HTTPS']);
    }

    public function testStartLeavesTheSecureFlagOffOverPlainHttp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($_SERVER['HTTPS']);

        Session::start();

        $this->assertSame('0', ini_get('session.cookie_secure'));

        session_write_close();
    }
}
