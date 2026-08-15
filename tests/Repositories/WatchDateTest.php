<?php
namespace App\Tests\Repositories;

use App\Repositories\BackfillException;
use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SeanceRepository;
use App\Tests\Support\DbTestCase;

class WatchDateTest extends DbTestCase
{
    private SeanceRepository $seances;
    private MovieRepository $movies;
    private int $jc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $this->jc = (new ProfileRepository($this->db))->findBySlug('jc')['id'];
        $this->movies = new MovieRepository($this->db);
        $this->seances = new SeanceRepository($this->db, $this->movies);
    }

    private function film(string $title = 'Brazil'): int
    {
        return $this->movies->add([
            'title' => $title, 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
    }

    public function testTheWatchDateCanBeMoved(): void
    {
        $id = $this->film();
        $this->seances->recordBackfill($id, '2026-07-04');
        $seance = $this->seances->watchSeanceForMovie($id);

        $this->seances->moveSeance((int) $seance['id'], '2026-06-20');

        $this->assertSame('2026-06-20', $this->seances->watchedDateForMovie($id));
    }

    public function testMovingOntoATakenDateIsRefused(): void
    {
        $premier = $this->film('Brazil');
        $second = $this->film('Alien');
        $this->seances->recordBackfill($premier, '2026-07-04');
        $this->seances->recordBackfill($second, '2026-06-20');
        $seance = $this->seances->watchSeanceForMovie($second);

        $this->expectException(BackfillException::class);
        $this->seances->moveSeance((int) $seance['id'], '2026-07-04');
    }

    public function testMovingToItsOwnDateIsNotSeenAsACollision(): void
    {
        $id = $this->film();
        $this->seances->recordBackfill($id, '2026-07-04');
        $seance = $this->seances->watchSeanceForMovie($id);

        $this->seances->moveSeance((int) $seance['id'], '2026-07-04');

        $this->assertSame('2026-07-04', $this->seances->watchedDateForMovie($id));
    }

    public function testAFutureWatchDateIsRefused(): void
    {
        $id = $this->film();
        $this->seances->recordBackfill($id, '2026-07-04');
        $seance = $this->seances->watchSeanceForMovie($id);
        $demain = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $this->expectException(BackfillException::class);
        $this->seances->moveSeance((int) $seance['id'], $demain);
    }

    public function testRemovingTheWatchPutsTheFilmBackInItsList(): void
    {
        $id = $this->film();
        $this->seances->recordBackfill($id, '2026-07-04');
        $seance = $this->seances->watchSeanceForMovie($id);

        $this->seances->removeWatch((int) $seance['id']);

        $this->assertSame('pool', $this->movies->find($id)['status']);
        $this->assertNull($this->seances->watchedDateForMovie($id));
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM seances')->fetchColumn());
    }

    public function testRemovingAWatchAlsoDropsItsRatings(): void
    {
        $id = $this->film();
        $this->seances->recordBackfill($id, '2026-07-04');
        $seance = $this->seances->watchSeanceForMovie($id);
        $this->seances->rate((int) $seance['id'], $this->jc, 5);

        $this->seances->removeWatch((int) $seance['id']);

        $this->assertSame(
            0,
            (int) $this->db->query('SELECT COUNT(*) FROM ratings')->fetchColumn(),
            'Les notes portaient sur cette soiree, elles disparaissent avec elle'
        );
    }

    public function testWatchSeanceReportsWhatARemovalWouldCost(): void
    {
        $a = $this->film('Sûr');
        $b = $this->film('Découverte');
        $seance = $this->seances->ensure('2026-07-04', 'adult');
        $this->seances->recordChoice((int) $seance['id'], [$a, $b], $a);
        $this->seances->rate((int) $seance['id'], $this->jc, 4);

        $trouvee = $this->seances->watchSeanceForMovie($a);

        $this->assertSame(1, (int) $trouvee['rating_count']);
        $this->assertSame(2, (int) $trouvee['shortlist_count'], 'La shortlist alimente le cooldown');
        $this->assertSame(0, (int) $trouvee['backfilled']);
    }

    public function testRemovingASeriesEveningRewindsItsProgress(): void
    {
        $episodes = [];
        for ($i = 1; $i <= 4; $i++) {
            $episodes[] = ['number' => $i, 'season' => 1, 'episode_in_season' => $i, 'runtime' => 30];
        }
        $serie = $this->movies->addSeries([
            'title' => 'Heartstopper', 'pool' => 'kid', 'added_by' => $this->jc,
            'season_count' => 1, 'episode_count' => 4, 'episodes_per_evening' => 2,
            'episodes' => $episodes,
        ]);

        $premiere = $this->seances->ensure('2026-07-04', 'kid');
        $this->seances->recordSeriesEvening((int) $premiere['id'], $serie, [
            'from' => 1, 'to' => 2, 'label' => 'S1E1 à S1E2', 'total_runtime' => 60, 'finishes' => false,
        ]);
        $seconde = $this->seances->ensure('2026-07-11', 'kid');
        $this->seances->recordSeriesEvening((int) $seconde['id'], $serie, [
            'from' => 3, 'to' => 4, 'label' => 'S1E3 à S1E4', 'total_runtime' => 59, 'finishes' => true,
        ]);

        $this->assertSame('watched', $this->movies->find($serie)['status']);

        $this->seances->removeWatch((int) $seconde['id']);

        $apres = $this->movies->find($serie);
        $this->assertSame('pool', $apres['status'], 'La serie doit redevenir reprenable');
        $this->assertSame(
            2,
            (int) $apres['episodes_watched'],
            'Les deux episodes de la soiree retiree ne sont plus comptes comme vus'
        );
    }

    public function testRemovingAnUnknownSeanceDoesNothing(): void
    {
        $this->seances->removeWatch(999);

        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM seances')->fetchColumn());
    }
}
