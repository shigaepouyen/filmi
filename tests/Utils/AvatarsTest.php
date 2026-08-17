<?php
namespace App\Tests\Utils;

use App\Utils\Avatars;
use PHPUnit\Framework\TestCase;

class AvatarsTest extends TestCase
{
    public function testCatalogueHasTwentyAvatars(): void
    {
        $this->assertCount(20, Avatars::all());
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
        foreach ([
            'crabe', 'meduse', 'mite', 'scarabee', 'araignee', 'ver',
            'chasseur', 'navette', 'soucoupe', 'intercepteur', 'croiseur',
            'tourelle', 'satellite', 'drone', 'sentinelle', 'mine',
            'blob', 'oeil', 'champignon', 'chauve',
        ] as $key) {
            $this->assertTrue(Avatars::exists($key), "Clé manquante : {$key}");
        }
        $this->assertFalse(Avatars::exists('mickey'));
        // Clés des rosters précédents : les migrations 6, 9 et 10 les réaffectent.
        foreach (['tentacule', 'aviatrice', 'idole', 'gumiho', 'slime', 'renard', 'pirate'] as $ancienne) {
            $this->assertFalse(Avatars::exists($ancienne), "{$ancienne} a été retiré du roster");
        }
    }

    public function testByFamilyCoversAllAvatars(): void
    {
        $total = array_sum(array_map('count', Avatars::byFamily()));

        $this->assertSame(20, $total);
        $this->assertSame(array_keys(Avatars::FAMILIES), array_keys(Avatars::byFamily()));
    }

    public function testRenderProducesInlineSvgWithoutExternalReference(): void
    {
        $svg = Avatars::render('crabe', 'violet', 64);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 16 16"', $svg);
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

    public function testEverySpriteStaysUnderTheRectBudget(): void
    {
        foreach (array_keys(Avatars::all()) as $key) {
            $svg = Avatars::render($key);
            $rects = substr_count($svg, '<rect');
            // Une silhouette monochrome tient en une trentaine de rectangles.
            // Au-delà de 45, c'est que le sprite part en confettis.
            $this->assertLessThanOrEqual(45, $rects, "{$key} dépasse le budget de rects");
        }
    }

    public function testEverySpriteUsesTheThemeAccentSomewhere(): void
    {
        foreach (array_keys(Avatars::all()) as $key) {
            $svgViolet = Avatars::render($key, 'violet');
            $svgSky = Avatars::render($key, 'sky');
            $this->assertNotSame($svgViolet, $svgSky, "{$key} ne semble pas utiliser la couleur de theme");
        }
    }
}
