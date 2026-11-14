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

    public function testAskingForAnAudienceSteersThePromptAndForcesTheStoredAudience(): void
    {
        // Le modele repond « all » : la demande ciblee fait foi.
        $this->service([$this->idea('Le Voyage de Chihiro', 2001)])->generate('JC', 'kid');

        $this->assertStringContainsString('UNIQUEMENT les filles', $this->geminiBodies[0]);
        $this->assertSame('kid', $this->suggestions->byStatus('new')[0]['audience']);
        $this->assertSame('kid', $this->settings->lastAiAttempt()['audience']);
    }

    public function testWithoutATargetTheModelKeepsItsOwnAudienceAndUnknownTargetsMeanEveryone(): void
    {
        $idea = array_merge($this->idea('Le Voyage de Chihiro', 2001), ['audience' => 'adult']);
        $this->service([$idea])->generate('JC', 'n-importe-quoi');

        $this->assertStringContainsString('Toute la famille', $this->geminiBodies[0]);
        $this->assertSame('adult', $this->suggestions->byStatus('new')[0]['audience']);
        $this->assertSame('all', $this->settings->lastAiAttempt()['audience']);
    }
}
