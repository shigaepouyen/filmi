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
             'movie_id' => 1, 'movie_title' => 'Brazil', 'movie_year' => 1985, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => 4.5, 'veto_count' => 0],
            ['date' => '2026-08-01', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 1,
             'movie_id' => 2, 'movie_title' => 'Un film de filles', 'movie_year' => 2023, 'movie_poster' => null,
             'proposer_name' => 'Zoé', 'avg_score' => 4.5, 'veto_count' => 1],
            ['date' => '2026-07-25', 'status' => 'skipped', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => null, 'movie_title' => null, 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => null, 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-07-18', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 3, 'movie_title' => 'Solaris', 'movie_year' => 1972, 'movie_poster' => null,
             'proposer_name' => 'Élodie', 'avg_score' => 2.0, 'veto_count' => 0],
            ['date' => '2026-07-11', 'status' => 'planned', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_id' => null, 'movie_title' => null, 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => null, 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2025-12-20', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 4, 'movie_title' => 'Un vieux film', 'movie_year' => 1954, 'movie_poster' => null,
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

    public function testResolveSelectionKeepsTheRequestedYearWhenHistoryExists(): void
    {
        $result = AwardsService::resolveSelection([2026, 2025], '2026');

        $this->assertSame(2026, $result['year']);
        $this->assertSame('2026', $result['selected']);
    }

    public function testResolveSelectionHonoursAnExplicitAllRequest(): void
    {
        $result = AwardsService::resolveSelection([2026, 2025], 'all');

        $this->assertNull($result['year']);
        $this->assertSame('all', $result['selected']);
    }

    public function testResolveSelectionFallsBackToAllWhenThereIsNoHistory(): void
    {
        // Sans aucune séance, le sélecteur ne propose que « Tout l'historique » :
        // le titre affiché doit correspondre, pas rester sur l'année du jour.
        $result = AwardsService::resolveSelection([], '2026');

        $this->assertNull($result['year']);
        $this->assertSame('all', $result['selected']);
    }

    public function testProposerTallyIsSortedDescending(): void
    {
        $history = [
            ['date' => '2026-08-08', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 10, 'movie_title' => 'A', 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => 'Élodie', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-08-01', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 11, 'movie_title' => 'B', 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-07-25', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 12, 'movie_title' => 'C', 'movie_year' => null, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => null, 'veto_count' => 0],
        ];

        $this->assertSame(['JC' => 2, 'Élodie' => 1], AwardsService::compute($history, [], 2026)['by_proposer']);
    }

    public function testASeriesWatchedOverSeveralSaturdaysCountsAsOneWork(): void
    {
        // Douze samedis d'une même série ne doivent pas afficher douze œuvres
        // vues : la note finale (portée par la ligne la plus récente) est celle
        // qui compte pour la série entière.
        $history = [
            ['date' => '2026-08-15', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_id' => 42, 'movie_title' => 'Heartstopper', 'movie_year' => 2022, 'movie_poster' => null,
             'proposer_name' => 'Zoé', 'avg_score' => 4.5, 'veto_count' => 0],
            ['date' => '2026-08-08', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_id' => 42, 'movie_title' => 'Heartstopper', 'movie_year' => 2022, 'movie_poster' => null,
             'proposer_name' => 'Zoé', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-08-01', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_id' => 42, 'movie_title' => 'Heartstopper', 'movie_year' => 2022, 'movie_poster' => null,
             'proposer_name' => 'Zoé', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-07-25', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 7, 'movie_title' => 'Brazil', 'movie_year' => 1985, 'movie_poster' => null,
             'proposer_name' => 'JC', 'avg_score' => 4.0, 'veto_count' => 0],
        ];

        $result = AwardsService::compute($history, [], 2026);

        $this->assertSame(2, $result['watched'], 'Trois samedis de série ne comptent que pour une œuvre');
        $this->assertSame(['Zoé' => 1, 'JC' => 1], $result['by_proposer']);
        $this->assertSame(['adult' => 1, 'kid' => 1], $result['by_side']);
        $this->assertSame(4.25, $result['avg_score'], '(4.5 + 4.0) / 2, la note de la serie est celle du dernier episode');
        $this->assertSame('Heartstopper', $result['best']['movie_title']);
    }

    private function profiles(): array
    {
        return [
            ['id' => 1, 'name' => 'JC', 'avatar' => 'detective', 'color' => 'slate', 'side' => 'adult'],
            ['id' => 2, 'name' => 'Élodie', 'avatar' => 'aviatrice', 'color' => 'rose', 'side' => 'adult'],
            ['id' => 3, 'name' => 'Zoé', 'avatar' => 'idole', 'color' => 'violet', 'side' => 'kid'],
            ['id' => 4, 'name' => 'Soline', 'avatar' => 'dinosaure', 'color' => 'emerald', 'side' => 'kid'],
        ];
    }

    /**
     * Historique mêlant un film et une série sur trois soirées, pour prouver que
     * le compte des œuvres distinctes et le temps de visionnage sont tous les
     * deux corrects sur ce même jeu de données : la série ne doit compter que
     * pour une œuvre, mais chacune de ses trois soirées doit apporter ses
     * propres minutes.
     */
    private function historyWithSeries(): array
    {
        $seriesEpisodes = json_encode([
            ['number' => 1, 'season' => 1, 'episode_in_season' => 1, 'title' => 'E1', 'runtime' => 25],
            ['number' => 2, 'season' => 1, 'episode_in_season' => 2, 'title' => 'E2', 'runtime' => 28],
            ['number' => 3, 'season' => 1, 'episode_in_season' => 3, 'title' => 'E3', 'runtime' => 24],
            ['number' => 4, 'season' => 1, 'episode_in_season' => 4, 'title' => 'E4', 'runtime' => 30],
        ]);

        return [
            ['date' => '2026-08-15', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_id' => 42, 'movie_title' => 'Heartstopper', 'movie_year' => 2022, 'movie_poster' => 'p42.jpg',
             'movie_runtime' => null, 'movie_kind' => 'series', 'movie_episodes' => $seriesEpisodes,
             'episodes_from' => 3, 'episodes_to' => 4,
             'proposer_id' => 3, 'proposer_name' => 'Zoé', 'avg_score' => 4.5, 'veto_count' => 0],
            ['date' => '2026-08-08', 'status' => 'done', 'chooser_side' => 'kid', 'derogation' => 0,
             'movie_id' => 42, 'movie_title' => 'Heartstopper', 'movie_year' => 2022, 'movie_poster' => 'p42.jpg',
             'movie_runtime' => null, 'movie_kind' => 'series', 'movie_episodes' => $seriesEpisodes,
             'episodes_from' => 1, 'episodes_to' => 2,
             'proposer_id' => 3, 'proposer_name' => 'Zoé', 'avg_score' => null, 'veto_count' => 0],
            ['date' => '2026-07-25', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 7, 'movie_title' => 'Brazil', 'movie_year' => 1985, 'movie_poster' => 'p7.jpg',
             'movie_runtime' => 132, 'movie_kind' => 'film', 'movie_episodes' => null,
             'episodes_from' => null, 'episodes_to' => null,
             'proposer_id' => 1, 'proposer_name' => 'JC', 'avg_score' => 2.0, 'veto_count' => 0],
            ['date' => '2026-07-18', 'status' => 'done', 'chooser_side' => 'adult', 'derogation' => 0,
             'movie_id' => 9, 'movie_title' => 'Le Labyrinthe de Pan', 'movie_year' => 2006, 'movie_poster' => 'p9.jpg',
             'movie_runtime' => 118, 'movie_kind' => 'film', 'movie_episodes' => null,
             'episodes_from' => null, 'episodes_to' => null,
             'proposer_id' => 2, 'proposer_name' => 'Élodie', 'avg_score' => 5.0, 'veto_count' => 0],
        ];
    }

    public function testTotalMinutesCountsEachSeriesEveningSeparatelyNotTheWholeSeriesEachTime(): void
    {
        $result = AwardsService::compute($this->historyWithSeries(), [], 2026);

        // 25+28 (première soirée) + 24+30 (deuxième soirée) + 132 (Brazil) + 118 (Pan)
        $this->assertSame(357, $result['total_minutes']);
        $this->assertSame(3, $result['watched'], 'Toujours trois œuvres distinctes malgré les deux soirées de série');
    }

    public function testPodiumListsUpToThreeRatedWorksBestFirst(): void
    {
        $result = AwardsService::compute($this->historyWithSeries(), [], 2026);

        $this->assertSame(
            ['Le Labyrinthe de Pan', 'Heartstopper', 'Brazil'],
            array_column($result['podium'], 'movie_title')
        );
    }

    public function testLongestAndShortestConsiderTheFullDurationOfAWork(): void
    {
        $result = AwardsService::compute($this->historyWithSeries(), [], 2026);

        // La série pèse 25+28+24+30 = 107 minutes au total, moins que les deux films.
        $this->assertSame('Heartstopper', $result['shortest']['movie_title']);
        $this->assertSame('Brazil', $result['longest']['movie_title']);
    }

    public function testOldestNewestAndTopDecadeAreComputedFromDistinctWorks(): void
    {
        $result = AwardsService::compute($this->historyWithSeries(), [], 2026);

        $this->assertSame(1985, (int) $result['oldest']['movie_year'], 'Brazil, 1985, est la plus ancienne des trois œuvres distinctes');
        $this->assertSame(2022, (int) $result['newest']['movie_year']);
        // Trois œuvres, trois décennies différentes (1980, 2000, 2020) : chacune
        // pèse une seule œuvre, l'égalité est départagée par la plus récente.
        $this->assertSame(['decade' => 2020, 'count' => 1], $result['top_decade']);
    }

    public function testByPersonAggregatesProposalsRatingsAndVetoesPerProfile(): void
    {
        $ratings = [
            ['date' => '2026-08-15', 'profile_id' => 1, 'name' => 'JC', 'avatar' => 'detective', 'color' => 'slate', 'score' => 3],
            ['date' => '2026-08-15', 'profile_id' => 3, 'name' => 'Zoé', 'avatar' => 'idole', 'color' => 'violet', 'score' => 5],
            ['date' => '2026-07-25', 'profile_id' => 1, 'name' => 'JC', 'avatar' => 'detective', 'color' => 'slate', 'score' => 2],
        ];
        $vetoCounts = [['profile_id' => 1, 'name' => 'JC', 'total' => 2]];

        $result = AwardsService::compute($this->historyWithSeries(), $vetoCounts, 2026, $ratings, $this->profiles());

        $byName = [];
        foreach ($result['by_person'] as $person) {
            $byName[$person['name']] = $person;
        }

        $this->assertSame(1, $byName['Zoé']['proposed'], 'La série ne compte que pour une œuvre malgré ses deux soirées');
        $this->assertSame(1, $byName['JC']['proposed']);
        $this->assertSame(1, $byName['Élodie']['proposed']);
        $this->assertSame(0, $byName['Soline']['proposed'], 'Un profil sans activité reste présent, à zéro');

        $this->assertSame(2.5, $byName['JC']['avg_given'], '(3 + 2) / 2');
        $this->assertSame(5.0, $byName['Zoé']['avg_given']);
        $this->assertNull($byName['Soline']['avg_given'], 'Aucune note donnée par Soline');

        $this->assertSame(2, $byName['JC']['veto_count']);
        $this->assertSame(0, $byName['Zoé']['veto_count']);

        $this->assertSame('Zoé', $result['most_generous']['name']);
        $this->assertSame('JC', $result['toughest']['name']);
    }

    public function testEmptyHistoryReturnsNeutralValuesForTheNewAggregatesToo(): void
    {
        $result = AwardsService::compute([], [], 2026, [], $this->profiles());

        $this->assertSame([], $result['poster_wall']);
        $this->assertSame([], $result['podium']);
        $this->assertSame(0, $result['total_minutes']);
        $this->assertNull($result['longest']);
        $this->assertNull($result['shortest']);
        $this->assertNull($result['oldest']);
        $this->assertNull($result['newest']);
        $this->assertNull($result['top_decade']);
        $this->assertNull($result['most_generous']);
        $this->assertNull($result['toughest']);
        $this->assertCount(4, $result['by_person'], 'Les quatre profils restent présents, tous à zéro');
        foreach ($result['by_person'] as $person) {
            $this->assertSame(0, $person['proposed']);
            $this->assertNull($person['avg_given']);
        }
    }
}
