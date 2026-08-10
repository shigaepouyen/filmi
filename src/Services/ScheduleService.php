<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class ScheduleService
{
    public const SIDE_ADULT = 'adult';
    public const SIDE_KID = 'kid';

    public static function nextSeanceDate(?DateTimeImmutable $today = null): DateTimeImmutable
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);

        if ($today->format('D') === 'Sat') {
            return $today;
        }

        return $today->modify('next saturday')->setTime(0, 0);
    }

    /**
     * @param list<array{status:string,chooser_side:string}> $seances triées par date décroissante
     */
    public static function defaultChooserSide(array $seances): string
    {
        foreach ($seances as $seance) {
            $status = $seance['status'] ?? '';

            if ($status === 'skipped') {
                // Samedi sauté : le camp prévu est reporté tel quel.
                return self::normalise($seance['chooser_side'] ?? '');
            }

            if ($status === 'done') {
                return self::opposite($seance['chooser_side'] ?? '');
            }
            // 'planned' : séance en cours de préparation, sans valeur historique.
        }

        return self::SIDE_ADULT;
    }

    public static function opposite(string $side): string
    {
        // Toute valeur non reconnue retombe sur "adult", pas sur l'oppose de "adult".
        return $side === self::SIDE_ADULT ? self::SIDE_KID : self::SIDE_ADULT;
    }

    private static function normalise(string $side): string
    {
        return $side === self::SIDE_KID ? self::SIDE_KID : self::SIDE_ADULT;
    }
}
