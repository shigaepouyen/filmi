<?php
declare(strict_types=1);

namespace App\Utils;

final class ViewRenderer
{
    /** @param array<string, mixed> $data */
    public static function component(string $name, array $data = []): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__, 2) . '/views/components/' . $name . '.php';

        return (string) ob_get_clean();
    }

    /** Rend une vue de page (sans layout), pour la tester hors serveur web. */
    public static function page(string $name, array $data = []): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__, 2) . '/views/' . $name . '.php';

        return (string) ob_get_clean();
    }
}
