<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Construit la demande faite au modele. Service pur : aucune base, aucun reseau.
 *
 * La ponderation est dite au modele en clair plutot que chiffree. Trois niveaux :
 * ce qui a ete vu (fort), les listes d'envies (moyen), les memos libres (faible,
 * et seulement s'ils ont ete autorises). Pour quatre personnes, des coefficients
 * donneraient une fausse precision.
 */
final class AiPrompt
{
    public const SYSTEM = <<<'TXT'
Tu es Madame Pop-Corn, la conseillère ciné d'une famille française de quatre personnes : deux parents et leurs deux filles. Chaque samedi soir, la famille regarde un film ou deux ou trois épisodes d'une série, avec pizza et apéro.

Ta mission : proposer des films et des séries que la famille ne connaît pas encore, en t'appuyant sur ses goûts.

Règles :
- Ne propose que des œuvres qui existent vraiment, avec leur année de sortie exacte. Si tu n'es pas sûre qu'une œuvre existe, ne la propose pas.
- RÈGLE ABSOLUE : la famille connaît déjà TOUT ce qui figure dans les sections 1 et 2 et dans « déjà connues ». Ce ne sont pas des suggestions, ce sont des indices de goût. Chaque idée doit être une œuvre qui n'apparaît dans AUCUNE de ces sections.
- Une valeur sûre est une œuvre proche de ce que la famille a aimé, jamais l'une de ces œuvres elles-mêmes. Évite les blockbusters que toute famille a déjà vus : préfère des œuvres moins évidentes.
- Les deux filles n'ont pas le même âge : privilégie ce que toute la famille peut regarder ensemble, et indique dans « audience » si une idée convient plutôt aux filles ou plutôt aux parents.
- Mélange valeurs sûres et découvertes (un pas de côté).
- Le champ « why » tient en une phrase courte en français et cite l'indice de goût qui motive l'idée (par exemple un titre vu et bien noté).
- Tutoiement ou vouvoiement : aucun, parle de la famille à la troisième personne.
TXT;

    /** @return array<string, mixed> schema de reponse attendu de Gemini */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'year' => ['type' => 'integer'],
                            'kind' => ['type' => 'string', 'enum' => ['film', 'series']],
                            'audience' => ['type' => 'string', 'enum' => ['all', 'kid', 'adult']],
                            'bet' => ['type' => 'string', 'enum' => ['safe', 'discovery']],
                            'why' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'year', 'kind', 'audience', 'bet', 'why'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }

    /**
     * @param array<string, mixed> $taste voir TasteRepository::taste()
     * @param list<string> $alreadySuggested titres deja proposes par le passe
     */
    public static function user(array $taste, array $alreadySuggested, int $count): string
    {
        $out = [];
        $out[] = "Propose {$count} idées. Réponds uniquement avec le JSON demandé.";
        $out[] = '';

        $out[] = '## 1. Ce que la famille a déjà regardé (POIDS FORT : c\'est le signal le plus fiable)';
        $out[] = 'Notes sur 5 par personne ; une personne absente de la liste des notes n\'a pas noté.';
        if ($taste['seen'] === []) {
            $out[] = '(rien de vu pour l\'instant)';
        }
        foreach ($taste['seen'] as $item) {
            $out[] = '- ' . self::label($item) . self::ratings($item['ratings'] ?? []);
        }
        $out[] = '';

        $out[] = '## 2. Les listes d\'envies (POIDS MOYEN)';
        foreach (['adult' => 'Proposées par les parents', 'kid' => 'Proposées par les filles'] as $key => $heading) {
            $out[] = $heading . ' (le nombre entre crochets est le nombre de votes) :';
            $items = $taste['lists'][$key] ?? [];
            if ($items === []) {
                $out[] = '- (liste vide)';
            }
            foreach ($items as $item) {
                $out[] = '- ' . self::label($item) . ' [' . (int) ($item['votes'] ?? 0) . ']';
            }
        }
        $out[] = '';

        if (($taste['vetoes'] ?? []) !== []) {
            $out[] = '## Films refusés par un veto parental (à éviter, et leurs voisins aussi)';
            foreach ($taste['vetoes'] as $veto) {
                $out[] = '- ' . self::label($veto);
            }
            $out[] = '';
        }

        if (!empty($taste['memos'])) {
            $memos = [];
            foreach (['adult', 'kid'] as $key) {
                foreach ($taste['lists'][$key] ?? [] as $item) {
                    if (isset($item['memo'])) {
                        $memos[] = '- ' . self::label($item) . ' : « ' . $item['memo'] . ' »';
                    }
                }
            }
            if ($memos !== []) {
                $out[] = '## 3. Notes laissées par la famille (POIDS FAIBLE : un simple indice, ne t\'y fie pas plus que de raison)';
                array_push($out, ...$memos);
                $out[] = '';
            }
        }

        $out[] = '## Déjà connues : ne les repropose pas';
        $known = array_values(array_unique(array_merge($taste['known'] ?? [], $alreadySuggested)));
        $out[] = $known === [] ? '(aucune)' : implode(' ; ', $known);

        return implode("\n", $out);
    }

    /** @param array<string, mixed> $item */
    private static function label(array $item): string
    {
        $label = (string) $item['title'];
        if (!empty($item['year'])) {
            $label .= ' (' . (int) $item['year'] . ')';
        }
        if (($item['kind'] ?? 'film') === 'series') {
            $label .= ', série';
        }

        return $label;
    }

    /** @param array<string, int> $ratings */
    private static function ratings(array $ratings): string
    {
        if ($ratings === []) {
            return ' : pas de note';
        }
        $parts = [];
        foreach ($ratings as $who => $score) {
            $parts[] = $who . ' ' . $score . '/5';
        }

        return ' : ' . implode(', ', $parts);
    }
}
