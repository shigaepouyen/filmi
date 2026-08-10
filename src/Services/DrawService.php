<?php
declare(strict_types=1);

namespace App\Services;

final class DrawService
{
    public const SAFE_COUNT = 1;
    public const DISCOVERY_COUNT = 2;

    private const QUOTA = ['safe' => self::SAFE_COUNT, 'discovery' => self::DISCOVERY_COUNT];

    /**
     * @param list<array{id:int,bet_type:?string}> $candidates
     * @param list<int> $cooldownIds  exclusion ferme
     * @param list<int> $shownIds     exclusion souple, levée si le pool ne suffit plus
     * @return array{movies: list<array>, reset: bool}
     * @throws DrawException
     */
    public static function pick(array $candidates, array $cooldownIds = [], array $shownIds = []): array
    {
        $eligible = self::groupByBetType($candidates, $cooldownIds);

        $movies = [];
        $reset = false;

        foreach (self::QUOTA as $category => $needed) {
            $bucket = $eligible[$category];
            $unseen = array_values(array_filter(
                $bucket,
                static fn (array $movie): bool => !in_array($movie['id'], $shownIds, true)
            ));

            if (count($unseen) >= $needed) {
                $source = $unseen;
            } else {
                // Plus assez de films jamais montrés ce soir : on repart du pool complet.
                $source = $bucket;
                $reset = true;
            }

            if (count($source) < $needed) {
                throw self::failure($eligible);
            }

            if (count($source) > $needed) {
                shuffle($source);
            }
            array_push($movies, ...array_slice($source, 0, $needed));
        }

        return ['movies' => $movies, 'reset' => $reset];
    }

    /**
     * @param list<array{id:int,bet_type:?string}> $candidates
     * @param list<int> $cooldownIds
     * @return array{safe: list<array>, discovery: list<array>}
     */
    private static function groupByBetType(array $candidates, array $cooldownIds): array
    {
        $grouped = ['safe' => [], 'discovery' => []];

        foreach ($candidates as $movie) {
            $category = $movie['bet_type'] ?? '';
            if (!isset($grouped[$category])) {
                continue; // tag absent ou inconnu : le film n'est pas tirable
            }
            if (in_array($movie['id'], $cooldownIds, true)) {
                continue;
            }
            $grouped[$category][] = $movie;
        }

        return $grouped;
    }

    /**
     * @param array{safe: list<array>, discovery: list<array>} $eligible
     */
    private static function failure(array $eligible): DrawException
    {
        $missing = [];
        $available = [];
        foreach (self::QUOTA as $category => $needed) {
            $available[$category] = count($eligible[$category]);
            $short = $needed - min($needed, count($eligible[$category]));
            if ($short > 0) {
                $missing[$category] = $short;
            }
        }

        return new DrawException($missing, $available);
    }
}
