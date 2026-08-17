<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Quand une seance peut recevoir une note.
 *
 * La regle vit ici, en un seul endroit, parce qu'elle doit valoir a l'identique
 * sur la page d'une seance, dans l'historique et sur les endpoints JSON. La
 * dupliquer, c'est se retrouver avec une interface qui refuse et une requete
 * forgee qui passe.
 */
final class RatingRules
{
    /**
     * Une seance se note si elle a bien eu lieu avec une oeuvre, et, dans le cas
     * d'une serie, seulement quand celle-ci est terminee : une note porte sur
     * l'oeuvre entiere, pas sur chacune des soirees qui l'ont composee.
     */
    public static function isRatable(?string $seanceStatus, ?string $movieKind, ?string $movieStatus): bool
    {
        if ($seanceStatus !== 'done') {
            return false;
        }

        if (($movieKind ?? 'film') !== 'series') {
            return true;
        }

        return $movieStatus === 'watched';
    }
}
