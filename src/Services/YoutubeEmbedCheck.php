<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Une bande-annonce que YouTube refuse d'integrer (video privee, supprimee,
 * integration interdite) n'affiche qu'une demande de connexion. On le sait avant
 * de l'enregistrer : l'endpoint oEmbed de YouTube renvoie 200 si la video est
 * lisible dans un lecteur integre, 401/403/404 sinon.
 */
final class YoutubeEmbedCheck
{
    /** Lisible ? En cas de doute (reseau, 5xx) on dit oui : mieux vaut garder une bonne bande-annonce que la perdre sur un incident. */
    public static function isEmbeddable(string $youtubeKey): bool
    {
        if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $youtubeKey) !== 1) {
            return false;
        }

        $handle = curl_init('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $youtubeKey));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        unset($handle);

        return $ok === false ? true : self::fromStatus($status);
    }

    /** Seuls 401, 403 et 404 sont des refus definitifs de YouTube. */
    public static function fromStatus(int $status): bool
    {
        return !in_array($status, [401, 403, 404], true);
    }
}
