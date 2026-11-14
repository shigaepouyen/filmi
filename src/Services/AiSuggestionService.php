<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MovieRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SuggestionRepository;
use App\Repositories\TasteRepository;
use App\Utils\Access;

/**
 * Madame Pop-Corn : demande des idees au modele, les verifie chez TMDb, les range.
 *
 * Le modele ne fournit que des titres. Tout ce que la famille voit (affiche,
 * synopsis, plateformes, avis parental) vient de TMDb, et une idee que TMDb ne
 * retrouve pas est ecartee : c'est le garde-fou contre les titres inventes.
 */
final class AiSuggestionService
{
    public const DAILY_LIMIT = 6;
    public const ASKED = 8;
    public const KEPT_PER_CALL = 6;
    /** En dessous, on redemande une fois au modele. */
    public const MIN_USEFUL = 3;
    public const MAX_PASSES = 2;

    public function __construct(
        private GeminiService $gemini,
        private TmdbService $tmdb,
        private TasteRepository $taste,
        private SuggestionRepository $suggestions,
        private MovieRepository $movies,
        private SettingRepository $settings
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured() && $this->tmdb->isConfigured();
    }

    /**
     * Une demande = au plus deux passages chez le modele, comptes pour un seul appel :
     * un modele leger repropose volontiers des classiques que la famille a deja, et
     * tout part alors en doublon. Le second passage lui redonne les titres refuses.
     * Le resultat, y compris un echec, est garde pour toute la famille (voir
     * SettingRepository::lastAiAttempt()).
     *
     * @param string|null $by prenom du profil qui a clique, pour l'afficher
     * @return array{added: int, dropped: int, passes: int}
     * @throws LlmException
     */
    public function generate(?string $by = null): array
    {
        if (!$this->gemini->isConfigured()) {
            throw new LlmException("Madame Pop-Corn n'a pas de clé Gemini. Elle se renseigne dans config/config.php.");
        }
        if (!$this->tmdb->isConfigured()) {
            throw new LlmException("Madame Pop-Corn a besoin de la clé TMDb pour vérifier ses idées.");
        }
        if ($this->settings->aiCallsToday() >= self::DAILY_LIMIT) {
            throw new LlmException(
                'Madame Pop-Corn a déjà donné ses idées ' . self::DAILY_LIMIT . ' fois aujourd\'hui. Elle reprend demain.'
            );
        }

        $taste = $this->taste->taste($this->settings->aiIncludeMemos());

        // L'appel est compte avant d'etre fait : un echec cote Google consomme
        // quand meme du quota, et un bouton qu'on martele ne doit pas le vider.
        $this->settings->countAiCall();

        $added = 0;
        $dropped = [];
        $tried = [];
        $passes = 0;
        $record = ['at' => date('Y-m-d H:i'), 'by' => $by, 'added' => 0, 'passes' => 0, 'dropped' => [], 'error' => null];

        $startedAt = microtime(true);
        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $passes++;
            try {
                $payload = $this->gemini->generateJson(
                    AiPrompt::SYSTEM,
                    AiPrompt::user($taste, array_merge($this->suggestions->knownTitles(), $tried), self::ASKED),
                    AiPrompt::schema()
                );
            } catch (LlmException $e) {
                if ($pass === 0) {
                    $record['error'] = $e->getMessage();
                    $record['passes'] = $passes;
                    $this->settings->setLastAiAttempt($record);
                    throw $e;
                }
                break; // le premier passage a deja donne quelque chose : on le garde
            }

            foreach (($payload['suggestions'] ?? []) as $idea) {
                if (!is_array($idea) || $added >= self::KEPT_PER_CALL) {
                    continue;
                }
                $title = trim((string) ($idea['title'] ?? ''));
                $key = mb_strtolower($title);
                if ($title === '' || in_array($key, $tried, true)) {
                    continue; // deja vu pendant cette demande : on ne le compte pas deux fois
                }
                $tried[] = $key;

                $reason = $this->store($idea);
                if ($reason === null) {
                    $added++;
                } else {
                    $dropped[] = [
                        'title' => $title,
                        'year' => isset($idea['year']) && is_numeric($idea['year']) ? (int) $idea['year'] : null,
                        'reason' => $reason,
                    ];
                }
            }

            // Pas de second passage si le premier a deja pris beaucoup de temps.
            if ($added >= self::MIN_USEFUL || $dropped === [] || microtime(true) - $startedAt > 30) {
                break;
            }
        }

        $record['added'] = $added;
        $record['passes'] = $passes;
        $record['dropped'] = $dropped;
        $this->settings->setLastAiAttempt($record);

        return ['added' => $added, 'dropped' => count($dropped), 'passes' => $passes];
    }

    /**
     * @param array<string, mixed> $idea
     * @return string|null null si l'idee est enregistree, sinon la raison de son rejet
     */
    private function store(array $idea): ?string
    {
        $title = trim((string) ($idea['title'] ?? ''));
        $year = isset($idea['year']) && is_numeric($idea['year']) ? (int) $idea['year'] : null;
        $kind = ($idea['kind'] ?? 'film') === 'series' ? 'series' : 'film';
        if ($title === '') {
            return 'sans titre';
        }

        try {
            $found = $this->match($title, $year, $kind);
            if ($found === null) {
                return 'introuvable chez TMDb';
            }

            $tmdbId = (int) $found['tmdb_id'];
            if ($this->suggestions->exists($tmdbId, $kind)) {
                return 'déjà proposée';
            }
            if ($this->movies->findDuplicate($tmdbId, (string) $found['title'], $found['year']) !== null) {
                return 'déjà dans vos listes ou déjà vu';
            }

            $details = $kind === 'series'
                ? $this->tmdb->seriesSummary($tmdbId)
                : $this->tmdb->details($tmdbId);
        } catch (TmdbException) {
            // Une idee dont la fiche est injoignable est perdue, pas le lot entier.
            return 'fiche TMDb injoignable';
        }

        return $this->suggestions->add($details + [
            'kind' => $kind,
            'audience' => $idea['audience'] ?? 'all',
            'bet_type' => $idea['bet'] ?? null,
            'why' => $idea['why'] ?? null,
        ]) !== null ? null : 'non enregistrée';
    }

    /**
     * Retrouve l'oeuvre chez TMDb. L'annee annoncee doit tomber a un an pres : sans
     * elle, un titre court ou un remake rendrait une tout autre oeuvre.
     *
     * @return array{tmdb_id:int,title:string,year:?int}|null
     */
    private function match(string $title, ?int $year, string $kind): ?array
    {
        $results = $kind === 'series' ? $this->tmdb->searchSeries($title) : $this->tmdb->search($title);

        foreach ($results as $result) {
            if ($year === null || $result['year'] === null || abs((int) $result['year'] - $year) <= 1) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Fait passer une idee dans la liste des parents ou des filles.
     *
     * @param array<string, mixed> $profile le profil qui agit
     * @return int l'identifiant du film cree dans movies
     * @throws \InvalidArgumentException refus explicite, jamais un repli silencieux
     */
    public function transfer(int $suggestionId, string $pool, array $profile): int
    {
        // Meme regle asymetrique que partout : les parents alimentent les deux
        // listes, les filles la leur seulement. Verifiee ici, cote serveur.
        if (!Access::canManagePool((string) $profile['side'], $pool)) {
            throw new \InvalidArgumentException('Tu ne peux ajouter un film que dans ta propre liste.');
        }

        $suggestion = $this->suggestions->find($suggestionId);
        if ($suggestion === null) {
            throw new \InvalidArgumentException("Cette idée n'existe plus.");
        }
        if ($suggestion['status'] === 'transferred') {
            throw new \InvalidArgumentException('Cette idée est déjà dans une liste.');
        }

        $tmdbId = (int) $suggestion['tmdb_id'];
        if ($this->movies->findDuplicate($tmdbId, (string) $suggestion['title'], $suggestion['year'] === null ? null : (int) $suggestion['year']) !== null) {
            throw new \InvalidArgumentException('Ce titre est déjà dans une liste ou a déjà été vu.');
        }

        $memo = 'Idée de Madame Pop-Corn' . (trim((string) $suggestion['why']) !== '' ? ' : ' . trim((string) $suggestion['why']) : '');
        $common = [
            'pool' => $pool,
            'memo' => $memo,
            'added_by' => (int) $profile['id'],
        ];

        try {
            if ($suggestion['kind'] === 'series') {
                // La suite d'episodes n'est chargee qu'ici, pas a la suggestion.
                $movieId = $this->movies->addSeries(array_merge($this->tmdb->seriesDetails($tmdbId), $common));
            } else {
                $betType = $pool === 'adult' ? ($suggestion['bet_type'] ?? 'discovery') : null;
                try {
                    // Fiche rechargee pour obtenir la saga, que la suggestion ne porte pas.
                    $data = $this->tmdb->details($tmdbId);
                } catch (TmdbException) {
                    $data = $this->storedDetails($suggestion);
                }
                $movieId = $this->movies->add(array_merge($data, $common, ['bet_type' => $betType]));
            }
        } catch (TmdbException) {
            throw new \InvalidArgumentException('TMDb est injoignable. Réessaie dans un instant.');
        }

        $this->suggestions->markTransferred($suggestionId, $movieId);

        return $movieId;
    }

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function storedDetails(array $s): array
    {
        $keys = ['tmdb_id', 'title', 'original_title', 'year', 'runtime', 'poster_url', 'overview',
            'genres', 'director', 'tmdb_rating', 'certification', 'providers', 'providers_at', 'trailer_url'];

        return array_intersect_key($s, array_flip($keys));
    }
}
