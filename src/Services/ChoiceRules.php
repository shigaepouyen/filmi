<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Ce qu'on accepte de retenir comme choix du samedi soir.
 *
 * La shortlist et le film retenu arrivent du navigateur : les prendre tels quels
 * laisserait une requete forgee marquer n'importe quoi comme vu et poser un
 * cooldown sur toute la liste. La reference est le trio reellement tire, garde en
 * session. Si la session a expire entre le tirage et le choix, on se rabat sur ce
 * que le tirage aurait pu sortir, ce qui reste bien plus etroit que la confiance
 * aveugle.
 */
final class ChoiceRules
{
    /**
     * @param list<int> $shortlist       ce que le navigateur affirme avoir tire
     * @param list<int>|null $drawnIds   le trio reellement tire, null si oublie
     * @param list<int> $drawableIds     les films que le tirage pourrait sortir
     */
    public static function accepts(array $shortlist, int $chosen, ?array $drawnIds, array $drawableIds): bool
    {
        if ($shortlist === [] || $chosen <= 0 || !in_array($chosen, $shortlist, true)) {
            return false;
        }

        if ($drawnIds !== null) {
            // Egalite d'ensembles : ni un film ajoute, ni un film retire.
            return array_diff($shortlist, $drawnIds) === [] && array_diff($drawnIds, $shortlist) === [];
        }

        return array_diff($shortlist, $drawableIds) === [];
    }
}
