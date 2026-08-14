<?php
namespace App\Tests\Repositories;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SeanceRepository;
use App\Tests\Support\DbTestCase;

class SeanceRepositoryTest extends DbTestCase
{
    private SeanceRepository $repo;
    private MovieRepository $movies;
    private int $jc;
    private int $elodie;
    private int $zoe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $profiles = new ProfileRepository($this->db);
        $this->jc = $profiles->findBySlug('jc')['id'];
        $this->elodie = $profiles->findBySlug('elodie')['id'];
        $this->zoe = $profiles->findBySlug('zoe')['id'];

        $this->movies = new MovieRepository($this->db);
        $this->repo = new SeanceRepository($this->db, $this->movies);
    }

    private function movie(string $title, string $betType = 'discovery'): int
    {
        return $this->movies->add([
            'title' => $title, 'pool' => 'adult', 'bet_type' => $betType, 'added_by' => $this->jc,
        ]);
    }

    public function testEnsureCreatesAPlannedSeanceOnce(): void
    {
        $first = $this->repo->ensure('2026-08-15', 'adult');
        $second = $this->repo->ensure('2026-08-15', 'kid');

        $this->assertSame('planned', $first['status']);
        $this->assertSame('adult', $first['chooser_side']);
        $this->assertSame($first['id'], $second['id'], 'Une seule séance par date');
        $this->assertSame('adult', $second['chooser_side'], 'Le camp existant ne doit pas être écrasé');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM seances')->fetchColumn());
    }

    public function testSetChooserSideRecordsTheDerogationAndItsNote(): void
    {
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->repo->setChooserSide($seance['id'], 'kid', true, 'Bulletin de Soline');
        $updated = $this->repo->findByDate('2026-08-15');

        $this->assertSame('kid', $updated['chooser_side']);
        $this->assertSame(1, (int) $updated['derogation']);
        $this->assertSame('Bulletin de Soline', $updated['derogation_note']);
    }

    public function testSkipMarksTheSeanceAndConsumesNoMovie(): void
    {
        $movieId = $this->movie('Brazil');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->repo->skip($seance['id']);

        $this->assertSame('skipped', $this->repo->findByDate('2026-08-15')['status']);
        $this->assertSame('pool', $this->movies->find($movieId)['status']);
    }

    public function testEnsureIsIdempotentWhenTheRowAppearsConcurrently(): void
    {
        // Simule l'appareil concurrent : la ligne est insérée entre le SELECT et l'INSERT.
        $this->db->prepare(
            "INSERT INTO seances (date, chooser_side, status) VALUES (?, 'kid', 'planned')"
        )->execute(['2026-08-15']);

        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->assertSame('kid', $seance['chooser_side'], 'Le camp existant ne doit pas être écrasé');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM seances')->fetchColumn());
    }

    public function testTheDateColumnRefusesADuplicate(): void
    {
        $this->repo->ensure('2026-08-15', 'adult');

        $this->expectException(\PDOException::class);
        $this->db->prepare(
            "INSERT INTO seances (date, chooser_side, status) VALUES (?, 'kid', 'planned')"
        )->execute(['2026-08-15']);
    }

    public function testUnskipBringsTheSeanceBackToPlanned(): void
    {
        $seance = $this->repo->ensure('2026-08-15', 'adult');
        $this->repo->skip($seance['id']);

        $this->repo->unskip($seance['id']);

        $this->assertSame('planned', $this->repo->findByDate('2026-08-15')['status']);
    }

    public function testSkipLeavesADoneSeanceAlone(): void
    {
        $movieId = $this->movie('Brazil');
        $seance = $this->repo->ensure('2026-08-15', 'adult');
        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);

        $this->repo->skip($seance['id']);

        $updated = $this->repo->findByDate('2026-08-15');
        $this->assertSame('done', $updated['status']);
        $this->assertSame($movieId, (int) $updated['movie_id']);
        $this->assertSame('watched', $this->movies->find($movieId)['status']);
    }

    public function testSetChooserSideLeavesADoneSeanceAlone(): void
    {
        $movieId = $this->movie('Brazil');
        $seance = $this->repo->ensure('2026-08-15', 'adult');
        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);

        $this->repo->setChooserSide($seance['id'], 'kid', true, 'trop tard');

        $updated = $this->repo->findByDate('2026-08-15');
        $this->assertSame('adult', $updated['chooser_side'], 'Le camp ne doit pas bouger une fois la séance faite');
        $this->assertSame(0, (int) $updated['derogation']);
    }

    public function testUnskipLeavesADoneSeanceAlone(): void
    {
        $movieId = $this->movie('Brazil');
        $seance = $this->repo->ensure('2026-08-15', 'adult');
        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);

        $this->repo->unskip($seance['id']);

        $this->assertSame('done', $this->repo->findByDate('2026-08-15')['status']);
    }

    public function testRecordChoiceWritesShortlistChosenAndWatchedStatus(): void
    {
        $a = $this->movie('Sûr', 'safe');
        $b = $this->movie('Découverte 1');
        $c = $this->movie('Découverte 2');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->repo->recordChoice($seance['id'], [$a, $b, $c], $b);

        $updated = $this->repo->findByDate('2026-08-15');
        $this->assertSame('done', $updated['status']);
        $this->assertSame($b, (int) $updated['movie_id']);
        $this->assertSame('watched', $this->movies->find($b)['status']);
        $this->assertSame('pool', $this->movies->find($a)['status'], 'Les non retenus restent au pool');
        $this->assertSame('pool', $this->movies->find($c)['status']);

        $picks = $this->db->query(
            'SELECT movie_id, role FROM seance_picks ORDER BY role, movie_id'
        )->fetchAll();
        $this->assertCount(4, $picks, '3 shortlist plus 1 chosen');
        $this->assertSame(
            [$b],
            array_column(array_filter($picks, fn ($p) => $p['role'] === 'chosen'), 'movie_id')
        );
    }

    public function testRecordChoiceRefusesAChosenMovieOutsideTheShortlist(): void
    {
        $a = $this->movie('Sûr', 'safe');
        $b = $this->movie('Découverte');
        $intrus = $this->movie('Intrus');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->recordChoice($seance['id'], [$a, $b], $intrus);
    }

    public function testRecordChoiceRollsBackEntirelyOnFailure(): void
    {
        $a = $this->movie('Sûr', 'safe');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        try {
            // 999 n'existe pas : la contrainte de clé étrangère doit tout annuler.
            $this->repo->recordChoice($seance['id'], [$a, 999], $a);
        } catch (\Throwable) {
            // attendu
        }

        $this->assertSame('planned', $this->repo->findByDate('2026-08-15')['status']);
        $this->assertSame('pool', $this->movies->find($a)['status']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM seance_picks')->fetchColumn());
    }

    public function testRatingsAreClearedWhenAVetoedFilmIsReplaced(): void
    {
        $premier = $this->movie('Premier');
        $second = $this->movie('Second');
        $seance = $this->repo->ensure('2026-08-15', 'kid');
        $this->repo->recordChoice($seance['id'], [], $premier);
        $this->repo->rate($seance['id'], $this->zoe, 4);

        $this->repo->recordVeto($seance['id'], $premier, $this->jc, 'Trop dur');
        $this->repo->recordChoice($seance['id'], [], $second);

        $this->assertSame([], $this->repo->ratings($seance['id']));
    }

    public function testRatingsSurviveRecordingTheSameFilmAgain(): void
    {
        $movieId = $this->movie('Brazil');
        $seance = $this->repo->ensure('2026-08-15', 'adult');
        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);
        $this->repo->rate($seance['id'], $this->zoe, 4);

        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);

        $this->assertCount(1, $this->repo->ratings($seance['id']));
    }

    public function testRecordVetoReturnsTheMovieToThePoolAndDetachesIt(): void
    {
        $movieId = $this->movie('Un film contesté');
        $seance = $this->repo->ensure('2026-08-15', 'kid');
        $this->repo->recordChoice($seance['id'], [], $movieId);

        $this->repo->recordVeto($seance['id'], $movieId, $this->jc, 'Trop dur pour Soline');

        $this->assertSame('pool', $this->movies->find($movieId)['status']);
        $this->assertNull($this->repo->findByDate('2026-08-15')['movie_id']);

        $veto = $this->db->query(
            "SELECT * FROM seance_picks WHERE role = 'vetoed'"
        )->fetch();
        $this->assertSame($this->jc, (int) $veto['by_profile_id']);
        $this->assertSame('Trop dur pour Soline', $veto['reason']);
    }

    public function testSeveralVetoesOnTheSameSeanceAreAllKept(): void
    {
        $premier = $this->movie('Premier');
        $second = $this->movie('Second');
        $seance = $this->repo->ensure('2026-08-15', 'kid');

        $this->repo->recordVeto($seance['id'], $premier, $this->jc, 'Non');
        $this->repo->recordVeto($seance['id'], $second, $this->elodie, 'Non plus');

        $this->assertSame(
            2,
            (int) $this->db->query("SELECT COUNT(*) FROM seance_picks WHERE role = 'vetoed'")->fetchColumn()
        );
    }

    public function testVetoCountsAreGroupedByProfileDescending(): void
    {
        $seance = $this->repo->ensure('2026-08-15', 'kid');
        $this->repo->recordVeto($seance['id'], $this->movie('A'), $this->jc, null);
        $this->repo->recordVeto($seance['id'], $this->movie('B'), $this->jc, null);
        $this->repo->recordVeto($seance['id'], $this->movie('C'), $this->elodie, null);

        $counts = $this->repo->vetoCounts();

        $this->assertSame(['JC', 'Élodie'], array_column($counts, 'name'));
        $this->assertSame([2, 1], array_map('intval', array_column($counts, 'total')));
    }

    public function testRateStoresOneScorePerProfileAndOverwrites(): void
    {
        $movieId = $this->movie('Brazil');
        $seance = $this->repo->ensure('2026-08-15', 'adult');
        $this->repo->recordChoice($seance['id'], [], $movieId);

        $this->repo->rate($seance['id'], $this->zoe, 3);
        $this->repo->rate($seance['id'], $this->zoe, 5);
        $this->repo->rate($seance['id'], $this->jc, 4);

        $ratings = $this->repo->ratings($seance['id']);

        $this->assertCount(2, $ratings);
        $byName = array_column($ratings, 'score', 'name');
        $this->assertSame(5, (int) $byName['Zoé']);
        $this->assertSame(4, (int) $byName['JC']);
    }

    public function testRateRejectsAScoreOutOfRange(): void
    {
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->rate($seance['id'], $this->zoe, 6);
    }

    public function testCooldownCoversTheTwoLastSeancesThatHadAShortlist(): void
    {
        $ids = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $title) {
            $ids[$title] = $this->movie($title);
        }

        // Semaine parents la plus ancienne : shortlist A B C, A retenu.
        $s1 = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($s1['id'], [$ids['A'], $ids['B'], $ids['C']], $ids['A']);

        // Semaine filles : aucune shortlist, elles choisissent dans tout le pool.
        $s2 = $this->repo->ensure('2026-07-11', 'kid');
        $this->repo->recordChoice($s2['id'], [], $ids['G']);

        // Semaine parents suivante : shortlist D E F, D retenu.
        $s3 = $this->repo->ensure('2026-07-18', 'adult');
        $this->repo->recordChoice($s3['id'], [$ids['D'], $ids['E'], $ids['F']], $ids['D']);

        $cooldown = $this->repo->cooldownMovieIds(2);

        foreach (['B', 'C', 'E', 'F'] as $title) {
            $this->assertContains($ids[$title], $cooldown, "{$title} devrait être en cooldown");
        }
    }

    public function testCooldownIgnoresSeancesOlderThanTheWindow(): void
    {
        $vieux = $this->movie('Vieux');
        $recentA = $this->movie('Récent A');
        $recentB = $this->movie('Récent B');

        $s1 = $this->repo->ensure('2026-06-06', 'adult');
        $this->repo->recordChoice($s1['id'], [$vieux], $vieux);
        $s2 = $this->repo->ensure('2026-06-13', 'adult');
        $this->repo->recordChoice($s2['id'], [$recentA], $recentA);
        $s3 = $this->repo->ensure('2026-06-20', 'adult');
        $this->repo->recordChoice($s3['id'], [$recentB], $recentB);

        $this->assertNotContains($vieux, $this->repo->cooldownMovieIds(2));
    }

    public function testASeanceWithoutShortlistConsumesNoCooldownSlot(): void
    {
        $a = $this->movie('Parents A', 'safe');
        $b = $this->movie('Parents B');
        $c = $this->movie('Parents C');

        // Semaine parents avec vraie shortlist.
        $s1 = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($s1['id'], [$a, $b, $c], $a);

        // Deux semaines filles sans shortlist, chacune avec son propre film :
        // elles ne doivent pas chasser la semaine parents hors de la fenêtre
        // de cooldown.
        foreach (['2026-07-11', '2026-07-18'] as $index => $date) {
            $kid = $this->movie('Choix des filles ' . $index);
            $seance = $this->repo->ensure($date, 'kid');
            $this->repo->recordChoice($seance['id'], [], $kid);
        }

        $cooldown = $this->repo->cooldownMovieIds(2);

        $this->assertContains($b, $cooldown, 'La semaine parents doit rester dans la fenêtre');
        $this->assertContains($c, $cooldown);
    }

    public function testRecordChoiceOnAnAlreadyDecidedSeanceReturnsThePreviousFilmToThePool(): void
    {
        $premier = $this->movie('Premier choix');
        $second = $this->movie('Second choix');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->repo->recordChoice($seance['id'], [$premier], $premier);
        $this->repo->recordChoice($seance['id'], [$second], $second);

        $this->assertSame('pool', $this->movies->find($premier)['status'], 'Le premier film doit revenir au pool');
        $this->assertSame('watched', $this->movies->find($second)['status']);

        $chosen = $this->db->query(
            "SELECT movie_id FROM seance_picks WHERE role = 'chosen'"
        )->fetchAll();
        $this->assertCount(1, $chosen, 'Une seule ligne chosen doit subsister');
        $this->assertSame($second, (int) $chosen[0]['movie_id']);
    }

    public function testRecordChoiceOnTheSameFilmAgainDoesNotTouchThePool(): void
    {
        $movieId = $this->movie('Même film');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);
        $this->repo->recordChoice($seance['id'], [$movieId], $movieId);

        $this->assertSame('watched', $this->movies->find($movieId)['status']);
    }

    public function testRecordChoiceStillRefusesANonEmptyShortlistMissingTheChosenFilm(): void
    {
        $dansLaShortlist = $this->movie('Dans la shortlist');
        $horsShortlist = $this->movie('Hors shortlist');
        $seance = $this->repo->ensure('2026-08-15', 'adult');

        $this->expectException(\InvalidArgumentException::class);
        $this->repo->recordChoice($seance['id'], [$dansLaShortlist], $horsShortlist);
    }

    public function testHistoryIsSortedByDateDescendingWithMovieAndAverage(): void
    {
        $a = $this->movie('Ancien');
        $b = $this->movie('Récent');
        $s1 = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($s1['id'], [], $a);
        $s2 = $this->repo->ensure('2026-07-11', 'kid');
        $this->repo->recordChoice($s2['id'], [], $b);
        $this->repo->rate($s2['id'], $this->zoe, 5);
        $this->repo->rate($s2['id'], $this->jc, 4);

        $history = $this->repo->history();

        $this->assertSame(['Récent', 'Ancien'], array_column($history, 'movie_title'));
        $this->assertSame(4.5, (float) $history[0]['avg_score']);
        $this->assertSame('JC', $history[0]['proposer_name']);
    }

    public function testHistoryDefaultLimitCoversSeveralYearsOfWeeklySeances(): void
    {
        $insert = $this->db->prepare(
            "INSERT INTO seances (date, chooser_side, status) VALUES (?, 'adult', 'done')"
        );
        for ($i = 0; $i < 250; $i++) {
            $insert->execute([sprintf('2020-01-%02d', ($i % 28) + 1) . '-' . $i]);
        }

        $this->assertCount(250, $this->repo->history());
    }

    public function testMostRecentDoneReturnsTheLatestDoneSeance(): void
    {
        $a = $this->movie('Ancien');
        $b = $this->movie('Récent');
        $s1 = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($s1['id'], [], $a);
        $s2 = $this->repo->ensure('2026-07-11', 'kid');
        $this->repo->recordChoice($s2['id'], [], $b);
        $s3 = $this->repo->ensure('2026-07-18', 'adult');
        // Séance à venir, sans film retenu : ne doit jamais être retournée.

        $latest = $this->repo->mostRecentDone();

        $this->assertSame($s2['id'], $latest['id']);
    }

    public function testMostRecentDoneReturnsNullWhenNothingWasEverWatched(): void
    {
        $this->repo->ensure('2026-08-15', 'adult');

        $this->assertNull($this->repo->mostRecentDone());
    }

    public function testRecentForScheduleFeedsTheScheduleService(): void
    {
        $movieId = $this->movie('Brazil');
        $s1 = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($s1['id'], [], $movieId);
        $s2 = $this->repo->ensure('2026-07-11', 'kid');
        $this->repo->skip($s2['id']);

        $rows = $this->repo->recentForSchedule();

        $this->assertSame(['skipped', 'done'], array_column($rows, 'status'));
        $this->assertSame(['kid', 'adult'], array_column($rows, 'chooser_side'));
    }

    public function testWatchedDateForMovieReturnsTheMostRecentDoneSeance(): void
    {
        $movieId = $this->movie('Brazil');
        $s1 = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($s1['id'], [], $movieId);

        $this->assertSame('2026-07-04', $this->repo->watchedDateForMovie($movieId));
    }

    public function testWatchedDateForMovieIsNullWhenNeverWatched(): void
    {
        $movieId = $this->movie('Brazil');

        $this->assertNull($this->repo->watchedDateForMovie($movieId));
    }

    private function series(string $title, int $episodeCount): int
    {
        return $this->movies->addSeries([
            'title' => $title,
            'pool' => 'kid',
            'added_by' => $this->zoe,
            'episode_count' => $episodeCount,
            'episodes' => [],
        ]);
    }

    public function testRecordSeriesEveningWritesTheRangeLabelAndAdvancesProgress(): void
    {
        $seriesId = $this->series('Heartstopper', 24);
        $seance = $this->repo->ensure('2026-08-15', 'kid');

        $this->repo->recordSeriesEvening($seance['id'], $seriesId, [
            'from' => 1, 'to' => 2, 'label' => 'S1E1 à S1E2', 'finishes' => false,
        ]);

        $updated = $this->repo->findByDate('2026-08-15');
        $this->assertSame('done', $updated['status']);
        $this->assertSame($seriesId, (int) $updated['movie_id']);
        $this->assertSame(1, (int) $updated['episodes_from']);
        $this->assertSame(2, (int) $updated['episodes_to']);
        $this->assertSame('S1E1 à S1E2', $updated['episodes_label']);

        $movie = $this->movies->find($seriesId);
        $this->assertSame(2, (int) $movie['episodes_watched']);
        $this->assertSame('pool', $movie['status'], 'La serie en cours reste au pool');
    }

    public function testRecordSeriesEveningOnTheLastEpisodeMarksTheSeriesWatched(): void
    {
        $seriesId = $this->series('Heartstopper', 24);
        $seance = $this->repo->ensure('2026-08-15', 'kid');

        $this->repo->recordSeriesEvening($seance['id'], $seriesId, [
            'from' => 23, 'to' => 24, 'label' => 'S3E7 à S3E8', 'finishes' => true,
        ]);

        $this->assertSame('watched', $this->movies->find($seriesId)['status']);
    }

    public function testRecordSeriesEveningAcrossSeveralSaturdaysAdvancesTheSameSeries(): void
    {
        $seriesId = $this->series('Heartstopper', 24);

        $s1 = $this->repo->ensure('2026-08-01', 'kid');
        $this->repo->recordSeriesEvening($s1['id'], $seriesId, [
            'from' => 1, 'to' => 2, 'label' => 'S1E1 à S1E2', 'finishes' => false,
        ]);
        $s2 = $this->repo->ensure('2026-08-08', 'kid');
        $this->repo->recordSeriesEvening($s2['id'], $seriesId, [
            'from' => 3, 'to' => 4, 'label' => 'S1E3 à S1E4', 'finishes' => false,
        ]);

        $this->assertSame(4, (int) $this->movies->find($seriesId)['episodes_watched']);
        $this->assertSame($seriesId, (int) $this->repo->findByDate('2026-08-01')['movie_id']);
        $this->assertSame($seriesId, (int) $this->repo->findByDate('2026-08-08')['movie_id']);
    }

    public function testRecordSeriesEveningRollsBackEntirelyWhenTheSeriesIsUnknown(): void
    {
        $seance = $this->repo->ensure('2026-08-15', 'kid');

        try {
            $this->repo->recordSeriesEvening($seance['id'], 999, [
                'from' => 1, 'to' => 2, 'label' => 'S1E1 à S1E2', 'finishes' => false,
            ]);
            $this->fail('InvalidArgumentException attendue');
        } catch (\InvalidArgumentException) {
            // attendu
        }

        $untouched = $this->repo->findByDate('2026-08-15');
        $this->assertSame('planned', $untouched['status']);
        $this->assertNull($untouched['movie_id']);
        $this->assertNull($untouched['episodes_from']);
    }

    public function testVetoOnAnIntermediateSeriesEveningRollsBackTheProgression(): void
    {
        $seriesId = $this->series('Heartstopper', 24);
        $seance = $this->repo->ensure('2026-08-15', 'kid');
        $this->repo->recordSeriesEvening($seance['id'], $seriesId, [
            'from' => 3, 'to' => 4, 'label' => 'S1E3 à S1E4', 'finishes' => false,
        ]);
        // La soirée a fait passer la progression de 2 à 4 : le veto doit la
        // ramener à 2, ce qu'elle était juste avant cette soirée précise.

        $this->repo->recordVeto($seance['id'], $seriesId, $this->jc, 'Trop tard, pas ce soir');

        $movie = $this->movies->find($seriesId);
        $this->assertSame(2, (int) $movie['episodes_watched']);
        $this->assertSame('pool', $movie['status']);

        $updatedSeance = $this->repo->findByDate('2026-08-15');
        $this->assertNull($updatedSeance['episodes_from']);
        $this->assertNull($updatedSeance['episodes_to']);
        $this->assertNull($updatedSeance['episodes_label']);

        $veto = $this->db->query("SELECT * FROM seance_picks WHERE role = 'vetoed'")->fetch();
        $this->assertSame($this->jc, (int) $veto['by_profile_id']);
        $this->assertSame('Trop tard, pas ce soir', $veto['reason']);
    }

    public function testVetoOnTheFinalSeriesEveningReopensTheSeriesInsteadOfStrandingItWatched(): void
    {
        $seriesId = $this->series('Heartstopper', 24);
        $seance = $this->repo->ensure('2026-08-15', 'kid');
        $this->repo->recordSeriesEvening($seance['id'], $seriesId, [
            'from' => 23, 'to' => 24, 'label' => 'S3E7 à S3E8', 'finishes' => true,
        ]);
        $this->assertSame('watched', $this->movies->find($seriesId)['status'], 'préalable : la série est bien finie');

        // Sans le rattrapage, ce veto laisserait la série 'watched' pour toujours :
        // c'est exactement le cas qui bloquait la reprise.
        $this->repo->recordVeto($seance['id'], $seriesId, $this->elodie, 'On regarde autre chose finalement');

        $movie = $this->movies->find($seriesId);
        $this->assertSame(22, (int) $movie['episodes_watched']);
        $this->assertSame('pool', $movie['status'], 'la série redevient en cours, pas terminée');
    }

    public function testVetoOnAFilmSeanceLeavesEpisodeColumnsUntouched(): void
    {
        $movieId = $this->movie('Un film contesté');
        $seance = $this->repo->ensure('2026-08-15', 'kid');
        $this->repo->recordChoice($seance['id'], [], $movieId);

        $this->repo->recordVeto($seance['id'], $movieId, $this->jc, 'Trop dur pour Soline');

        $updated = $this->repo->findByDate('2026-08-15');
        $this->assertNull($updated['episodes_from']);
        $this->assertNull($updated['episodes_to']);
        $this->assertNull($updated['episodes_label']);
        $this->assertSame('pool', $this->movies->find($movieId)['status']);
    }

    public function testTwoVetoesInARowOnRelaunchedSeriesEveningsNeverGoNegative(): void
    {
        $seriesId = $this->series('Heartstopper', 24);
        $seance = $this->repo->ensure('2026-08-15', 'kid');

        $this->repo->recordSeriesEvening($seance['id'], $seriesId, [
            'from' => 1, 'to' => 2, 'label' => 'S1E1 à S1E2', 'finishes' => false,
        ]);
        $this->repo->recordVeto($seance['id'], $seriesId, $this->jc, 'Non');
        $this->assertSame(0, (int) $this->movies->find($seriesId)['episodes_watched']);

        // La soirée est relancée sur la même séance (redevenue 'planned') puis
        // vetoée une seconde fois : la progression ne doit jamais passer sous zéro.
        $this->repo->recordSeriesEvening($seance['id'], $seriesId, [
            'from' => 1, 'to' => 2, 'label' => 'S1E1 à S1E2', 'finishes' => false,
        ]);
        $this->repo->recordVeto($seance['id'], $seriesId, $this->elodie, 'Non plus');

        $movie = $this->movies->find($seriesId);
        $this->assertSame(0, (int) $movie['episodes_watched']);
        $this->assertGreaterThanOrEqual(0, (int) $movie['episodes_watched']);
        $this->assertSame('pool', $movie['status']);
    }

    public function testRecordBackfillCreatesADoneSeanceWithThePoolAsChooserSideAndMarksTheMovieWatched(): void
    {
        $movieId = $this->movie('Brazil');

        $seance = $this->repo->recordBackfill($movieId, '2026-08-01');

        $this->assertSame('2026-08-01', $seance['date']);
        $this->assertSame('done', $seance['status']);
        $this->assertSame('adult', $seance['chooser_side'], 'Le camp vient du pool du film, pas d une saisie');
        $this->assertSame(1, (int) $seance['backfilled']);
        $this->assertSame($movieId, (int) $seance['movie_id']);

        $this->assertSame('watched', $this->movies->find($movieId)['status']);
        $this->assertSame(
            [],
            array_column($this->movies->drawCandidates(), 'id'),
            'Le film rattrapé doit quitter le tirage'
        );
    }

    public function testRecordBackfillOnASeriesMarksAllEpisodesWatchedWithTheEpisodeCountLabel(): void
    {
        $seriesId = $this->series('Heartstopper', 24);

        $seance = $this->repo->recordBackfill($seriesId, '2026-08-01');

        $this->assertSame(1, (int) $seance['episodes_from']);
        $this->assertSame(24, (int) $seance['episodes_to']);
        $this->assertSame('24 épisodes', $seance['episodes_label']);

        $movie = $this->movies->find($seriesId);
        $this->assertSame(24, (int) $movie['episodes_watched']);
        $this->assertSame('watched', $movie['status']);
    }

    public function testRecordBackfillConsumesNoCooldownSlot(): void
    {
        // Un vrai tirage laisse une trace de cooldown : on la fabrique pour
        // prouver que le rattrapage ne l'altère pas, plutôt que de comparer deux
        // listes vides qui resteraient égales par construction.
        $shortlisted = $this->movie('Dans le cooldown');
        $drawnSeance = $this->repo->ensure('2026-07-18', 'adult');
        $this->repo->recordChoice($drawnSeance['id'], [$shortlisted], $shortlisted);
        $before = $this->repo->cooldownMovieIds();
        $this->assertSame([$shortlisted], $before);

        $movieId = $this->movie('Brazil');
        $this->repo->recordBackfill($movieId, '2026-08-01');

        $this->assertSame($before, $this->repo->cooldownMovieIds());
    }

    /**
     * La vérification la plus importante du rattrapage : ScheduleService::defaultChooserSide()
     * parcourt recentForSchedule() par date décroissante pour déduire le camp du samedi à
     * venir. Un rattrapage daté après la dernière vraie séance ne doit jamais s'y glisser.
     */
    public function testRecordBackfillDatedAfterTheLastRealSeanceDoesNotFlipTheComingSaturdaysSide(): void
    {
        $movieId = $this->movie('Brazil');
        $realSeance = $this->repo->ensure('2026-07-04', 'adult');
        $this->repo->recordChoice($realSeance['id'], [], $movieId);

        $before = \App\Services\ScheduleService::defaultChooserSide($this->repo->recentForSchedule());
        $this->assertSame('kid', $before, 'Après une vraie séance adulte, le tour revient aux filles');

        $laterMovieId = $this->movie('Un film rattrapé');
        $this->repo->recordBackfill($laterMovieId, '2026-08-08');

        $after = \App\Services\ScheduleService::defaultChooserSide($this->repo->recentForSchedule());
        $this->assertSame($before, $after, 'Un rattrapage plus récent ne doit jamais rejouer l alternance');
        $this->assertSame('kid', $after);
    }

    public function testRecentForScheduleExcludesBackfilledSeances(): void
    {
        $movieId = $this->movie('Brazil');
        $this->repo->recordBackfill($movieId, '2026-08-08');

        $this->assertSame([], $this->repo->recentForSchedule());
    }

    public function testRecordBackfillRefusesAFutureDate(): void
    {
        $movieId = $this->movie('Brazil');
        $future = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $this->expectException(\App\Repositories\BackfillException::class);
        try {
            $this->repo->recordBackfill($movieId, $future);
        } finally {
            $this->assertSame('pool', $this->movies->find($movieId)['status'], 'Rien ne doit être écrit');
            $this->assertNull($this->repo->findByDate($future));
        }
    }

    public function testRecordBackfillRefusesAnInvalidDate(): void
    {
        $movieId = $this->movie('Brazil');

        $this->expectException(\App\Repositories\BackfillException::class);
        try {
            $this->repo->recordBackfill($movieId, 'pas une date');
        } finally {
            $this->assertSame('pool', $this->movies->find($movieId)['status']);
        }
    }

    public function testRecordBackfillRefusesAnEmptyDate(): void
    {
        $movieId = $this->movie('Brazil');

        $this->expectException(\App\Repositories\BackfillException::class);
        $this->repo->recordBackfill($movieId, '');
    }

    public function testRecordBackfillRefusesADateAlreadyTaken(): void
    {
        $movieId = $this->movie('Brazil');
        $otherMovieId = $this->movie('Autre film');
        $this->repo->ensure('2026-08-01', 'adult');

        try {
            $this->repo->recordBackfill($otherMovieId, '2026-08-01');
            $this->fail('Une exception était attendue');
        } catch (\App\Repositories\BackfillException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame('pool', $this->movies->find($otherMovieId)['status'], 'Rien ne doit être écrit');
    }

    public function testRecordBackfillRefusesAnAlreadyWatchedMovie(): void
    {
        $movieId = $this->movie('Brazil');
        $this->repo->recordBackfill($movieId, '2026-08-01');

        $this->expectException(\App\Repositories\BackfillException::class);
        $this->repo->recordBackfill($movieId, '2026-08-08');
    }
}
