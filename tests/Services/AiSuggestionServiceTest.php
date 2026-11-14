<?php
namespace App\Tests\Services;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SuggestionRepository;
use App\Repositories\TasteRepository;
use App\Services\AiSuggestionService;
use App\Services\GeminiService;
use App\Services\LlmException;
use App\Services\TmdbService;
use App\Tests\Support\DbTestCase;

class AiSuggestionServiceTest extends DbTestCase
{
    private SuggestionRepository $suggestions;
    private MovieRepository $movies;
    private SettingRepository $settings;
    private array $geminiBodies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $this->suggestions = new SuggestionRepository($this->db);
        $this->movies = new MovieRepository($this->db);
        $this->settings = new SettingRepository($this->db);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/' . $name);
    }

    /** @param list<array>|null $batches reponses successives du modele ; $ideas sert quand elles sont epuisees */
    private function service(array $ideas, ?string $geminiKey = 'k', ?array $batches = null): AiSuggestionService
    {
        $gemini = new GeminiService((string) $geminiKey, 'm', function (string $u, array $h, string $body) use ($ideas, $batches): string {
            $ideas = $batches[count($this->geminiBodies)] ?? $ideas;
            $this->geminiBodies[] = $body;
            return json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode(['suggestions' => $ideas])]]]]]]);
        });
        $routes = [
            '/search/movie' => $this->fixture('tmdb_search.json'),
            '/search/tv' => $this->fixture('tmdb_tv_search.json'),
            '/movie/129' => $this->fixture('tmdb_details.json'),
            '/tv/124834' => $this->fixture('tmdb_tv_details.json'),
            '/season/' => $this->fixture('tmdb_tv_season1.json'),
        ];
        $tmdb = new TmdbService('t', 'fr-FR', 'FR', function (string $url) use ($routes): string {
            foreach ($routes as $fragment => $payload) {
                if (str_contains($url, $fragment)) {
                    return $payload;
                }
            }
            throw new \App\Services\TmdbException('introuvable : ' . $url);
        });

        return new AiSuggestionService(
            $gemini, $tmdb, new TasteRepository($this->db, new ProfileRepository($this->db)),
            $this->suggestions, $this->movies, $this->settings
        );
    }

    private function idea(string $title, int $year, string $kind = 'film'): array
    {
        return ['title' => $title, 'year' => $year, 'kind' => $kind, 'audience' => 'all', 'bet' => 'safe', 'why' => 'Parce que'];
    }

    public function testIdeasAreVerifiedAtTmdbAndInventedOnesAreDropped(): void
    {
        $result = $this->service([
            $this->idea('Le Voyage de Chihiro', 2001),
            $this->idea('Film inventé', 2001),         // l'annee ne colle a aucun resultat
        ])->generate();

        $this->assertSame(['added' => 1, 'dropped' => 1, 'passes' => 2], $result);
        $row = $this->suggestions->byStatus('new')[0];
        $this->assertSame(129, (int) $row['tmdb_id']);
        $this->assertSame('Parce que', $row['why']);
    }

    public function testWorksAlreadyInTheListsOrSuggestedBeforeAreNotAddedAgain(): void
    {
        $this->movies->add(['tmdb_id' => 129, 'title' => 'Le Voyage de Chihiro', 'pool' => 'kid', 'added_by' => 1]);

        $this->assertSame(['added' => 0, 'dropped' => 1, 'passes' => 2], $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate());
    }

    public function testSeriesUseTheLightSummaryAndKeepNoEpisodeList(): void
    {
        $this->service([$this->idea('Heartstopper', 2022, 'series')])->generate();

        $row = $this->suggestions->byStatus('new')[0];
        $this->assertSame('series', $row['kind']);
        $this->assertSame(124834, (int) $row['tmdb_id']);
    }

    public function testDailyLimitStopsBeforeCallingTheModel(): void
    {
        $service = $this->service([]);
        for ($i = 0; $i < AiSuggestionService::DAILY_LIMIT; $i++) {
            $this->settings->countAiCall();
        }

        try {
            $service->generate();
            $this->fail('exception attendue');
        } catch (LlmException $e) {
            $this->assertStringContainsString('demain', $e->getMessage());
            $this->assertSame([], $this->geminiBodies);
        }
    }

    public function testNoGeminiKeyIsAClearErrorNotACall(): void
    {
        $this->expectException(LlmException::class);
        $this->service([], '')->generate();
    }

    public function testMemosAreSentOnlyWhenTheSettingIsOn(): void
    {
        $this->movies->add(['title' => 'Alien', 'pool' => 'adult', 'bet_type' => 'safe', 'memo' => 'mémo-test-unique', 'added_by' => 1]);

        $this->service([])->generate();
        $this->assertStringNotContainsString('mémo-test-unique', $this->geminiBodies[0]);

        $this->settings->setAiIncludeMemos(true);
        $this->service([])->generate();
        $this->assertStringContainsString('mémo-test-unique', $this->geminiBodies[1]);
    }

    public function testTransferCreatesAMovieInTheChosenListAndLocksTheSuggestion(): void
    {
        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate();
        $id = (int) $this->suggestions->byStatus('new')[0]['id'];
        $parent = (new ProfileRepository($this->db))->find(1);

        $movieId = $this->service([])->transfer($id, 'adult', $parent);

        $movie = $this->movies->find($movieId);
        $this->assertSame('adult', $movie['pool']);
        $this->assertSame('safe', $movie['bet_type']);
        $this->assertStringContainsString('Madame Pop-Corn', $movie['memo']);
        $this->assertSame('transferred', $this->suggestions->find($id)['status']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service([])->transfer($id, 'kid', $parent);
    }

    public function testAGirlCannotTransferIntoTheParentsList(): void
    {
        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate();
        $id = (int) $this->suggestions->byStatus('new')[0]['id'];
        $girl = (new ProfileRepository($this->db))->find(3);

        try {
            $this->service([])->transfer($id, 'adult', $girl);
            $this->fail('refus attendu');
        } catch (\InvalidArgumentException) {
            $this->assertSame('new', $this->suggestions->find($id)['status']);
            $this->assertSame(0, $this->movies->countPool('adult'));
        }
    }

    public function testTransferIsRefusedWhenTheWorkIsAlreadyInAList(): void
    {
        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate();
        $id = (int) $this->suggestions->byStatus('new')[0]['id'];
        $this->movies->add(['tmdb_id' => 129, 'title' => 'Le Voyage de Chihiro', 'pool' => 'kid', 'added_by' => 3]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service([])->transfer($id, 'kid', (new ProfileRepository($this->db))->find(3));
    }

    public function testASecondPassRecoversWhenTheFirstOnlyRepeatsKnownTitles(): void
    {
        $this->movies->add(['tmdb_id' => 129, 'title' => 'Le Voyage de Chihiro', 'pool' => 'kid', 'added_by' => 1]);

        $result = $this->service([], 'k', [
            [$this->idea('Le Voyage de Chihiro', 2001)],   // deja en liste : tout part en doublon
            [$this->idea('Heartstopper', 2022, 'series')], // le second passage trouve
        ])->generate();

        $this->assertSame(['added' => 1, 'dropped' => 1, 'passes' => 2], $result);
        $this->assertCount(2, $this->geminiBodies);
        // Le second passage redonne au modele le titre qu'il vient de se faire refuser.
        $this->assertStringContainsString('Le Voyage de Chihiro', $this->geminiBodies[1]);
        $this->assertSame(1, $this->settings->aiCallsToday(), 'deux passages = une seule demande comptee');
    }

    public function testTheLastAttemptIsKeptForEveryoneWithTheReasonsForDrops(): void
    {
        $this->movies->add(['tmdb_id' => 129, 'title' => 'Le Voyage de Chihiro', 'pool' => 'kid', 'added_by' => 1]);

        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate('JC');

        $last = $this->settings->lastAiAttempt();
        $this->assertSame('JC', $last['by']);
        $this->assertSame(0, $last['added']);
        $this->assertSame('Le Voyage de Chihiro', $last['dropped'][0]['title']);
        $this->assertStringContainsString('déjà dans vos listes', $last['dropped'][0]['reason']);
        $this->assertNull($last['error']);
    }

    public function testAFailedAttemptIsKeptToo(): void
    {
        $gemini = new GeminiService('k', 'm', fn () => throw new LlmException('Gemini a répondu 503.'));
        $service = new AiSuggestionService(
            $gemini, new TmdbService('t', 'fr-FR', 'FR', fn () => '{}'),
            new TasteRepository($this->db, new ProfileRepository($this->db)),
            $this->suggestions, $this->movies, $this->settings
        );

        try {
            $service->generate('Zoé');
            $this->fail('exception attendue');
        } catch (LlmException) {
        }

        $last = $this->settings->lastAiAttempt();
        $this->assertSame('Zoé', $last['by']);
        $this->assertSame('Gemini a répondu 503.', $last['error']);
    }

    public function testNeutralLabelsWrittenByTheModelBecomeRealNames(): void
    {
        $service = $this->service([]);
        $names = (new TasteRepository($this->db, new ProfileRepository($this->db)))->namesByLabel();
        $parent1 = $names['parent 1'];
        $parent2 = $names['parent 2'];

        $this->assertSame("Adoré par $parent1.", $service->withRealNames('Adoré par le parent 1.'));
        $this->assertSame("Adoré par $parent1.", $service->withRealNames('Adoré par Parent 1.'));
        $withOf = $service->withRealNames('Dans la lignée des goûts du parent 2.');
        $this->assertStringContainsString($parent2, $withOf);
        $this->assertStringNotContainsString('parent 2', $withOf);
        $this->assertMatchesRegularExpression("/goûts (de |d')" . preg_quote($parent2, '/') . '\./u', $withOf);
        // Un numero inconnu reste tel quel : jamais de nom invente.
        $this->assertSame('Aimé par le parent 9.', $service->withRealNames('Aimé par le parent 9.'));
    }

    public function testTheModelNeverReceivesRealNamesButTheStoredReasonDoes(): void
    {
        $name = (new TasteRepository($this->db, new ProfileRepository($this->db)))->namesByLabel()['parent 1'];
        $this->service([array_merge($this->idea('Le Voyage de Chihiro', 2001), ['why' => 'Adoré par le parent 1'])])->generate();

        $this->assertStringNotContainsString($name, $this->geminiBodies[0]);
        $this->assertSame("Adoré par $name", $this->suggestions->byStatus('new')[0]['why']);
    }

    public function testAskingForTheParentsListFeedsTheModelOnlyWithThatListsFilmsSeenAndUnseen(): void
    {
        $jc = 1;
        $parentsSeen = $this->movies->add(['title' => 'Film vu des parents', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $jc]);
        $this->movies->add(['title' => 'Film à voir des parents', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $jc]);
        $girlsSeen = $this->movies->add(['title' => 'Film vu des filles', 'pool' => 'kid', 'added_by' => $jc]);
        $this->movies->add(['title' => 'Film à voir des filles', 'pool' => 'kid', 'added_by' => $jc]);
        foreach ([$parentsSeen, $girlsSeen] as $i => $id) {
            $this->db->exec("INSERT INTO seances (date, chooser_side, status, movie_id) VALUES ('2026-07-0" . ($i + 1) . "', 'adult', 'done', {$id})");
            $this->movies->markWatched($id);
        }

        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate('JC', 'adult');
        $body = $this->geminiBodies[0];
        // Ce qui nourrit les goûts (sections 1 et 2) ; la liste « déjà connues » vient apres.
        [$taste, $known] = explode('Déjà connues', $body, 2);

        $this->assertStringContainsString('Film vu des parents', $taste);
        $this->assertStringContainsString('Film à voir des parents', $taste);
        $this->assertStringNotContainsString('Film vu des filles', $taste);
        $this->assertStringNotContainsString('Film à voir des filles', $taste);
        // Mais tout reste « deja connu » : on ne propose rien de ce que la famille a deja.
        $this->assertStringContainsString('Film vu des filles', $known);
        $this->assertStringContainsString('Film à voir des filles', $known);
        // Aucune orientation, le modele garde son propre champ audience.
        $this->assertStringNotContainsString('UNIQUEMENT', $body);
        $stored = $this->suggestions->byStatus('new')[0];
        $this->assertSame('adult', $stored['source']);
        $this->assertSame('all', $stored['audience']);
        $this->assertSame('adult', $this->settings->lastAiAttempt()['source']);
    }

    public function testEveryoneUsesBothListsAndUnknownSourcesMeanEveryone(): void
    {
        $this->movies->add(['title' => 'Liste des parents', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => 1]);
        $this->movies->add(['title' => 'Liste des filles', 'pool' => 'kid', 'added_by' => 1]);

        $this->service([])->generate('JC', 'n-importe-quoi');

        $this->assertStringContainsString('Proposées par les parents', $this->geminiBodies[0]);
        $this->assertStringContainsString('Proposées par les filles', $this->geminiBodies[0]);
        $this->assertSame('all', $this->settings->lastAiAttempt()['source']);
    }

    public function testTheSecondPassUsesTheStricterRetryModelAndTheFirstDoesNot(): void
    {
        $this->movies->add(['tmdb_id' => 129, 'title' => 'Le Voyage de Chihiro', 'pool' => 'kid', 'added_by' => 1]);
        $urls = [];
        $batches = [[$this->idea('Le Voyage de Chihiro', 2001)], [$this->idea('Heartstopper', 2022, 'series')]];
        $gemini = new GeminiService('k', 'modele-leger', function (string $url) use (&$urls, $batches): string {
            $ideas = $batches[count($urls)] ?? [];
            $urls[] = $url;
            return json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode(['suggestions' => $ideas])]]]]]]);
        }, 'repli');
        $tmdb = new TmdbService('t', 'fr-FR', 'FR', function (string $url): string {
            foreach (['/search/movie' => 'tmdb_search.json', '/search/tv' => 'tmdb_tv_search.json', '/movie/129' => 'tmdb_details.json', '/tv/124834' => 'tmdb_tv_details.json'] as $fragment => $file) {
                if (str_contains($url, $fragment)) {
                    return $this->fixture($file);
                }
            }
            throw new \App\Services\TmdbException('introuvable : ' . $url);
        });
        $service = new AiSuggestionService($gemini, $tmdb, new TasteRepository($this->db, new ProfileRepository($this->db)),
            $this->suggestions, $this->movies, $this->settings, 'modele-strict');

        $result = $service->generate('JC');

        $this->assertSame(2, $result['passes']);
        $this->assertStringContainsString('modele-leger:generateContent', $urls[0]);
        $this->assertStringContainsString('modele-strict:generateContent', $urls[1]);
    }

    public function testProgressIsWrittenStepByStepAndEndsDone(): void
    {
        $this->movies->add(['title' => 'Film à voir', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => 1]);

        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate('JC');

        $p = $this->settings->aiProgress();
        $this->assertSame('done', $p['state']);
        $this->assertSame('JC', $p['by']);
        $this->assertSame(1, $p['added']);
        $this->assertSame('0 film vu, 1 à voir', $p['taste']);
        $this->assertSame(1, $p['total']);
        $this->assertSame(1, $p['checked']);
        $this->assertGreaterThanOrEqual($p['started'], $p['updated']);
    }

    public function testASecondRequestIsRefusedWhileOneIsRunningAndDoesNotSpendTheQuota(): void
    {
        $this->settings->setAiProgress(['state' => 'running', 'by' => 'Élodie', 'started' => time(), 'updated' => time()]);
        $service = $this->service([$this->idea('Le Voyage de Chihiro', 2001)]);

        try {
            $service->generate('JC');
            $this->fail('exception attendue');
        } catch (LlmException $e) {
            $this->assertStringContainsString('déjà au travail', $e->getMessage());
            $this->assertStringContainsString('Élodie', $e->getMessage());
        }

        $this->assertSame([], $this->geminiBodies, 'le modele n\'est pas appele');
        $this->assertSame(0, $this->settings->aiCallsToday(), 'pas de demande comptee');
        $this->assertSame('running', $this->settings->aiProgress()['state'], 'la demande en cours n\'est pas ecrasee');
    }

    public function testADeadRunningRequestDoesNotBlockForever(): void
    {
        $old = time() - AiSuggestionService::STALE_AFTER - 5;
        $this->settings->setAiProgress(['state' => 'running', 'by' => 'Élodie', 'started' => $old, 'updated' => $old]);

        $result = $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate('JC');

        $this->assertSame(1, $result['added']);
        $this->assertSame('done', $this->settings->aiProgress()['state']);
    }

    public function testAFailureLeavesAnErrorStateNotARunningOne(): void
    {
        $gemini = new GeminiService('k', 'm', fn () => throw new LlmException('Gemini a répondu 503.'));
        $service = new AiSuggestionService(
            $gemini, new TmdbService('t', 'fr-FR', 'FR', fn () => '{}'),
            new TasteRepository($this->db, new ProfileRepository($this->db)),
            $this->suggestions, $this->movies, $this->settings
        );

        try {
            $service->generate('JC');
            $this->fail('exception attendue');
        } catch (LlmException) {
        }

        $p = $this->settings->aiProgress();
        $this->assertSame('error', $p['state']);
        $this->assertSame('Gemini a répondu 503.', $p['message']);
        // Et une nouvelle demande est possible tout de suite.
        $this->assertNotSame('running', $p['state']);
    }

    public function testTheSecondPassIsReportedInTheProgress(): void
    {
        $this->movies->add(['tmdb_id' => 129, 'title' => 'Le Voyage de Chihiro', 'pool' => 'kid', 'added_by' => 1]);

        $this->service([], 'k', [[$this->idea('Le Voyage de Chihiro', 2001)], [$this->idea('Heartstopper', 2022, 'series')]])->generate('JC');

        $p = $this->settings->aiProgress();
        $this->assertSame(2, $p['pass']);
        $this->assertSame('done', $p['state']);
    }
}
