<?php
declare(strict_types=1);

namespace App\Services;

/**
 * L'ordre de visionnage d'une saga.
 *
 * Regle de la famille : ne pas se voir proposer Violent Night 2 alors que
 * Violent Night attend encore dans une liste. Le blocage ne joue que si le
 * precedent est reellement present et pas encore vu ; s'il n'a jamais ete
 * ajoute, on suppose qu'il a ete vu avant Filmi.
 *
 * Service pur : il recoit des tableaux et n'ouvre aucune connexion, ce qui rend
 * la regle testable sans base.
 */
final class SequelService
{
    /**
     * Le film qui bloque celui passe en argument, ou null s'il est libre.
     *
     * Quand plusieurs precedents manquent, c'est le plus ancien qui est nomme :
     * c'est par celui-la qu'il faut commencer.
     *
     * @param array<string, mixed> $movie
     * @param list<array<string, mixed>> $catalogue films des deux listes, tous statuts
     * @return array<string, mixed>|null
     */
    public static function blockedBy(array $movie, array $catalogue): ?array
    {
        // Une echappatoire par film, indispensable pour les sagas dont l'ordre de
        // sortie n'est pas l'ordre de visionnage, Star Wars en tete.
        if ((int) ($movie['ignore_order'] ?? 0) === 1) {
            return null;
        }

        // Un film deja vu n'est jamais retenu : le blocage existe pour ne pas gacher
        // l'ordre de decouverte, or il n'y a plus rien a gacher. C'est ce qui rend
        // un "revoir" possible au milieu d'une saga.
        if ((int) ($movie['already_seen'] ?? 0) === 1) {
            return null;
        }

        $collection = $movie['collection_id'] ?? null;
        $rank = $movie['collection_rank'] ?? null;
        if ($collection === null || $rank === null) {
            return null;
        }

        $bloquants = array_filter($catalogue, static function (array $autre) use ($collection, $rank): bool {
            return (int) ($autre['collection_id'] ?? 0) === (int) $collection
                && ($autre['collection_rank'] ?? null) !== null
                && (int) $autre['collection_rank'] < (int) $rank
                // Seul un film encore en liste bloque : un film vu a rempli son
                // office, un film archive est sorti du jeu.
                && ($autre['status'] ?? '') === 'pool';
        });

        if ($bloquants === []) {
            return null;
        }

        usort(
            $bloquants,
            static fn (array $a, array $b): int => (int) $a['collection_rank'] <=> (int) $b['collection_rank']
        );

        return $bloquants[0];
    }

    /**
     * Les identifiants des films bloques dans un catalogue donne.
     *
     * @param list<array<string, mixed>> $catalogue
     * @return list<int>
     */
    public static function blockedIds(array $catalogue): array
    {
        $ids = [];
        foreach ($catalogue as $movie) {
            if (self::blockedBy($movie, $catalogue) !== null) {
                $ids[] = (int) $movie['id'];
            }
        }

        return $ids;
    }
}
