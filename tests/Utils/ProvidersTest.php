<?php
namespace App\Tests\Utils;

use App\Utils\Providers;
use PHPUnit\Framework\TestCase;

class ProvidersTest extends TestCase
{
    /** Les 30 noms réellement présents en production. */
    public static function realNamesProvider(): array
    {
        return array_map(static fn (string $n) => [$n], [
            'ARTE Boutique', 'Amazon Prime Video', 'Amazon Prime Video with Ads',
            'Amazon Video', 'Apple TV Store', 'Canal VOD', 'Canal+',
            'Cine+ OCS Amazon Channel ', 'Disney Plus', 'Google Play Movies',
            'HBO Max', 'HBO Max Amazon Channel', 'LaCinetek', 'MGM Amazon Channel',
            'Molotov TV', 'Netflix', 'Netflix Standard with Ads', 'Orange VOD',
            'Paramount Plus', 'Paramount Plus Premium', 'Paramount+ Amazon Channel',
            'Pathé Home', 'Premiere Max', 'Rakuten TV', 'SFR Play',
            'Sony Pictures Core Amazon Channel', 'Sooner', 'TF1+',
            'VIVA by videofutur', 'YouTube',
        ]);
    }

    /** @dataProvider realNamesProvider */
    public function testBrandNeverThrowsAndNeverReturnsEmptyOnAnyRealName(string $name): void
    {
        $brand = Providers::brand($name);

        $this->assertNotSame('', $brand);
    }

    public function testNetflixVariantsShareABrand(): void
    {
        $this->assertSame(Providers::brand('Netflix'), Providers::brand('Netflix Standard with Ads'));
        $this->assertSame('Netflix', Providers::brand('Netflix Standard with Ads'));
    }

    public function testHboMaxVariantsShareABrand(): void
    {
        $this->assertSame(Providers::brand('HBO Max'), Providers::brand('HBO Max Amazon Channel'));
        $this->assertSame('HBO Max', Providers::brand('HBO Max Amazon Channel'));
    }

    public function testParamountVariantsAllGiveParamountPlus(): void
    {
        $this->assertSame('Paramount+', Providers::brand('Paramount Plus'));
        $this->assertSame('Paramount+', Providers::brand('Paramount Plus Premium'));
        $this->assertSame('Paramount+', Providers::brand('Paramount+ Amazon Channel'));
    }

    public function testDisneyPlusGivesDisneyPlusSign(): void
    {
        $this->assertSame('Disney+', Providers::brand('Disney Plus'));
    }

    public function testCinePlusOcsAmazonChannelWithTrailingSpaceGivesCinePlusOcs(): void
    {
        $this->assertSame('Cine+ OCS', Providers::brand('Cine+ OCS Amazon Channel '));
    }

    public function testMgmAmazonChannelGivesMgm(): void
    {
        $this->assertSame('MGM', Providers::brand('MGM Amazon Channel'));
    }

    public function testCanalPlusAndCanalVodStayDistinct(): void
    {
        $this->assertNotSame(Providers::brand('Canal+'), Providers::brand('Canal VOD'));
        $this->assertSame('Canal+', Providers::brand('Canal+'));
        $this->assertSame('Canal VOD', Providers::brand('Canal VOD'));
    }

    public function testAmazonPrimeVideoAndAmazonVideoStayDistinct(): void
    {
        $this->assertNotSame(Providers::brand('Amazon Prime Video'), Providers::brand('Amazon Video'));
    }

    public function testNormaliseAcceptsTheOldStringShapeAndProducesTheNewObjectShape(): void
    {
        $result = Providers::normalise(['Netflix', 'Max']);

        $this->assertSame([
            ['id' => null, 'name' => 'Netflix', 'logo' => null],
            ['id' => null, 'name' => 'Max', 'logo' => null],
        ], $result);
    }

    public function testNormaliseAcceptsTheNewObjectShape(): void
    {
        $result = Providers::normalise([
            ['id' => 8, 'name' => 'Netflix', 'logo' => '/netflix.jpg'],
        ]);

        $this->assertSame([
            ['id' => 8, 'name' => 'Netflix', 'logo' => '/netflix.jpg'],
        ], $result);
    }

    public function testNormaliseOnOldShapeAndNewShapeProducesTheSameNamesForTheSameProvider(): void
    {
        $old = Providers::normalise(['Netflix']);
        $new = Providers::normalise([['id' => 8, 'name' => 'Netflix', 'logo' => '/n.jpg']]);

        $this->assertSame($old[0]['name'], $new[0]['name']);
    }

    public function testNormaliseIgnoresNonArrayInput(): void
    {
        $this->assertSame([], Providers::normalise(null));
        $this->assertSame([], Providers::normalise('not an array'));
    }

    public function testNormaliseSkipsEmptyNames(): void
    {
        $this->assertSame([], Providers::normalise(['', '   ']));
        $this->assertSame([], Providers::normalise([['id' => 1, 'name' => '']]));
    }

    public function testLogoUrlBuildsTheFullTmdbUrl(): void
    {
        $this->assertSame(
            'https://image.tmdb.org/t/p/w92/netflix.jpg',
            Providers::logoUrl('/netflix.jpg')
        );
    }

    public function testLogoUrlReturnsNullForNullOrEmptyPath(): void
    {
        $this->assertNull(Providers::logoUrl(null));
        $this->assertNull(Providers::logoUrl(''));
    }

    public function testBrandsDedupesByBrandAcrossMultipleRawShapesAndKeepsALogo(): void
    {
        $providers = [
            ['id' => 8, 'name' => 'Netflix', 'logo' => '/netflix.jpg'],
            ['id' => 9, 'name' => 'Netflix Standard with Ads', 'logo' => null],
            'HBO Max',
            ['id' => 384, 'name' => 'HBO Max Amazon Channel', 'logo' => '/hbo.jpg'],
        ];

        $brands = Providers::brands($providers);

        $this->assertSame(
            ['HBO Max', 'Netflix'],
            array_column($brands, 'brand')
        );
        $netflix = $brands[array_search('Netflix', array_column($brands, 'brand'), true)];
        $this->assertSame('/netflix.jpg', $netflix['logo']);
        $hbo = $brands[array_search('HBO Max', array_column($brands, 'brand'), true)];
        $this->assertSame('/hbo.jpg', $hbo['logo']);
    }

    public function testNeedsWarningIsFalseWhenNoSubscriptionIsConfigured(): void
    {
        $providers = Providers::normalise(['Disney Plus']);

        $this->assertFalse(Providers::needsWarning($providers, []));
    }

    public function testNeedsWarningIsFalseWhenAProviderMatchesTheSubscribedBrand(): void
    {
        $providers = Providers::normalise(['Netflix Standard with Ads']);

        $this->assertFalse(Providers::needsWarning($providers, ['Netflix']));
    }

    public function testNeedsWarningIsTrueWhenNoProviderMatchesTheSubscribedBrands(): void
    {
        $providers = Providers::normalise(['Disney Plus']);

        $this->assertTrue(Providers::needsWarning($providers, ['Netflix', 'Canal+']));
    }

    public function testNeedsWarningIsTrueWhenTheMovieHasNoProviderAtAll(): void
    {
        $this->assertTrue(Providers::needsWarning([], ['Netflix']));
    }
}
