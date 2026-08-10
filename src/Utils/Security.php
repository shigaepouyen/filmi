<?php
declare(strict_types=1);

namespace App\Utils;

final class Security
{
    private const KEY = 'filmi_csrf';

    public static function csrfToken(): string
    {
        self::assertSessionUsable();

        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::KEY];
    }

    public static function checkCsrf(?string $token): bool
    {
        self::assertSessionUsable();

        if ($token === null || $token === '' || empty($_SESSION[self::KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::KEY], $token);
    }

    /**
     * En contexte web, un jeton sans session active ne survivrait pas a la requete
     * suivante et ferait echouer silencieusement tous les envois de formulaire.
     * On prefere echouer bruyamment. En CLI (tests), $_SESSION comme simple
     * tableau suffit et aucune session n'est requise.
     */
    private static function assertSessionUsable(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException(
                'Aucune session active : App::boot() doit appeler Session::start() avant tout jeton CSRF.'
            );
        }
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
