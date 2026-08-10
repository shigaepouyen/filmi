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

    public function testDetailsKeepsOnlyFrenchProvidersAndDeduplicates(): void
    {
        $service = $this->service(['/movie/129' => $this->fixture('tmdb_details.json')]);

        $providers = json_decode($service->details(129)['providers'], true);

        $this->assertSame(['Netflix', 'Max', 'Apple TV'], $providers);
        $this->assertNotEmpty($service->details(129)['providers_at']);
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
