<?php
declare(strict_types=1);

namespace App\Utils;

final class Security
{
    private const KEY = 'filmi_csrf';

    public static function csrfToken(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::KEY];
    }

    public static function checkCsrf(?string $token): bool
    {
        if ($token === null || $token === '' || empty($_SESSION[self::KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::KEY], $token);
    }

    public static function e(?string $value): string
    {
        return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
