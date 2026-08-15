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

    /** @return list<array{tmdb_id:int,title:string,original_title:?string,year:?int,poster_url:?string}> */
    public function searchSeries(string $query): array
    {
        $query = trim($query);
        if (!$this->isConfigured() || mb_strlen($query) < 2) {
            return [];
        }

        $payload = $this->get('/search/tv', [
            'query' => $query,
            'include_adult' => 'false',
        ]);

        $results = [];
        foreach (array_slice($payload['results'] ?? [], 0, self::MAX_RESULTS) as $row) {
            $results[] = [
                'tmdb_id' => (int) $row['id'],
                'title' => (string) ($row['name'] ?? ''),
                'original_title' => $row['original_name'] ?? null,
                'year' => self::yearFrom($row['first_air_date'] ?? null),
                'poster_url' => self::poster($row['poster_path'] ?? null),
            ];
        }

        return $results;
    }

    /**
     * Construit la suite continue d'épisodes d'une série : un appel série pour
     * les métadonnées et les plateformes, puis un appel par saison car
     * episode_run_time est vide au niveau série (vérifié sur Heartstopper) et
     * seul l'endpoint saison donne la durée de chaque épisode. La saison 0
     * (specials) est hors suite continue et n'est jamais appelée.
     *
     * @return array<string, mixed> prêt pour MovieRepository::addSeries()
     */
    public function seriesDetails(int $tmdbId): array
    {
        if (!$this->isConfigured()) {
            throw new TmdbException('Aucune clé TMDb configurée.');
        }

        $series = $this->get('/tv/' . $tmdbId, [
            'append_to_response' => 'watch/providers',
        ]);

        $episodes = [];
        $number = 0;
        $seasonNumbers = [];
        foreach ($series['seasons'] ?? [] as $season) {
            $seasonNumber = (int) ($season['season_number'] ?? 0);
            if ($seasonNumber < 1) {
                continue;
            }
            $seasonNumbers[] = $seasonNumber;

            $seasonPayload = $this->get('/tv/' . $tmdbId . '/season/' . $seasonNumber, []);
            foreach ($seasonPayload['episodes'] ?? [] as $episode) {
                $number++;
                $episodes[] = [
                    'number' => $number,
                    'season' => $seasonNumber,
                    'episode_in_season' => (int) ($episode['episode_number'] ?? 0),
                    'title' => $episode['name'] ?? null,
                    'runtime' => isset($episode['runtime']) ? (int) $episode['runtime'] : null,
                ];
            }
        }

        return [
            'kind' => 'series',
            'tmdb_id' => (int) ($series['id'] ?? $tmdbId),
            'title' => (string) ($series['name'] ?? ''),
            'original_title' => $series['original_name'] ?? null,
            'year' => self::yearFrom($series['first_air_date'] ?? null),
            'poster_url' => self::poster($series['poster_path'] ?? null),
            'overview' => $series['overview'] ?? null,
            'tmdb_rating' => isset($series['vote_average']) ? (float) $series['vote_average'] : null,
            'providers' => json_encode(
                $this->providers($series['watch/providers']['results'] ?? []),
                JSON_UNESCAPED_UNICODE
            ),
            'providers_at' => date('Y-m-d H:i:s'),
            'season_count' => count($seasonNumbers),
            'episode_count' => count($episodes),
            'episodes' => json_encode($episodes, JSON_UNESCAPED_UNICODE),
            'episodes_watched' => 0,
            'episodes_per_evening' => 2,
        ];
    }

    /** @return array<string, mixed> prêt pour MovieRepository::add() */
    public function details(int $tmdbId): array
    {
        if (!$this->isConfigured()) {
            throw new TmdbException('Aucune clé TMDb configurée.');
        }

        $movie = $this->get('/movie/' . $tmdbId, [
            'append_to_response' => 'credits,watch/providers,release_dates,videos',
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
            'trailer_url' => self::trailerUrl($movie['videos']['results'] ?? []),
        ] + $this->collection($movie['belongs_to_collection'] ?? null, (int) ($movie['id'] ?? $tmdbId));
    }

    /**
     * La saga du film et son rang dedans, pour ne jamais proposer une suite avant
     * son precedent. Le rang vient des dates de sortie, donc un appel de plus a
     * /collection/{id}, fait une seule fois a l'ajout puis fige en base.
     *
     * @param array<string, mixed>|null $belongsTo
     * @return array{collection_id: ?int, collection_name: ?string, collection_rank: ?int}
     */
    private function collection(?array $belongsTo, int $tmdbId): array
    {
        $vide = ['collection_id' => null, 'collection_name' => null, 'collection_rank' => null];

        if ($belongsTo === null || !isset($belongsTo['id'])) {
            return $vide;
        }

        try {
            $collection = $this->get('/collection/' . (int) $belongsTo['id'], []);
        } catch (TmdbException) {
            // Une saga introuvable ne doit pas empecher d'ajouter le film : sans
            // rang il ne sera ni bloque ni bloquant, ce qui est le comportement
            // d'un film sans saga.
            return $vide;
        }

        $parts = $collection['parts'] ?? [];
        usort($parts, static function (array $a, array $b): int {
            // Une part sans date passe en fin de saga plutot qu'en tete.
            return strcmp((string) ($a['release_date'] ?: '9999'), (string) ($b['release_date'] ?: '9999'));
        });

        $rang = null;
        foreach ($parts as $index => $part) {
            if ((int) ($part['id'] ?? 0) === $tmdbId) {
                $rang = $index + 1;
                break;
            }
        }

        return [
            'collection_id' => (int) $belongsTo['id'],
            'collection_name' => $belongsTo['name'] ?? null,
            'collection_rank' => $rang,
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
     * @return list<array{id: ?int, name: string, logo: ?string}> plateformes de la région, sans doublon
     */
    private function providers(array $byCountry): array
    {
        $region = $byCountry[$this->region] ?? [];
        $result = [];
        $seen = [];

        foreach (['flatrate', 'free', 'ads', 'rent', 'buy'] as $kind) {
            foreach ($region[$kind] ?? [] as $provider) {
                $name = trim((string) ($provider['provider_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $id = isset($provider['provider_id']) ? (int) $provider['provider_id'] : null;
                $dedupeKey = $id !== null ? 'id:' . $id : 'name:' . mb_strtolower($name, 'UTF-8');
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $result[] = [
                    'id' => $id,
                    'name' => $name,
                    'logo' => $provider['logo_path'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Sélection de la bande-annonce, par ordre de préférence : Trailer YouTube
     * officiel en français, puis officiel toutes langues, puis n'importe quel
     * Trailer YouTube. Renvoie l'URL complète, ou null si aucun ne convient.
     *
     * @param array<int, array<string, mixed>> $videos
     */
    private static function trailerUrl(array $videos): ?string
    {
        $trailers = array_values(array_filter(
            $videos,
            static fn (array $v): bool => ($v['site'] ?? null) === 'YouTube' && ($v['type'] ?? null) === 'Trailer'
        ));

        if ($trailers === []) {
            return null;
        }

        $pick = self::firstMatching(
            $trailers,
            static fn (array $v): bool => ($v['official'] ?? false) === true && ($v['iso_639_1'] ?? null) === 'fr'
        ) ?? self::firstMatching(
            $trailers,
            static fn (array $v): bool => ($v['official'] ?? false) === true
        ) ?? $trailers[0];

        $key = $pick['key'] ?? null;

        return $key ? 'https://www.youtube.com/watch?v=' . $key : null;
    }

    /** @param array<int, array<string, mixed>> $items */
    private static function firstMatching(array $items, callable $predicate): ?array
    {
        foreach ($items as $item) {
            if ($predicate($item)) {
                return $item;
            }
        }

        return null;
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
