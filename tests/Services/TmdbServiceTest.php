<?php
namespace App\Tests\Services;

use App\Services\TmdbException;
use App\Services\TmdbService;
use PHPUnit\Framework\TestCase;

class TmdbServiceTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/' . $name);
    }

    /** @param array<string,string> $routes fragment d'URL vers charge utile */
    private function service(array $routes, string $key = 'clef-test'): TmdbService
    {
        $transport = function (string $url) use ($routes): string {
            foreach ($routes as $fragment => $payload) {
                if (str_contains($url, $fragment)) {
                    return $payload;
                }
            }
            throw new TmdbException('URL inattendue : ' . $url);
        };

        return new TmdbService($key, 'fr-FR', 'FR', $transport);
    }

    public function testIsConfiguredReflectsThePresenceOfAKey(): void
    {
        $this->assertTrue($this->service([])->isConfigured());
        $this->assertFalse($this->service([], '')->isConfigured());
    }

    public function testSearchReturnsAtMostEightNormalisedResults(): void
    {
        $service = $this->service(['/search/movie' => $this->fixture('tmdb_search.json')]);

        $results = $service->search('chihiro');

        $this->assertCount(3, $results);
        $this->assertSame(129, $results[0]['tmdb_id']);
        $this->assertSame('Le Voyage de Chihiro', $results[0]['title']);
        $this->assertSame(2001, $results[0]['year']);
        $this->assertSame(TmdbService::POSTER_BASE . '/chihiro.jpg', $results[0]['poster_url']);
        $this->assertNull($results[1]['poster_url'], 'Un poster_path nul ne doit pas produire une URL');
        $this->assertNull($results[2]['year'], 'Une date vide ne doit pas produire une année');
    }

    public function testSearchIgnoresTooShortQueriesWithoutCallingTheApi(): void
    {
        // Aucune route déclarée : un appel réseau lèverait une TmdbException.
        $this->assertSame([], $this->service([])->search('a'));
        $this->assertSame([], $this->service([])->search(' '));
    }

    public function testSearchWithoutKeyReturnsEmptyWithoutCallingTheApi(): void
    {
        $this->assertSame([], $this->service([], '')->search('chihiro'));
    }

    public function testDetailsMapsEveryFieldNeededByTheRepository(): void
    {
        $service = $this->service(['/movie/129' => $this->fixture('tmdb_details.json')]);

        $movie = $service->details(129);

        $this->assertSame(129, $movie['tmdb_id']);
        $this->assertSame('Le Voyage de Chihiro', $movie['title']);
        $this->assertSame('千と千尋の神隠し', $movie['original_title']);
        $this->assertSame(2001, $movie['year']);
        $this->assertSame(125, $movie['runtime']);
        $this->assertSame(TmdbService::POSTER_BASE . '/chihiro.jpg', $movie['poster_url']);
        $this->assertStringContainsString('déménagent', $movie['overview']);
        $this->assertSame(8.5, $movie['tmdb_rating']);
        $this->assertSame('Hayao Miyazaki', $movie['director']);
        $this->assertSame(['Animation', 'Fantastique'], json_decode($movie['genres'], true));
    }

    public function testDetailsKeepsOnlyFrenchProvidersAsObjectsAndDeduplicates(): void
    {
        $service = $this->service(['/movie/129' => $this->fixture('tmdb_details.json')]);

        $providers = json_decode($service->details(129)['providers'], true);

        $this->assertSame([
            ['id' => 8, 'name' => 'Netflix', 'logo' => '/netflix.jpg'],
            ['id' => 384, 'name' => 'Max', 'logo' => '/max.jpg'],
            ['id' => 2, 'name' => 'Apple TV', 'logo' => '/appletv.jpg'],
        ], $providers);
        $this->assertNotEmpty($service->details(129)['providers_at']);
    }

    public function testDetailsPrefersTheOfficialFrenchYoutubeTrailer(): void
    {
        $service = $this->service(['/movie/129' => $this->fixture('tmdb_details.json')]);

        $this->assertSame(
            'https://www.youtube.com/watch?v=official-fr-key',
            $service->details(129)['trailer_url']
        );
    }

    public function testDetailsFallsBackOnAnyOfficialYoutubeTrailerWithoutFrench(): void
    {
        $service = $this->service(['/movie/200' => $this->fixture('tmdb_details_trailer_official_only.json')]);

        $this->assertSame(
            'https://www.youtube.com/watch?v=official-en-key',
            $service->details(200)['trailer_url']
        );
    }

    public function testDetailsFallsBackOnAnyYoutubeTrailerWhenNoneIsOfficial(): void
    {
        $service = $this->service(['/movie/201' => $this->fixture('tmdb_details_trailer_any_only.json')]);

        $this->assertSame(
            'https://www.youtube.com/watch?v=unofficial-key',
            $service->details(201)['trailer_url']
        );
    }

    public function testDetailsTrailerIsNullWhenNoYoutubeTrailerExists(): void
    {
        $service = $this->service(['/movie/202' => $this->fixture('tmdb_details_no_trailer.json')]);

        $this->assertNull($service->details(202)['trailer_url']);
    }

    public function testDetailsKeepsTheFrenchCertificationAndSkipsEmptyOnes(): void
    {
        $service = $this->service(['/movie/129' => $this->fixture('tmdb_details.json')]);

        $this->assertSame('Tous publics', $service->details(129)['certification']);
    }

    public function testDetailsWithoutKeyThrows(): void
    {
        $this->expectException(TmdbException::class);
        $this->service([], '')->details(129);
    }

    public function testMalformedJsonThrows(): void
    {
        $service = $this->service(['/movie/129' => 'pas du json']);

        $this->expectException(TmdbException::class);
        $service->details(129);
    }

    public function testTransportFailureBubblesUpAsTmdbException(): void
    {
        $service = new TmdbService('clef', 'fr-FR', 'FR', function (string $url): string {
            throw new \RuntimeException('réseau coupé');
        });

        $this->expectException(TmdbException::class);
        $service->search('chihiro');
    }

    public function testSearchSeriesReturnsNormalisedResults(): void
    {
        $service = $this->service(['/search/tv' => $this->fixture('tmdb_tv_search.json')]);

        $results = $service->searchSeries('heartstopper');

        $this->assertCount(2, $results);
        $this->assertSame(124834, $results[0]['tmdb_id']);
        $this->assertSame('Heartstopper', $results[0]['title']);
        $this->assertSame(2022, $results[0]['year']);
        $this->assertSame(TmdbService::POSTER_BASE . '/heartstopper.jpg', $results[0]['poster_url']);
        $this->assertNull($results[1]['year'], 'Une date vide ne doit pas produire une année');
        $this->assertNull($results[1]['poster_url']);
    }

    public function testSearchSeriesIgnoresTooShortQueriesWithoutCallingTheApi(): void
    {
        $this->assertSame([], $this->service([])->searchSeries('a'));
    }

    public function testSearchSeriesWithoutKeyReturnsEmptyWithoutCallingTheApi(): void
    {
        $this->assertSame([], $this->service([], '')->searchSeries('heartstopper'));
    }

    public function testSeriesDetailsBuildsTheContinuousEpisodeSequenceAcrossSeasons(): void
    {
        // Les fragments de saisons sont déclarés avant le fragment générique
        // '/tv/124834' : sinon str_contains() matcherait le générique en premier.
        $service = $this->service([
            '/tv/124834/season/1' => $this->fixture('tmdb_tv_season1.json'),
            '/tv/124834/season/2' => $this->fixture('tmdb_tv_season2.json'),
            '/tv/124834/season/3' => $this->fixture('tmdb_tv_season3.json'),
            '/tv/124834' => $this->fixture('tmdb_tv_details.json'),
        ]);

        $series = $service->seriesDetails(124834);

        $this->assertSame('series', $series['kind']);
        $this->assertSame(124834, $series['tmdb_id']);
        $this->assertSame('Heartstopper', $series['title']);
        $this->assertSame(2022, $series['year']);
        $this->assertSame(TmdbService::POSTER_BASE . '/heartstopper.jpg', $series['poster_url']);
        $this->assertSame(8.4, $series['tmdb_rating']);
        $this->assertSame(3, $series['season_count'], 'La saison 0 (specials) est hors suite continue');
        $this->assertSame(24, $series['episode_count']);
        $this->assertSame(0, $series['episodes_watched']);
        $this->assertSame(2, $series['episodes_per_evening']);

        $providers = json_decode($series['providers'], true);
        $this->assertSame([['id' => 8, 'name' => 'Netflix', 'logo' => '/netflix.jpg']], $providers);

        $episodes = json_decode($series['episodes'], true);
        $this->assertCount(24, $episodes);
        $this->assertSame(
            ['number' => 1, 'season' => 1, 'episode_in_season' => 1, 'title' => 'Crush', 'runtime' => 27],
            $episodes[0]
        );
        // Frontière de saison : le 9e épisode de la suite est bien S2E1.
        $this->assertSame(9, $episodes[8]['number']);
        $this->assertSame(2, $episodes[8]['season']);
        $this->assertSame(1, $episodes[8]['episode_in_season']);
        $this->assertSame(26, $episodes[8]['runtime']);
        $this->assertSame(24, $episodes[23]['number']);
        $this->assertSame(3, $episodes[23]['season']);
        $this->assertSame(8, $episodes[23]['episode_in_season']);
    }

    public function testSeriesDetailsNeverCallsTheSpecialsSeason(): void
    {
        // Aucune route pour /tv/124834/season/0 : si le code l'appelait, la
        // TmdbException "URL inattendue" serait levée et ce test échouerait.
        $service = $this->service([
            '/tv/124834/season/1' => $this->fixture('tmdb_tv_season1.json'),
            '/tv/124834/season/2' => $this->fixture('tmdb_tv_season2.json'),
            '/tv/124834/season/3' => $this->fixture('tmdb_tv_season3.json'),
            '/tv/124834' => $this->fixture('tmdb_tv_details.json'),
        ]);

        $series = $service->seriesDetails(124834);

        $this->assertSame(24, $series['episode_count']);
    }

    public function testSeriesDetailsWithoutKeyThrows(): void
    {
        $this->expectException(TmdbException::class);
        $this->service([], '')->seriesDetails(124834);
    }

    public function testApiKeyNeverAppearsAnywhereInTheExceptionChain(): void
    {
        $key = 'SECRET123';
        $service = new TmdbService($key, 'fr-FR', 'FR', function (string $url) use ($key): string {
            throw new \RuntimeException('erreur sur ' . $url);
        });

        try {
            $service->search('chihiro');
            $this->fail('TmdbException attendue');
        } catch (TmdbException $e) {
            $this->assertStringNotContainsString($key, $e->getMessage());
            $this->assertStringNotContainsString($key, (string) $e);
            $this->assertStringNotContainsString($key, $e->getTraceAsString());

            for ($previous = $e->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
                $this->assertStringNotContainsString(
                    $key,
                    $previous->getMessage(),
                    'La cle fuit dans une exception chainee'
                );
            }
        }
    }
}
