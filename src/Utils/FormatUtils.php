<?php
declare(strict_types=1);

namespace App\Utils;

final class FormatUtils
{
    private const DAYS = [
        'Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi',
        'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche',
    ];

    private const MONTHS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre',
        11 => 'novembre', 12 => 'décembre',
    ];

    /** Abreviations d'usage, tronquer « décembre » donnerait « déce. ». */
    private const MONTHS_SHORT = [
        1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
        7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.',
        11 => 'nov.', 12 => 'déc.',
    ];

    public static function humanRuntime(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return 'durée inconnue';
        }

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0
            ? $hours . ' h'
            : sprintf('%d h %02d', $hours, $rest);
    }

    public static function endTime(string $startHhMm, ?int $minutes): ?string
    {
        if ($minutes === null || $minutes < 0) {
            return null;
        }

        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $startHhMm, $m)) {
            return null;
        }

        $total = ((int) $m[1] * 60 + (int) $m[2] + $minutes) % 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public static function frenchDate(string $isoDate): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
        if ($date === false || $date->format('Y-m-d') !== $isoDate) {
            return $isoDate;
        }

        return sprintf(
            '%s %d %s',
            self::DAYS[$date->format('D')],
            (int) $date->format('j'),
            self::MONTHS[(int) $date->format('n')]
        );
    }

    /**
     * Date courte, sans le jour de la semaine : « 4 juil. ». Sert la ou la place
     * manque, dans une pastille ou une liste serree.
     */
    public static function shortDate(string $isoDate): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
        if ($date === false || $date->format('Y-m-d') !== $isoDate) {
            return $isoDate;
        }

        return (int) $date->format('j') . ' ' . self::MONTHS_SHORT[(int) $date->format('n')];
    }
}
