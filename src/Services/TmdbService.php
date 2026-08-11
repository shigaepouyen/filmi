<?php
declare(strict_types=1);

namespace App\Services;

final class TmdbService
{
    public const POSTER_BASE = 'https://image.tmdb.org/t/p/w342';
    private const API_BASE = 'https://api.themoviedb.org/3';
    private const MAX_RESULTS = 8;

    /** @var callable(string): string */
    private $transport;

    public function __construct(
        private string $apiKey,
        private string $language = 'fr-FR',
        private string $region = 'FR',
        ?callable $transport = null
    ) {
        $this->transport = $transport ?? [$this, 'curlGet'];
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /** @return list<array{tmdb_id:int,title:string,original_title:?string,year:?int,poster_url:?string}> */
    public function search(string $query): array
    {
        $query = trim($query);
        if (!$this->isConfigured() || mb_strlen($query) < 2) {
            return [];
        }

        $payload = $this->get('/search/movie', [
            'query' => $query,
            'include_adult' => 'false',
        ]);

        $results = [];
        foreach (array_slice($payload['results'] ?? [], 0, self::MAX_RESULTS) as $row) {
            $results[] = [
                'tmdb_id' => (int) $row['id'],
                'title' => (string) ($row['title'] ?? ''),
                'original_title' => $row['original_title'] ?? null,
                'year' => self::yearFrom($row['release_date'] ?? null),
                'poster_url' => self::poster($row['poster_path'] ?? null),
            ];
        }

        return $results;
    }

    /** @return array<string, mixed> prêt pour MovieRepository::add() */
    public function details(int $tmdbId): array
    {
        if (!$this->isConfigured()) {
            throw new TmdbException('Aucune clé TMDb configurée.');
        }

        $movie = $this->get('/movie/' . $tmdbId, [
            'append_to_response' => 'credits,watch/providers,release_dates',
        ]);

        return [
            'tmdb_id' => (int) ($movie['id'] ?? $tmdbId),
            'title' => (string) ($movie['title'] ?? ''),
            'original_title' => $movie['original_title'] ?? null,
            'year' => self::yearFrom($movie['release_date'] ?? null),
            'runtime' => isset($movie['runtime']) ? (int) $movie['runtime'] : null,
            'poster_url' => self::poster($movie['poster_path'] ?? null),
            'overview' => $movie['overview'] ?? null,
            'tmdb_rating' => isset($movie['vote_average']) ? (float) $movie['vote_average'] : null,
            'genres' => json_encode(
                array_column($movie['genres'] ?? [], 'name'),
                JSON_UNESCAPED_UNICODE
            ),
            'director' => self::director($movie['credits']['crew'] ?? []),
            'certification' => $this->certification($movie['release_dates']['results'] ?? []),
            'providers' => json_encode(
                $this->providers($movie['watch/providers']['results'] ?? []),
                JSON_UNESCAPED_UNICODE
            ),
            'providers_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** @param array<string, mixed> $params */
    private function get(string $path, array $params): array
    {
        $url = self::API_BASE . $path . '?' . http_build_query(array_merge([
            'api_key' => $this->apiKey,
            'language' => $this->language,
        ], $params));

        try {
            $raw = ($this->transport)($url);
        } catch (\Throwable $e) {
            // On ne chaine jamais l'exception d'origine telle quelle : son message
            // contient l'URL complete, donc la cle. (string) $e la ferait fuiter
            // dans les logs et dans le gestionnaire d'exceptions par defaut.
            throw new TmdbException(
                'Appel TMDb en échec : ' . $this->redact($e->getMessage()),
                0,
                new \RuntimeException($this->redact($e->getMessage()), (int) $e->getCode())
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new TmdbException('Réponse TMDb illisible.');
        }
        if (isset($decoded['success']) && $decoded['success'] === false) {
            throw new TmdbException('TMDb a refusé la requête : ' . ($decoded['status_message'] ?? 'raison inconnue'));
        }

        return $decoded;
    }

    private function curlGet(string $url): string
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        // Pas de curl_close() : deprecie depuis PHP 8.0 et sans effet, le handle
        // est libere a la sortie de portee. L'appeler emet un avertissement en 8.5.
        unset($handle);

        if ($body === false) {
            throw new TmdbException('Réseau indisponible : ' . $this->redact($error));
        }
        if ($status >= 400) {
            throw new TmdbException('TMDb a répondu ' . $status . '.');
        }

        return (string) $body;
    }

    private function redact(string $message): string
    {
        return $this->apiKey === ''
            ? $message
            : str_replace($this->apiKey, '[clef masquée]', $message);
    }

    /** @param array<int, array<string, mixed>> $countries */
    private function certification(array $countries): ?string
    {
        foreach ($countries as $country) {
            if (($country['iso_3166_1'] ?? '') !== $this->region) {
                continue;
            }
            foreach ($country['release_dates'] ?? [] as $release) {
                $value = trim((string) ($release['certification'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $byCountry
     * @return list<string> noms de plateformes de la région, sans doublon
     */
    private function providers(array $byCountry): array
    {
        $region = $byCountry[$this->region] ?? [];
        $names = [];

        foreach (['flatrate', 'free', 'ads', 'rent', 'buy'] as $kind) {
            foreach ($region[$kind] ?? [] as $provider) {
                $name = (string) ($provider['provider_name'] ?? '');
                if ($name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /** @param array<int, array<string, mixed>> $crew */
    private static function director(array $crew): ?string
    {
        foreach ($crew as $member) {
            if (($member['job'] ?? '') === 'Director') {
                return (string) $member['name'];
            }
        }

        return null;
    }

    private static function yearFrom(?string $releaseDate): ?int
    {
        return preg_match('/^(\d{4})/', (string) $releaseDate, $m) ? (int) $m[1] : null;
    }

    private static function poster(?string $path): ?string
    {
        return $path === null || $path === '' ? null : self::POSTER_BASE . $path;
    }
}
