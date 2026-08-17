<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Ou renvoyer quelqu'un apres le choix de son profil.
 *
 * Un lien partage a la famille vise une page precise, l'historique par exemple.
 * Sans memoire de cette intention, le choix de profil renvoie tout le monde a
 * l'accueil et le lien ne sert a rien.
 *
 * La destination arrive donc dans l'URL, ce qui en fait une valeur fournie de
 * l'exterieur : la reexpedier telle quelle serait une redirection ouverte, de
 * quoi faire atterrir la famille sur un site quelconque depuis un lien qui
 * semble etre celui de Filmi. Seuls les chemins d'une page connue de l'app
 * passent, jamais rien qui ressemble a une adresse absolue.
 */
final class Redirects
{
    public const DEFAULT = '/tonight.php';

    /**
     * Les pages ou l'on peut atterrir. index.php en est exclu : s'y renvoyer
     * apres avoir choisi un profil ferait boucler l'ecran de choix.
     */
    private const PAGES = [
        '/tonight.php',
        '/pool.php',
        '/add.php',
        '/draw.php',
        '/seance.php',
        '/movie.php',
        '/history.php',
        '/awards.php',
        '/settings.php',
    ];

    private const MAX_LENGTH = 200;

    /** Le chemin sur lequel repartir, ou null si la valeur n'est pas digne de confiance. */
    public static function sanitize(?string $raw): ?string
    {
        if ($raw === null || $raw === '' || strlen($raw) > self::MAX_LENGTH) {
            return null;
        }

        // Un chemin, et rien d'autre : pas de schema, pas d'hote, pas de
        // '//evil.example' que le navigateur lirait comme un domaine, pas de
        // retour chariot qui couperait l'en-tete Location en deux.
        if ($raw[0] !== '/' || str_starts_with($raw, '//')) {
            return null;
        }
        if (preg_match('/[\r\n\t\\\\]/', $raw) === 1) {
            return null;
        }

        $parts = parse_url($raw);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (!in_array($path, self::PAGES, true)) {
            return null;
        }

        $query = $parts['query'] ?? '';
        if ($query !== '' && preg_match('/^[A-Za-z0-9_\-=&%.]+$/', $query) !== 1) {
            return null;
        }

        return $query === '' ? $path : $path . '?' . $query;
    }

    /** La destination retenue, avec l'accueil pour repli. */
    public static function target(?string $raw): string
    {
        return self::sanitize($raw) ?? self::DEFAULT;
    }

    /**
     * L'adresse de l'ecran de choix de profil, en gardant en memoire la page
     * demandee. Seule une navigation normale est memorisee : rejouer un POST
     * apres le choix du profil n'aurait aucun sens, son corps est deja perdu.
     */
    public static function profileChoiceUrl(?string $requestUri, string $method = 'GET'): string
    {
        $next = $method === 'GET' ? self::sanitize($requestUri) : null;

        return $next === null ? '/index.php' : '/index.php?next=' . urlencode($next);
    }
}
