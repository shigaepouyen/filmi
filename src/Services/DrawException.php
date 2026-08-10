<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class DrawException extends RuntimeException
{
    private const LABELS = ['safe' => 'valeur sûre', 'discovery' => 'découverte'];

    /**
     * @param array<string, int> $missing   catégorie vers nombre de films manquants
     * @param array<string, int> $available catégorie vers nombre de films disponibles
     */
    public function __construct(
        public readonly array $missing,
        public readonly array $available
    ) {
        parent::__construct(self::buildMessage($missing));
    }

    /** @param array<string, int> $missing */
    private static function buildMessage(array $missing): string
    {
        $parts = [];
        foreach ($missing as $category => $count) {
            $label = self::LABELS[$category] ?? $category;
            $parts[] = $count > 1 ? "{$count} {$label}s" : "{$count} {$label}";
        }

        return 'Tirage impossible, il manque ' . implode(' et ', $parts)
            . ' dans le pool des parents.';
    }
}
