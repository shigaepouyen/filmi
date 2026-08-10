<?php
namespace App\Tests\Services;

use App\Services\AwardsService;
use PHPUnit\Framework\TestCase;

class AwardsServiceTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function history(): array
    {
        return [
            ['date' => '2026-08-08', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => 'Brazil', 'movie_year' => 1985, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => 4.5, 'veto_count' => 0],
            ['date' => '2026-08-01', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 1,
             'movie_title' => 'Un film de filles', 'movie_year' => 2023, 'movie_poster' => null,
             'proposer_name' => 'Zoé', 'avg_score' => 4.5, 'veto_count' => 1],
            ['date' => '2026-07-25', 'status' => 'skipped', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => null, 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => null, 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-07-18', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => 'Solaris', 'movie_year' => 1972, 'movie_poster' => null,
             'proposer_name' => 'Élodie', 'avg_score' => 2.0, 'veto_count' => 0],
            ['date' => '2026-07-11', 'status' => 'planned', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_title' => null, 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => null, 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2025-12-20', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => 'Un vieux film', 'movie_year' => 1954, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => 5.0, 'veto_count' => 0],
        ];
    }

    private function vetoes(): array
    {
        return [['name' => 'JC', 'total' => 1]];
    }

    public function testCountsOnlyDoneSeancesAsWatched(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), 2026);

        $this->assertSame(2026, $result['year']);
        $this->assertSame(3, $result['watched']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['derogations']);
    }

    public function testAverageIgnoresUnratedSeances(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), 2026);

        // (4.5 + 4.5 + 2.0) / 3
        $this->assertSame(3.67, $result['avg_score']);
        $this->assertSame(3, $result['rated']);
    }

    public function testBestAndWorstAreIdentified(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), 2026);

        $this->assertSame('Brazil', $result['best']['movie_title'], 'Égalité tranchée par la date la plus récente');
        $this->assertSame('Solaris', $result['worst']['movie_title']);
    }

    public function testProposerTallyCountsOnlyRetainedMovies(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), 2026);

        $this->assertSame(['JC' => 1, 'Zoé' => 1, 'Élodie' => 1], $result['by_proposer']);
    }

    public function testSideTally(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), 2026);

        $this->assertSame(['adult' => 2, 'kid' => 1], $result['by_side']);
    }

    public function testYearFilterExcludesOtherYears(): void
    {
        $onlyPrevious = AwardsService::compute($this->history(), $this->vetoes(), 2025);

        $this->assertSame(1, $onlyPrevious['watched']);
        $this->assertSame('Un vieux film', $onlyPrevious['best']['movie_title']);
    }

    public function testNullYearCoversEverything(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), null);

        $this->assertNull($result['year']);
        $this->assertSame(4, $result['watched']);
        $this->assertSame('Un vieux film', $result['best']['movie_title']);
    }

    public function testEmptyHistoryReturnsNeutralValues(): void
    {
        $result = AwardsService::compute([], [], 2026);

        $this->assertSame(0, $result['watched']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNull($result['avg_score']);
        $this->assertNull($result['best']);
        $this->assertNull($result['worst']);
        $this->assertSame([], $result['by_proposer']);
        $this->assertSame(['adult' => 0, 'kid' => 0], $result['by_side']);
    }

    public function testVetoesArePassedThrough(): void
    {
        $result = AwardsService::compute($this->history(), $this->vetoes(), 2026);

        $this->assertSame([['name' => 'JC', 'total' => 1]], $result['vetoes']);
    }

    public function testProposerTallyIsSortedDescending(): void
    {
        $history = [
            ['date' => '2026-08-08', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => 'A', 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => 'Élodie', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-08-01', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => 'B', 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-07-25', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_title' => 'C', 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => null, 'veto_count' => 0],
        ];

        $this->assertSame(['JC' => 2, 'Élodie' => 1], AwardsService::compute($history, [], 2026)['by_proposer']);
    }
}
