<?php
namespace App\Tests\Utils;

use App\Utils\Avatars;
use PHPUnit\Framework\TestCase;

class AvatarsTest extends TestCase
{
    public function testCatalogueHasTwentyFourAvatars(): void
    {
        $this->assertCount(24, Avatars::all());
    }

    public function testEveryAvatarBelongsToADeclaredFamily(): void
    {
        foreach (Avatars::all() as $key => $avatar) {
            $this->assertArrayHasKey(
                $avatar['family'],
                Avatars::FAMILIES,
                "Avatar {$key} référence une famille inconnue"
            );
            $this->assertNotSame('', $avatar['label'], "Avatar {$key} sans libellé");
        }
    }

    public function testExpectedKeysArePresent(): void
    {
        foreach (['alien', 'dinosaure', 'aviatrice', 'detective', 'gumiho', 'dokkaebi', 'idole', 'fan'] as $key) {
            $this->assertTrue(Avatars::exists($key), "Clé manquante : {$key}");
        }
        $this->assertFalse(Avatars::exists('mickey'));
    }

    public function testByFamilyCoversAllAvatars(): void
    {
        $total = array_sum(array_map('count', Avatars::byFamily()));

        $this->assertSame(24, $total);
        $this->assertSame(array_keys(Avatars::FAMILIES), array_keys(Avatars::byFamily()));
    }

    public function testRenderProducesInlineSvgWithoutExternalReference(): void
    {
        $svg = Avatars::render('alien', 'violet', 64);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 96 96"', $svg);
        $this->assertStringContainsString('width="64"', $svg);
        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringNotContainsString('xlink:href', $svg);
        $this->assertStringNotContainsString('href=', $svg);
        $this->assertStringNotContainsString('url(', $svg);
        $this->assertStringNotContainsString('<text', $svg);
        $this->assertSame(
            1,
            preg_match_all('/https?:/', $svg),
            'La seule URL autorisee est le namespace SVG'
        );
    }

    public function testEveryAvatarRendersValidSvg(): void
    {
        foreach (array_keys(Avatars::all()) as $key) {
            $svg = Avatars::render($key);
            $this->assertStringStartsWith('<svg', $svg, "Rendu invalide pour {$key}");
            $this->assertNotFalse(
                simplexml_load_string($svg),
                "SVG mal formé pour {$key}"
            );
        }
    }

    public function testUnknownKeyFallsBackWithoutError(): void
    {
        $this->assertSame(Avatars::render(Avatars::FALLBACK), Avatars::render('inconnu'));
    }
}
