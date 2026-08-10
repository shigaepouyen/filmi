<?php
namespace App\Tests\Repositories;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\VoteRepository;
use App\Tests\Support\DbTestCase;

class VoteRepositoryTest extends DbTestCase
{
    private VoteRepository $votes;
    private int $movieId;
    private int $otherMovieId;
    private int $jc;
    private int $zoe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $profiles = new ProfileRepository($this->db);
        $this->jc = $profiles->findBySlug('jc')['id'];
        $this->zoe = $profiles->findBySlug('zoe')['id'];

        $movies = new MovieRepository($this->db);
        $this->movieId = $movies->add([
            'title' => 'Brazil', 'pool' => 'adult', 'bet_type' => 'discovery', 'added_by' => $this->jc,
        ]);
        $this->otherMovieId = $movies->add([
            'title' => 'Alien', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);

        $this->votes = new VoteRepository($this->db);
    }

    public function testFirstToggleAddsTheVote(): void
    {
        $result = $this->votes->toggle($this->movieId, $this->jc);

        $this->assertTrue($result['voted']);
        $this->assertSame(1, $result['count']);
        $this->assertTrue($this->votes->hasVoted($this->movieId, $this->jc));
    }

    public function testSecondToggleRemovesIt(): void
    {
        $this->votes->toggle($this->movieId, $this->jc);
        $result = $this->votes->toggle($this->movieId, $this->jc);

        $this->assertFalse($result['voted']);
        $this->assertSame(0, $result['count']);
        $this->assertFalse($this->votes->hasVoted($this->movieId, $this->jc));
    }

    public function testVotesFromDifferentProfilesAccumulate(): void
    {
        $this->votes->toggle($this->movieId, $this->jc);
        $result = $this->votes->toggle($this->movieId, $this->zoe);

        $this->assertSame(2, $result['count']);
    }

    public function testOneProfileCannotVoteTwiceForTheSameMovie(): void
    {
        $this->votes->toggle($this->movieId, $this->jc);
        $this->votes->toggle($this->movieId, $this->jc);
        $this->votes->toggle($this->movieId, $this->jc);

        $this->assertSame(1, $this->votes->count($this->movieId));
    }

    public function testVotedMovieIdsListsOnlyThatProfileVotes(): void
    {
        $this->votes->toggle($this->movieId, $this->jc);
        $this->votes->toggle($this->otherMovieId, $this->zoe);

        $this->assertSame([$this->movieId], $this->votes->votedMovieIds($this->jc));
        $this->assertSame([$this->otherMovieId], $this->votes->votedMovieIds($this->zoe));
    }

    public function testDeletingAMovieRemovesItsVotes(): void
    {
        $this->votes->toggle($this->movieId, $this->jc);
        $this->db->prepare('DELETE FROM movies WHERE id = ?')->execute([$this->movieId]);

        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM votes')->fetchColumn());
    }
}
