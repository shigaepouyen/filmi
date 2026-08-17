<?php
namespace App\Tests\Repositories;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Tests\Support\DbTestCase;

class MovieRepositoryTest extends DbTestCase
{
    private MovieRepository $repo;
    private int $jc;
    private int $zoe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $profiles = new ProfileRepository($this->db);
        $this->jc = $profiles->findBySlug('jc')['id'];
        $this->zoe = $profiles->findBySlug('zoe')['id'];
        $this->repo = new MovieRepository($this->db);
    }

    private function addAdult(string $title, string $betType, array $extra = []): int
    {
        return $this->repo->add(array_merge([
            'title' => $title,
            'pool' => 'adult',
            'bet_type' => $betType,
            'added_by' => $this->jc,
            'memo' => 'Parce que ça compte',
        ], $extra));
    }

    public function testAddStoresTheMinimalFieldsAndDefaultsTheRest(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');
        $movie = $this->repo->find($id);

        $this->assertSame('Brazil', $movie['title']);
        $this->assertSame('adult', $movie['pool']);
        $this->assertSame('discovery', $movie['bet_type']);
        $this->assertSame('pool', $movie['status']);
        $this->assertSame($this->jc, $movie['added_by']);
        $this->assertSame('Parce que ça compte', $movie['memo']);
        $this->assertNull($movie['tmdb_id']);
        $this->assertNull($movie['runtime']);
        $this->assertNotEmpty($movie['created_at']);
    }

    public function testAddKeepsEveryTmdbField(): void
    {
        $id = $this->addAdult('Le Voyage de Chihiro', 'safe', [
            'tmdb_id' => 129,
            'original_title' => '千と千尋の神隠し',
            'year' => 2001,
            'runtime' => 125,
            'poster_url' => 'https://image.tmdb.org/t/p/w342/chihiro.jpg',
            'overview' => 'Un résumé',
            'genres' => '["Animation"]',
            'director' => 'Hayao Miyazaki',
            'tmdb_rating' => 8.5,
            'certification' => 'Tous publics',
            'providers' => '["Netflix"]',
            'providers_at' => '2026-08-10 12:00:00',
        ]);

        $movie = $this->repo->find($id);

        $this->assertSame(129, $movie['tmdb_id']);
        $this->assertSame(125, $movie['runtime']);
        $this->assertSame('Hayao Miyazaki', $movie['director']);
        $this->assertSame('Tous publics', $movie['certification']);
        $this->assertSame(['Netflix'], json_decode($movie['providers'], true));
    }

    public function testKidPoolNeverKeepsABetType(): void
    {
        $id = $this->repo->add([
            'title' => 'Une comédie musicale',
            'pool' => 'kid',
            'bet_type' => 'safe',
            'added_by' => $this->zoe,
        ]);

        $this->assertNull($this->repo->find($id)['bet_type']);
    }

    public function testPoolReturnsOnlyItsOwnPoolAndOnlyPoolStatus(): void
    {
        $this->addAdult('Brazil', 'discovery');
        $watched = $this->addAdult('Alien', 'safe');
        $this->repo->markWatched($watched);
        $this->repo->add(['title' => 'Un film de filles', 'pool' => 'kid', 'added_by' => $this->zoe]);

        $adult = $this->repo->pool('adult');
        $kid = $this->repo->pool('kid');

        $this->assertSame(['Brazil'], array_column($adult, 'title'));
        $this->assertSame(['Un film de filles'], array_column($kid, 'title'));
    }

    public function testPoolExposesProposerAndVoteCount(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');
        $this->db->prepare('INSERT INTO votes (movie_id, profile_id) VALUES (?, ?)')
                 ->execute([$id, $this->zoe]);

        $row = $this->repo->pool('adult')[0];

        $this->assertSame('JC', $row['proposer_name']);
        $this->assertSame('blob', $row['proposer_avatar']);
        $this->assertSame(1, $row['vote_count']);
        $this->assertSame('Zoé', $row['voter_names']);
    }

    public function testPoolSortsByVotesThenRecency(): void
    {
        $peu = $this->addAdult('Peu voté', 'discovery');
        $beaucoup = $this->addAdult('Très voté', 'safe');
        $stmt = $this->db->prepare('INSERT INTO votes (movie_id, profile_id) VALUES (?, ?)');
        foreach ([$this->jc, $this->zoe] as $voter) {
            $stmt->execute([$beaucoup, $voter]);
        }
        $stmt->execute([$peu, $this->jc]);

        $this->assertSame(
            ['Très voté', 'Peu voté'],
            array_column($this->repo->pool('adult', 'votes'), 'title')
        );
    }

    public function testPoolSortByRuntimePutsShortestFirstAndUnknownLast(): void
    {
        $this->addAdult('Long', 'discovery', ['runtime' => 180]);
        $this->addAdult('Court', 'safe', ['runtime' => 88]);
        $this->addAdult('Inconnu', 'discovery');

        $this->assertSame(
            ['Court', 'Long', 'Inconnu'],
            array_column($this->repo->pool('adult', 'runtime'), 'title')
        );
    }

    public function testUnknownSortFallsBackOnVotesWithoutCrashing(): void
    {
        $this->addAdult('Brazil', 'discovery');

        $this->assertCount(1, $this->repo->pool('adult', 'n importe quoi'));
    }

    public function testDrawCandidatesOnlyReturnsTheAdultPool(): void
    {
        $this->addAdult('Brazil', 'discovery');
        $this->repo->add(['title' => 'Film de filles', 'pool' => 'kid', 'added_by' => $this->zoe]);

        $candidates = $this->repo->drawCandidates();

        $this->assertSame(['Brazil'], array_column($candidates, 'title'));
        $this->assertArrayHasKey('bet_type', $candidates[0]);
        $this->assertArrayHasKey('id', $candidates[0]);
    }

    public function testDrawCandidatesNeverReturnsASeries(): void
    {
        // Un tirage engagerait la famille sur des mois de television sans
        // l'avoir decide : une serie ne doit jamais pouvoir sortir du tirage,
        // meme si elle porte un bet_type et vit dans le pool adulte.
        $this->addAdult('Brazil', 'discovery');
        $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'adult',
            'bet_type' => 'safe',
            'added_by' => $this->zoe,
            'episode_count' => 24,
            'season_count' => 3,
            'episodes' => [],
        ]);

        $candidates = $this->repo->drawCandidates();

        $this->assertSame(['Brazil'], array_column($candidates, 'title'));
    }

    public function testFindDuplicateMatchesOnTmdbId(): void
    {
        $this->addAdult('Le Voyage de Chihiro', 'safe', ['tmdb_id' => 129, 'year' => 2001]);

        $found = $this->repo->findDuplicate(129, 'Titre totalement différent', null);

        $this->assertNotNull($found);
        $this->assertSame('Le Voyage de Chihiro', $found['title']);
        $this->assertSame('pool', $found['status']);
    }

    public function testFindDuplicateMatchesOnTitleIgnoringCaseAndAccents(): void
    {
        $this->addAdult('Amélie', 'safe', ['year' => 2001]);

        $this->assertNotNull($this->repo->findDuplicate(null, 'amelie', 2001));
        $this->assertNotNull($this->repo->findDuplicate(null, 'AMÉLIE', 2001));
        $this->assertNull($this->repo->findDuplicate(null, 'Amélie', 1999));
    }

    public function testFindDuplicateReportsAWatchedMovieWithItsSeanceDate(): void
    {
        $id = $this->addAdult('Alien', 'safe');
        $this->repo->markWatched($id);
        $this->db->prepare(
            "INSERT INTO seances (date, chooser_side, status, movie_id) VALUES (?, 'adult', 'done', ?)"
        )->execute(['2026-07-11', $id]);

        $found = $this->repo->findDuplicate(null, 'Alien', null);

        $this->assertSame('watched', $found['status']);
        $this->assertSame('2026-07-11', $found['watched_on']);
    }

    public function testFindDuplicateReturnsNullWhenNothingMatches(): void
    {
        $this->addAdult('Brazil', 'discovery');

        $this->assertNull($this->repo->findDuplicate(null, 'Solaris', null));
    }

    public function testMarkWatchedAndReturnToPool(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->repo->markWatched($id);
        $this->assertSame('watched', $this->repo->find($id)['status']);

        $this->repo->returnToPool($id);
        $this->assertSame('pool', $this->repo->find($id)['status']);
    }

    public function testCountByBetTypeOnlyCountsTheAdultPoolInPoolStatus(): void
    {
        $this->addAdult('Sûr 1', 'safe');
        $this->addAdult('Sûr 2', 'safe');
        $this->addAdult('Découverte', 'discovery');
        $vu = $this->addAdult('Déjà vu', 'safe');
        $this->repo->markWatched($vu);
        $this->repo->add(['title' => 'Film de filles', 'pool' => 'kid', 'added_by' => $this->zoe]);

        $this->assertSame(['safe' => 2, 'discovery' => 1], $this->repo->countByBetType());
    }

    public function testCountByBetTypeReturnsZeroesOnEmptyPool(): void
    {
        $this->assertSame(['safe' => 0, 'discovery' => 0], $this->repo->countByBetType());
    }

    public function testAddRejectsAnUnknownPool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->add(['title' => 'X', 'pool' => 'grand-parents', 'added_by' => $this->jc]);
    }

    public function testAddRejectsAnEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->add(['title' => '   ', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc]);
    }

    public function testStaleProvidersReturnsNeverFetchedAndOutdatedOnes(): void
    {
        $jamais = $this->addAdult('Jamais interrogé', 'safe', ['tmdb_id' => 1]);
        $vieux = $this->addAdult('Cache périmé', 'discovery', [
            'tmdb_id' => 2, 'providers' => '["Netflix"]',
            'providers_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
        ]);
        $this->addAdult('Cache frais', 'discovery', [
            'tmdb_id' => 3, 'providers' => '["Max"]',
            'providers_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);
        $this->addAdult('Saisi à la main', 'safe');

        $stale = $this->repo->staleProviders(7);

        $ids = array_column($stale, 'id');
        $this->assertContains($jamais, $ids);
        $this->assertContains($vieux, $ids);
        $this->assertCount(2, $ids, 'Le cache frais et le film sans tmdb_id sont exclus');
    }

    public function testStaleProvidersIgnoresWatchedMoviesAndHonoursTheLimit(): void
    {
        $vu = $this->addAdult('Déjà vu', 'safe', ['tmdb_id' => 10]);
        $this->repo->markWatched($vu);
        $this->addAdult('A', 'safe', ['tmdb_id' => 11]);
        $this->addAdult('B', 'discovery', ['tmdb_id' => 12]);

        $this->assertNotContains($vu, array_column($this->repo->staleProviders(7), 'id'));
        $this->assertCount(1, $this->repo->staleProviders(7, 1));
    }

    public function testUpdateProvidersStampsTheFetchDate(): void
    {
        $id = $this->addAdult('Brazil', 'discovery', ['tmdb_id' => 20]);

        $this->repo->updateProviders($id, '["Netflix","Max"]', 'Tous publics');
        $movie = $this->repo->find($id);

        $this->assertSame(['Netflix', 'Max'], json_decode($movie['providers'], true));
        $this->assertSame('Tous publics', $movie['certification']);
        $this->assertNotNull($movie['providers_at']);
        $this->assertSame([], $this->repo->staleProviders(7));
    }

    public function testUpdateProvidersAlsoCarriesTheTrailer(): void
    {
        $id = $this->addAdult('Brazil', 'discovery', ['tmdb_id' => 20]);

        $this->repo->updateProviders(
            $id,
            '["Netflix"]',
            'Tous publics',
            'https://www.youtube.com/watch?v=abc123'
        );

        $this->assertSame(
            'https://www.youtube.com/watch?v=abc123',
            $this->repo->find($id)['trailer_url']
        );
    }

    public function testUpdateProvidersKeepsTheExistingTrailerWhenNoneIsGiven(): void
    {
        $id = $this->addAdult('Brazil', 'discovery', ['tmdb_id' => 20]);
        $this->repo->updateProviders($id, '["Netflix"]', 'Tous publics', 'https://www.youtube.com/watch?v=abc123');

        // Un rafraîchissement ultérieur qui ne retrouve pas de bande-annonce
        // (film retiré de TMDb, panne partielle) ne doit pas effacer celle déjà connue.
        $this->repo->updateProviders($id, '["Netflix","Max"]', null, null);

        $this->assertSame(
            'https://www.youtube.com/watch?v=abc123',
            $this->repo->find($id)['trailer_url']
        );
    }

    public function testArchiveHidesAMovieFromItsPoolAndArchivedListShowsIt(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->repo->archive($id);

        $this->assertSame('archived', $this->repo->find($id)['status']);
        $this->assertSame([], array_column($this->repo->pool('adult'), 'id'));
        $this->assertSame(['Brazil'], array_column($this->repo->archivedList('adult'), 'title'));
    }

    public function testArchivedListOnlyReturnsItsOwnPool(): void
    {
        $adultId = $this->addAdult('Brazil', 'discovery');
        $kidId = $this->repo->add(['title' => 'Film de filles', 'pool' => 'kid', 'added_by' => $this->zoe]);
        $this->repo->archive($adultId);
        $this->repo->archive($kidId);

        $this->assertSame(['Brazil'], array_column($this->repo->archivedList('adult'), 'title'));
        $this->assertSame(['Film de filles'], array_column($this->repo->archivedList('kid'), 'title'));
    }

    public function testUnarchiveReturnsTheMovieToItsPool(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');
        $this->repo->archive($id);

        $this->repo->unarchive($id);

        $this->assertSame('pool', $this->repo->find($id)['status']);
        $this->assertSame(['Brazil'], array_column($this->repo->pool('adult'), 'title'));
    }

    public function testUpdateClassificationChangesPoolAndBetType(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->repo->updateClassification($id, 'adult', 'safe');

        $movie = $this->repo->find($id);
        $this->assertSame('adult', $movie['pool']);
        $this->assertSame('safe', $movie['bet_type']);
    }

    public function testMovingAFilmToTheKidPoolSilentlyDropsItsBetType(): void
    {
        // Le formulaire de la fiche renvoie le pari coche meme quand on choisit la
        // liste des filles. Refuser le deplacement pour cette raison serait une
        // erreur incomprehensible : le pari ne sert qu'au tirage des parents.
        $id = $this->addAdult('Un film qui change de camp', 'safe');

        $this->repo->updateClassification($id, 'kid', 'safe');
        $movie = $this->repo->find($id);

        $this->assertSame('kid', $movie['pool']);
        $this->assertNull($movie['bet_type'], 'Le pari doit disparaitre, sans erreur');
    }

    public function testMovingBackToTheAdultPoolRequiresABetTypeAgain(): void
    {
        $id = $this->addAdult('Un film qui revient', 'discovery');
        $this->repo->updateClassification($id, 'kid', null);

        // Ici l'erreur est legitime : sans pari, le tirage ne peut pas servir son quota.
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->updateClassification($id, 'adult', null);
    }

    public function testMovingBackToTheAdultPoolWithABetTypeWorks(): void
    {
        $id = $this->addAdult('Un aller-retour complet', 'safe');
        $this->repo->updateClassification($id, 'kid', 'safe');

        $this->repo->updateClassification($id, 'adult', 'discovery');
        $movie = $this->repo->find($id);

        $this->assertSame('adult', $movie['pool']);
        $this->assertSame('discovery', $movie['bet_type']);
    }

    public function testUpdateClassificationRejectsTheAdultPoolWithoutABetType(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->updateClassification($id, 'adult', null);
    }

    public function testUpdateClassificationRejectsAnUnknownPool(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->updateClassification($id, 'grand-parents', 'safe');
    }

    public function testUpdateClassificationAcceptsTheKidPoolWithoutABetType(): void
    {
        $id = $this->repo->add(['title' => 'Film de filles', 'pool' => 'kid', 'added_by' => $this->zoe]);

        $this->repo->updateClassification($id, 'kid', null);

        $this->assertNull($this->repo->find($id)['bet_type']);
    }

    public function testUpdateClassificationCanChangeTheProposer(): void
    {
        // Cas réel : JC ajoute un film pour Zoé, il doit pouvoir apparaître
        // proposé par elle plutôt que par lui-même.
        $id = $this->addAdult('Brazil', 'discovery');

        $this->repo->updateClassification($id, 'adult', 'discovery', $this->zoe);

        $this->assertSame($this->zoe, (int) $this->repo->find($id)['added_by']);
    }

    public function testUpdateClassificationWithoutAProposerLeavesItUntouched(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->repo->updateClassification($id, 'adult', 'safe');

        $this->assertSame($this->jc, (int) $this->repo->find($id)['added_by']);
    }

    public function testUpdateClassificationRejectsAnUnknownProposer(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->updateClassification($id, 'adult', 'discovery', 999);
    }

    public function testProviderBrandsReturnsDistinctBrandsAcrossAllMovies(): void
    {
        $this->addAdult('Brazil', 'discovery', ['providers' => '["Netflix","Netflix Standard with Ads"]']);
        $this->addAdult('Alien', 'safe', ['providers' => '[{"id":384,"name":"HBO Max","logo":"/hbo.jpg"}]']);

        $brands = $this->repo->providerBrands();

        $this->assertSame(['HBO Max', 'Netflix'], array_column($brands, 'brand'));
    }

    public function testProviderBrandsExcludesArchivedMovies(): void
    {
        $id = $this->addAdult('Brazil', 'discovery', ['providers' => '["Netflix"]']);
        $this->repo->archive($id);

        $this->assertSame([], $this->repo->providerBrands());
    }

    public function testAddDefaultsKindToFilmWithZeroProgressAndTwoEpisodesPerEvening(): void
    {
        $id = $this->addAdult('Brazil', 'discovery');

        $movie = $this->repo->find($id);

        $this->assertSame('film', $movie['kind']);
        $this->assertSame(0, (int) $movie['episodes_watched']);
        $this->assertSame(2, (int) $movie['episodes_per_evening']);
        $this->assertNull($movie['episodes']);
    }

    public function testAddSeriesStoresKindAndEpisodeFieldsAndForcesNoBetType(): void
    {
        $episodes = [
            ['number' => 1, 'season' => 1, 'episode_in_season' => 1, 'title' => 'Crush', 'runtime' => 27],
            ['number' => 2, 'season' => 1, 'episode_in_season' => 2, 'title' => 'Lucky', 'runtime' => 33],
        ];

        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'bet_type' => 'safe', // ignoré : une série n'a jamais de pari
            'added_by' => $this->zoe,
            'season_count' => 3,
            'episode_count' => 24,
            'episodes' => $episodes,
        ]);

        $movie = $this->repo->find($id);

        $this->assertSame('series', $movie['kind']);
        $this->assertSame(3, (int) $movie['season_count']);
        $this->assertSame(24, (int) $movie['episode_count']);
        $this->assertSame(0, (int) $movie['episodes_watched']);
        $this->assertSame(2, (int) $movie['episodes_per_evening']);
        $this->assertNull($movie['bet_type']);
        $this->assertSame($episodes, json_decode($movie['episodes'], true));
    }

    public function testAddSeriesAcceptsAnAlreadyEncodedEpisodesJson(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episodes' => '[{"number":1,"season":1,"episode_in_season":1,"title":"Crush","runtime":27}]',
        ]);

        $episodes = json_decode($this->repo->find($id)['episodes'], true);
        $this->assertCount(1, $episodes);
        $this->assertSame('Crush', $episodes[0]['title']);
    }

    public function testAddSeriesHonoursACustomEpisodesPerEvening(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episodes_per_evening' => 3,
            'episodes' => [],
        ]);

        $this->assertSame(3, (int) $this->repo->find($id)['episodes_per_evening']);
    }

    public function testAdvanceSeriesIncrementsProgressAndStaysInPool(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episode_count' => 24,
            'episodes' => [],
        ]);

        $this->repo->advanceSeries($id, 2);

        $movie = $this->repo->find($id);
        $this->assertSame(2, (int) $movie['episodes_watched']);
        $this->assertSame('pool', $movie['status'], 'La serie en cours reste au pool');
    }

    public function testAdvanceSeriesMarksWatchedOnceTheLastEpisodeIsReached(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episode_count' => 24,
            'episodes' => [],
        ]);

        $this->repo->advanceSeries($id, 24);

        $movie = $this->repo->find($id);
        $this->assertSame(24, (int) $movie['episodes_watched']);
        $this->assertSame('watched', $movie['status']);
    }

    public function testSetEpisodesPerEveningUpdatesTheValue(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episodes' => [],
        ]);

        $this->repo->setEpisodesPerEvening($id, 3);

        $this->assertSame(3, (int) $this->repo->find($id)['episodes_per_evening']);
    }

    public function testSetEpisodesPerEveningRejectsLessThanOne(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episodes' => [],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->setEpisodesPerEvening($id, 0);
    }

    public function testUpdateClassificationNeverRequiresABetForASeriesMovingToTheAdultPool(): void
    {
        // Ecran mouvant.php : reclasser une serie vers la liste des parents ne
        // doit jamais etre bloque par l'exigence de pari, une serie n'en ayant
        // jamais (elle ne sort jamais au tirage).
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episodes' => [],
        ]);

        $this->repo->updateClassification($id, 'adult', null);

        $movie = $this->repo->find($id);
        $this->assertSame('adult', $movie['pool']);
        $this->assertNull($movie['bet_type']);
    }

    public function testSeriesInProgressReturnsNullWhenNoSeriesHasStarted(): void
    {
        $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episode_count' => 24,
            'episodes' => [],
        ]);

        $this->assertNull($this->repo->seriesInProgress('kid'));
    }

    public function testSeriesInProgressFindsAStartedSeriesInItsOwnPoolOnly(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episode_count' => 24,
            'episodes' => [],
        ]);
        $this->repo->advanceSeries($id, 2);

        $found = $this->repo->seriesInProgress('kid');
        $this->assertNotNull($found);
        $this->assertSame($id, (int) $found['id']);
        $this->assertNull($this->repo->seriesInProgress('adult'));
    }

    public function testSeriesInProgressIgnoresAFinishedSeries(): void
    {
        $id = $this->repo->addSeries([
            'title' => 'Heartstopper',
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episode_count' => 24,
            'episodes' => [],
        ]);
        $this->repo->advanceSeries($id, 24);

        $this->assertNull($this->repo->seriesInProgress('kid'));
    }

    public function testAddCapsTitleAndMemoLengths(): void
    {
        $id = $this->repo->add([
            'title' => str_repeat('a', 400),
            'memo' => str_repeat('é', 900),
            'pool' => 'adult',
            'bet_type' => 'safe',
            'added_by' => $this->jc,
        ]);

        $film = $this->repo->find($id);

        $this->assertSame(200, mb_strlen($film['title']));
        $this->assertSame(500, mb_strlen($film['memo']));
    }
}
