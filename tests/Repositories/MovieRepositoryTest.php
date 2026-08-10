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
        $this->assertSame('detective', $row['proposer_avatar']);
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
}
