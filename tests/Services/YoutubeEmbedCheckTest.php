<?php
namespace App\Tests\Services;

use App\Services\YoutubeEmbedCheck;
use PHPUnit\Framework\TestCase;

class YoutubeEmbedCheckTest extends TestCase
{
    public function testOnlyDefinitiveRefusalsMakeAVideoUnplayable(): void
    {
        foreach ([401, 403, 404] as $refused) {
            $this->assertFalse(YoutubeEmbedCheck::fromStatus($refused), (string) $refused);
        }
        // En cas de doute (OK, erreur serveur, redirection) on garde la video.
        foreach ([200, 301, 429, 500, 503, 0] as $fine) {
            $this->assertTrue(YoutubeEmbedCheck::fromStatus($fine), (string) $fine);
        }
    }

    public function testAMalformedVideoKeyIsRefusedWithoutAnyNetworkCall(): void
    {
        $this->assertFalse(YoutubeEmbedCheck::isEmbeddable(''));
        $this->assertFalse(YoutubeEmbedCheck::isEmbeddable('../../etc'));
    }
}
