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
}
