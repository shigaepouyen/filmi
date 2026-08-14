<?php
declare(strict_types=1);

namespace App\Services;

final class AwardsService
{
    /**
     * @param list<array<string, mixed>> $history séances triées par date décroissante
     * @param list<array{profile_id?:int,name:string,total:int|string}> $vetoCounts
     * @param list<array{date:string,profile_id:int,name:string,avatar:string,color:string,score:int}> $ratings
     *        une ligne par note individuelle donnée, triées par date décroissante
     * @param list<array{id:int,name:string,avatar:string,color:string,side?:string}> $profiles
     * @return array<string, mixed>
     */
    public static function compute(
        array $history,
        array $vetoCounts,
        ?int $year = null,
        array $ratings = [],
        array $profiles = []
    ): array {
        $rows = self::filterByYear($history, $year);
        $ratingRows = self::filterByYear($ratings, $year);

        $doneSeances = array_values(array_filter($rows, static fn ($r) => ($r['status'] ?? '') === 'done'));
        $skipped = array_values(array_filter($rows, static fn ($r) => ($r['status'] ?? '') === 'skipped'));

        // Une série regardée sur plusieurs samedis est une seule œuvre, pas une
        // par soirée : douze samedis de série ne doivent pas afficher douze
        // œuvres vues. $rows est trié par date décroissante, donc la première
        // occurrence de chaque movie_id porte la note finale (la seule notée,
        // donnée au dernier épisode). L'historique lui-même n'est pas touché,
        // il garde une ligne par samedi.
        $done = [];
        $seenMovies = [];
        foreach ($doneSeances as $row) {
            $movieId = $row['movie_id'] ?? null;
            $key = $movieId !== null ? 'id:' . $movieId : 'row:' . count($done);
            if (isset($seenMovies[$key])) {
                continue;
            }
            $seenMovies[$key] = true;
            $done[] = $row;
        }

        $rated = array_values(array_filter($done, static fn ($r) => $r['avg_score'] !== null));
        $scores = array_map(static fn ($r) => (float) $r['avg_score'], $rated);

        $byProposer = [];
        foreach ($done as $row) {
            $name = $row['proposer_name'] ?? null;
            if ($name === null || $name === '') {
                continue;
            }
            $byProposer[$name] = ($byProposer[$name] ?? 0) + 1;
        }
        arsort($byProposer);

        $bySide = ['adult' => 0, 'kid' => 0];
        foreach ($done as $row) {
            $side = $row['chooser_side'] ?? '';
            if (isset($bySide[$side])) {
                $bySide[$side]++;
            }
        }

        // Le total de minutes regardées porte sur chaque soirée, pas sur les
        // œuvres distinctes : une série occupe plusieurs samedis, et chacun ne
        // doit compter que les épisodes réellement vus ce soir-là, jamais la
        // série entière à chaque fois.
        $totalMinutes = 0;
        foreach ($doneSeances as $row) {
            $totalMinutes += self::eveningMinutes($row);
        }

        $byPerson = self::byPerson($done, $ratingRows, $vetoCounts, $profiles);
        $raters = array_values(array_filter($byPerson, static fn ($p) => $p['ratings_count'] > 0));

        return [
            'year' => $year,
            'watched' => count($done),
            'skipped' => count($skipped),
            'derogations' => count(array_filter($done, static fn ($r) => (int) ($r['derogation'] ?? 0) === 1)),
            'rated' => count($rated),
            'avg_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
            'best' => self::extreme($rated, 'avg_score', true),
            'worst' => self::extreme($rated, 'avg_score', false),
            'by_proposer' => $byProposer,
            'by_side' => $bySide,
            'vetoes' => $vetoCounts,
            'poster_wall' => $done,
            'podium' => self::podium($rated),
            'total_minutes' => $totalMinutes,
            'longest' => self::extreme(self::withDuration($done), 'duration_minutes', true),
            'shortest' => self::extreme(self::withDuration($done), 'duration_minutes', false),
            'oldest' => self::extreme(self::withYear($done), 'movie_year', false),
            'newest' => self::extreme(self::withYear($done), 'movie_year', true),
            'top_decade' => self::topDecade($done),
            'by_person' => $byPerson,
            'most_generous' => self::extreme($raters, 'avg_given', true),
            'toughest' => self::extreme($raters, 'avg_given', false),
        ];
    }

    /**
     * Traduit la sélection demandée (année précise ou 'all') en année à filtrer
     * et en valeur affichée par le sélecteur. Sans aucune séance en historique,
     * le sélecteur ne propose que « Tout l'historique » : on aligne le titre
     * dessus plutôt que de laisser afficher l'année du jour sans rien derrière.
     *
     * @param list<int> $years années disponibles dans l'historique
     * @return array{year: int|null, selected: string}
     */
    public static function resolveSelection(array $years, string $requested): array
    {
        if ($years === [] || $requested === 'all') {
            return ['year' => null, 'selected' => 'all'];
        }

        return ['year' => (int) $requested, 'selected' => $requested];
    }

    /**
     * @param list<array<string, mixed>> $rows lignes portant une clé 'date'
     * @return list<array<string, mixed>>
     */
    private static function filterByYear(array $rows, ?int $year): array
    {
        if ($year === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => str_starts_with((string) $row['date'], (string) $year)
        ));
    }

    /**
     * Minutes effectivement regardées lors d'une soirée précise. Un film compte
     * sa durée entière (il n'est vu qu'une fois). Une soirée de série ne compte
     * que les épisodes de sa plage figée (episodes_from à episodes_to), jamais
     * la série entière : c'est ce qui évite de compter douze fois la durée totale
     * d'une série regardée sur douze samedis.
     */
    private static function eveningMinutes(array $row): int
    {
        if (($row['movie_kind'] ?? 'film') === 'series') {
            if ($row['episodes_from'] === null || $row['episodes_to'] === null) {
                return 0;
            }

            return self::episodesRuntime(
                $row['movie_episodes'] ?? null,
                (int) $row['episodes_from'],
                (int) $row['episodes_to']
            );
        }

        return (int) ($row['movie_runtime'] ?? 0);
    }

    /**
     * Durée totale d'une œuvre (film entier, ou somme de tous les épisodes pour
     * une série), pour les records « plus long »/« plus court ». À la différence
     * de eveningMinutes(), qui ne porte que sur une soirée donnée.
     */
    private static function fullDurationMinutes(array $row): int
    {
        if (($row['movie_kind'] ?? 'film') === 'series') {
            return self::episodesRuntime($row['movie_episodes'] ?? null, null, null);
        }

        return (int) ($row['movie_runtime'] ?? 0);
    }

    private static function episodesRuntime(mixed $episodesJson, ?int $from, ?int $to): int
    {
        $episodes = json_decode((string) $episodesJson, true);
        $episodes = is_array($episodes) ? $episodes : [];

        $total = 0;
        foreach ($episodes as $episode) {
            $number = (int) ($episode['number'] ?? 0);
            if ($from !== null && $number < $from) {
                continue;
            }
            if ($to !== null && $number > $to) {
                continue;
            }
            $total += (int) ($episode['runtime'] ?? 0);
        }

        return $total;
    }

    /** @param list<array<string, mixed>> $done */
    private static function withDuration(array $done): array
    {
        $withDuration = [];
        foreach ($done as $row) {
            $minutes = self::fullDurationMinutes($row);
            if ($minutes > 0) {
                $row['duration_minutes'] = $minutes;
                $withDuration[] = $row;
            }
        }

        return $withDuration;
    }

    /** @param list<array<string, mixed>> $done */
    private static function withYear(array $done): array
    {
        return array_values(array_filter($done, static fn ($r) => $r['movie_year'] !== null));
    }

    /** @param list<array<string, mixed>> $done */
    private static function topDecade(array $done): ?array
    {
        $counts = [];
        foreach (self::withYear($done) as $row) {
            $decade = intdiv((int) $row['movie_year'], 10) * 10;
            $counts[$decade] = ($counts[$decade] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);
        $decade = array_key_first($counts);

        return ['decade' => (int) $decade, 'count' => $counts[$decade]];
    }

    /**
     * Les trois œuvres les mieux notées, à égalité départagées par la
     * récence (l'entrée d'origine est déjà triée par date décroissante, et le
     * tri par score est stable depuis PHP 8).
     *
     * @param list<array<string, mixed>> $rated
     */
    private static function podium(array $rated): array
    {
        $podium = $rated;
        usort($podium, static fn ($a, $b) => (float) $b['avg_score'] <=> (float) $a['avg_score']);

        return array_slice($podium, 0, 3);
    }

    /**
     * @param list<array<string, mixed>> $done
     * @param list<array{date:string,profile_id:int,score:int}> $ratings
     * @param list<array{profile_id?:int,name:string,total:int|string}> $vetoCounts
     * @param list<array{id:int,name:string,avatar:string,color:string}> $profiles
     * @return list<array{id:int,name:string,avatar:?string,color:?string,proposed:int,ratings_count:int,avg_given:?float,veto_count:int}>
     */
    private static function byPerson(array $done, array $ratings, array $vetoCounts, array $profiles): array
    {
        $vetoByProfile = [];
        foreach ($vetoCounts as $veto) {
            if (isset($veto['profile_id'])) {
                $vetoByProfile[(int) $veto['profile_id']] = (int) $veto['total'];
            }
        }

        $result = [];
        foreach ($profiles as $profile) {
            $id = (int) $profile['id'];

            $proposed = count(array_filter(
                $done,
                static fn ($r) => (int) ($r['proposer_id'] ?? 0) === $id
            ));

            $given = array_values(array_filter(
                $ratings,
                static fn ($r) => (int) ($r['profile_id'] ?? 0) === $id
            ));
            $scores = array_map(static fn ($r) => (float) $r['score'], $given);

            $result[] = [
                'id' => $id,
                'name' => $profile['name'],
                'avatar' => $profile['avatar'] ?? null,
                'color' => $profile['color'] ?? null,
                'proposed' => $proposed,
                'ratings_count' => count($scores),
                'avg_given' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
                'veto_count' => $vetoByProfile[$id] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * L'entrée est déjà triée par date décroissante (ou construite dans cet
     * ordre), donc à égalité de valeur le premier rencontré est le plus récent.
     *
     * @param list<array<string, mixed>> $rows
     */
    private static function extreme(array $rows, string $field, bool $highest): ?array
    {
        $winner = null;
        foreach ($rows as $row) {
            if ($winner === null) {
                $winner = $row;
                continue;
            }
            $current = (float) $row[$field];
            $best = (float) $winner[$field];
            if ($highest ? $current > $best : $current < $best) {
                $winner = $row;
            }
        }

        return $winner;
    }
}
