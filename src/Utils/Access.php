<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Qui a le droit d'agir sur quelle liste.
 *
 * La regle de la famille est volontairement asymetrique : les parents gerent les
 * deux listes, y compris celle des filles, alors que les filles ne gerent que la
 * leur. En revanche tout le monde voit les deux listes et peut voter partout,
 * c'est ce qui permet aux filles de decouvrir ce que les parents ont en reserve.
 *
 * Gerer veut dire ajouter un film, le reclasser ou l'archiver. Voter et consulter
 * ne passent pas par ici, ce sont des actions ouvertes a tous.
 */
final class Access
{
    public const POOLS = ['adult', 'kid'];

    public static function canManagePool(string $side, string $pool): bool
    {
        if (!in_array($pool, self::POOLS, true)) {
            return false;
        }

        return $side === 'adult' || $pool === 'kid';
    }

    /**
     * Les listes sur lesquelles ce profil peut agir, dans l'ordre d'affichage.
     *
     * @return list<string>
     */
    public static function manageablePools(string $side): array
    {
        return array_values(array_filter(
            self::POOLS,
            static fn (string $pool): bool => self::canManagePool($side, $pool)
        ));
    }
}
