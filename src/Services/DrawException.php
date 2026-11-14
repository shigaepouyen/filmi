<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class DrawException extends RuntimeException
{
    private const LABELS = ['safe' => 'valeur sûre', 'discovery' => 'découverte', 'any' => 'film'];

    /**
     * @param array<string, int> $missing   catégorie vers nombre de films manquants
     * @param array<string, int> $available catégorie vers nombre de films disponibles
     */
    public function __construct(
        public readonly array $missing,
        public readonly array $available,
        string $pool = 'adult'
    ) {
        parent::__construct(self::buildMessage($missing, $pool));
    }

    /** @param array<string, int> $missing */
    private static function buildMessage(array $missing, string $pool): string
    {
        $parts = [];
        foreach ($missing as $category => $count) {
            $label = self::LABELS[$category] ?? $category;
            $parts[] = $count > 1 ? "{$count} {$label}s" : "{$count} {$label}";
        }

        return 'Tirage impossible, il manque ' . implode(' et ', $parts)
            . ($pool === 'kid' ? ' dans la liste des filles.' : ' dans le pool des parents.');
    }
}
