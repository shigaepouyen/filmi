<?php
declare(strict_types=1);

namespace App\Services;

final class AwardsService
{
    /**
     * @param list<array<string, mixed>> $history séances triées par date décroissante
     * @param list<array{name:string,total:int|string}> $vetoCounts
     * @return array<string, mixed>
     */
    public static function compute(array $history, array $vetoCounts, ?int $year = null): array
    {
        $rows = $year === null
            ? $history
            : array_values(array_filter(
                $history,
                static fn (array $row): bool => str_starts_with((string) $row['date'], (string) $year)
            ));

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

        return [
            'year' => $year,
            'watched' => count($done),
            'skipped' => count($skipped),
            'derogations' => count(array_filter($done, static fn ($r) => (int) ($r['derogation'] ?? 0) === 1)),
            'rated' => count($rated),
            'avg_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
            'best' => self::extreme($rated, true),
            'worst' => self::extreme($rated, false),
            'by_proposer' => $byProposer,
            'by_side' => $bySide,
            'vetoes' => $vetoCounts,
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
     * L'entrée est déjà triée par date décroissante, donc à égalité de note
     * le premier rencontré est le plus récent.
     *
     * @param list<array<string, mixed>> $rated
     */
    private static function extreme(array $rated, bool $highest): ?array
    {
        $winner = null;
        foreach ($rated as $row) {
            if ($winner === null) {
                $winner = $row;
                continue;
            }
            $current = (float) $row['avg_score'];
            $best = (float) $winner['avg_score'];
            if ($highest ? $current > $best : $current < $best) {
                $winner = $row;
            }
        }

        return $winner;
    }
}
