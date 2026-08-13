<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Calcule la soirée d'une série à partir de sa suite continue d'épisodes.
 * Pur : aucune base, aucun réseau, aucune superglobale. La suite est numérotée
 * en continu de 1 à N toutes saisons confondues, ce qui permet à une soirée de
 * chevaucher deux saisons sans traitement particulier.
 */
final class SeriesService
{
    /**
     * @param list<array{number:int, season:int, episode_in_season:int, title:?string, runtime:?int}> $episodes
     *        suite continue triée par 'number' croissant, à partir de 1
     * @return array{from:int, to:int, label:string, total_runtime:int, finishes:bool}
     */
    public static function nextEvening(array $episodes, int $episodesWatched, int $episodesPerEvening): array
    {
        $total = count($episodes);
        $perEvening = max(1, $episodesPerEvening);

        $from = max(1, $episodesWatched + 1);
        $to = max($from - 1, min($total, $from + $perEvening - 1));

        return [
            'from' => $from,
            'to' => $to,
            'label' => self::label(self::find($episodes, $from), self::find($episodes, $to)),
            'total_runtime' => self::totalRuntime($episodes, $from, $to),
            'finishes' => $total > 0 && $to >= $total,
        ];
    }

    /** @param list<array{number:int, runtime:?int}> $episodes */
    private static function totalRuntime(array $episodes, int $from, int $to): int
    {
        $total = 0;
        foreach ($episodes as $episode) {
            if ($episode['number'] >= $from && $episode['number'] <= $to) {
                $total += (int) ($episode['runtime'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * @param array{number:int, season:int, episode_in_season:int}|null $from
     * @param array{number:int, season:int, episode_in_season:int}|null $to
     */
    private static function label(?array $from, ?array $to): string
    {
        if ($from === null || $to === null) {
            return '';
        }
        if ($from['number'] === $to['number']) {
            return self::tag($from);
        }

        return self::tag($from) . ' à ' . self::tag($to);
    }

    /** @param array{season:int, episode_in_season:int} $episode */
    private static function tag(array $episode): string
    {
        return 'S' . $episode['season'] . 'E' . $episode['episode_in_season'];
    }

    /**
     * @param list<array{number:int}> $episodes
     * @return array{number:int, season:int, episode_in_season:int}|null
     */
    private static function find(array $episodes, int $number): ?array
    {
        foreach ($episodes as $episode) {
            if ($episode['number'] === $number) {
                return $episode;
            }
        }

        return null;
    }
}
