<?php
namespace App\Tests\Utils;

use App\Utils\Redirects;
use PHPUnit\Framework\TestCase;

class RedirectsTest extends TestCase
{
    public function testAKnownPageIsKept(): void
    {
        $this->assertSame('/history.php', Redirects::sanitize('/history.php'));
        $this->assertSame('/awards.php', Redirects::sanitize('/awards.php'));
    }

    public function testAQueryStringSurvives(): void
    {
        $this->assertSame('/movie.php?id=42', Redirects::sanitize('/movie.php?id=42'));
        $this->assertSame('/pool.php?pool=kid&sort=votes', Redirects::sanitize('/pool.php?pool=kid&sort=votes'));
    }

    public function testAnAbsoluteUrlIsRefused(): void
    {
        foreach ([
            'https://evil.example/phishing',
            'http://evil.example',
            '//evil.example',
            '/\\evil.example',
            'javascript:alert(1)',
        ] as $hostile) {
            $this->assertNull(
                Redirects::sanitize($hostile),
                "{$hostile} ne doit jamais servir de destination"
            );
        }
    }

    public function testAHeaderSplittingAttemptIsRefused(): void
    {
        $this->assertNull(Redirects::sanitize("/history.php\r\nLocation: https://evil.example"));
        $this->assertNull(Redirects::sanitize("/history.php\nSet-Cookie: a=b"));
    }

    public function testAnUnknownPathIsRefused(): void
    {
        $this->assertNull(Redirects::sanitize('/api/vote.php'));
        $this->assertNull(Redirects::sanitize('/../config/config.php'));
        $this->assertNull(Redirects::sanitize('/data/filmi.sqlite'));
    }

    public function testTheProfileChoiceItselfIsNotADestination(): void
    {
        // Sinon choisir son profil renverrait a l'ecran de choix, en boucle.
        $this->assertNull(Redirects::sanitize('/index.php'));
    }

    public function testAnOverlongValueIsRefused(): void
    {
        $this->assertNull(Redirects::sanitize('/history.php?x=' . str_repeat('a', 300)));
    }

    public function testTargetFallsBackToTheHomePage(): void
    {
        $this->assertSame('/tonight.php', Redirects::target(null));
        $this->assertSame('/tonight.php', Redirects::target('https://evil.example'));
        $this->assertSame('/history.php', Redirects::target('/history.php'));
    }

    public function testTheProfileChoiceUrlCarriesTheRequestedPage(): void
    {
        $this->assertSame(
            '/index.php?next=' . urlencode('/history.php'),
            Redirects::profileChoiceUrl('/history.php')
        );
        $this->assertSame(
            '/index.php?next=' . urlencode('/movie.php?id=7'),
            Redirects::profileChoiceUrl('/movie.php?id=7')
        );
    }

    public function testAPostIsNeverReplayedAfterTheProfileChoice(): void
    {
        // Le corps de la requete est perdu de toute facon : y renvoyer en GET
        // afficherait au mieux une page vide, au pire referait une action.
        $this->assertSame('/index.php', Redirects::profileChoiceUrl('/movie.php?id=7', 'POST'));
    }

    public function testAnUntrustedRequestUriGivesThePlainChoiceUrl(): void
    {
        $this->assertSame('/index.php', Redirects::profileChoiceUrl('https://evil.example'));
        $this->assertSame('/index.php', Redirects::profileChoiceUrl(null));
    }
}
