<?php
namespace App\Tests\Utils;

use App\Utils\Security;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsStableWithinASession(): void
    {
        $this->assertSame(Security::csrfToken(), Security::csrfToken());
        $this->assertSame(64, strlen(Security::csrfToken()));
    }

    public function testCheckAcceptsTheCurrentTokenOnly(): void
    {
        $token = Security::csrfToken();

        $this->assertTrue(Security::checkCsrf($token));
        $this->assertFalse(Security::checkCsrf('faux'));
        $this->assertFalse(Security::checkCsrf(null));
        $this->assertFalse(Security::checkCsrf(''));
    }

    public function testEscapeHandlesQuotesAndNull(): void
    {
        $this->assertSame('&lt;b&gt;Zo&eacute;&lt;/b&gt;', Security::e('<b>Zoé</b>'));
        $this->assertSame('&quot;', Security::e('"'));
        $this->assertSame('', Security::e(null));
    }

    public function testTokenWorksWithoutAnActiveSessionInCli(): void
    {
        $this->assertSame(64, strlen(Security::csrfToken()));
    }

    public function testCheckRoundTripsWithoutAnActiveSessionInCli(): void
    {
        $token = Security::csrfToken();

        $this->assertTrue(Security::checkCsrf($token));
        $this->assertFalse(Security::checkCsrf('faux'));
    }
}
