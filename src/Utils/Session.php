<?php
declare(strict_types=1);

namespace App\Utils;

use App\Repositories\ProfileRepository;

final class Session
{
    public const COOKIE = 'filmi_profile';
    private const TTL = 31536000; // un an

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
            session_start();
        }
    }

    /**
     * Le site est servi en HTTPS uniquement en production, mais le dev local tourne
     * en clair : le flag 'secure' ne doit s'activer que si la requête est réellement
     * en HTTPS, sinon aucun cookie ne serait posé en local.
     */
    public static function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS']);
    }

    public static function currentProfileId(): ?int
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;

        return ctype_digit((string) $raw) ? (int) $raw : null;
    }

    public static function setCurrentProfile(int $id): void
    {
        setcookie(self::COOKIE, (string) $id, [
            'expires' => time() + self::TTL,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
        ]);
        $_COOKIE[self::COOKIE] = (string) $id;
    }

    public static function clear(): void
    {
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE[self::COOKIE]);
    }

    /** Renvoie le profil courant, ou renvoie vers le choix de profil et arrête le script. */
    public static function requireProfile(ProfileRepository $repo): array
    {
        self::start();
        $id = self::currentProfileId();
        $profile = $id !== null ? $repo->find($id) : null;

        if ($profile === null) {
            self::clear();
            header('Location: /index.php');
            exit;
        }

        return $profile;
    }
}
