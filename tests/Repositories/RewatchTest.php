<?php
namespace App\Tests\Repositories;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SeanceRepository;
use App\Services\SequelService;
use App\Tests\Support\DbTestCase;

class RewatchTest extends DbTestCase
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

    private function film(string $title, ?int $collection = null, ?int $rank = null): int
    {
        return $this->movies->add([
            'title' => $title,
            'pool' => 'adult',
            'bet_type' => 'safe',
            'added_by' => $this->jc,
            'collection_id' => $collection,
            'collection_name' => $collection === null ? null : 'Une saga',
            'collection_rank' => $rank,
        ]);
    }

    public function testRewatchPutsTheFilmBackInItsListWithoutErasingItsPast(): void
    {
        $id = $this->film('Retour vers le futur');
        $this->seances->recordBackfill($id, '2026-05-02');

        $this->movies->markForRewatch($id);

        $this->assertSame('pool', $this->movies->find($id)['status']);
        $this->assertSame(
            ['2026-05-02'],
            $this->seances->viewingsForMovie($id),
            'Le visionnage precedent reste dans l historique'
        );
    }

    public function testARewatchedFilmIsDrawableAgain(): void
    {
        $id = $this->film('Retour vers le futur');
        $this->seances->recordBackfill($id, '2026-05-02');
        $this->assertNotContains($id, array_column($this->movies->drawCandidates(), 'id'));

        $this->movies->markForRewatch($id);

        $this->assertContains($id, array_column($this->movies->drawCandidates(), 'id'));
    }

    public function testWatchingItAgainAddsASecondViewing(): void
    {
        $id = $this->film('Retour vers le futur');
        $this->seances->recordBackfill($id, '2026-05-02');
        $this->movies->markForRewatch($id);
        $this->seances->recordBackfill($id, '2026-07-11');

        $this->assertSame(['2026-07-11', '2026-05-02'], $this->seances->viewingsForMovie($id));
        $this->assertSame('2026-07-11', $this->seances->watchedDateForMovie($id));
    }

    public function testARewatchIsNeverHeldBackByItsSagaOrder(): void
    {
        // Le second d'une saga a deja ete vu, on veut le revoir alors que le
        // premier attend encore dans la liste : rien a gacher, donc rien a bloquer.
        $premier = $this->film('Violent Night', 1078456, 1);
        $second = $this->film('Violent Night 2', 1078456, 2);
        $this->seances->recordBackfill($second, '2026-05-02');
        $this->movies->markForRewatch($second);

        $catalogue = $this->movies->sagaCatalogue();
        $ligne = null;
        foreach ($catalogue as $row) {
            if ((int) $row['id'] === $second) {
                $ligne = $row;
            }
        }

        $this->assertSame(1, (int) $ligne['already_seen']);
        $this->assertNull(SequelService::blockedBy($ligne, $catalogue));
        $this->assertContains($second, array_column($this->movies->drawCandidates(), 'id'));
    }

    public function testANeverSeenSequelIsStillHeldBack(): void
    {
        $premier = $this->film('Violent Night', 1078456, 1);
        $second = $this->film('Violent Night 2', 1078456, 2);

        $this->assertNotContains(
            $second,
            array_column($this->movies->drawCandidates(), 'id'),
            'La regle de saga continue de jouer pour une oeuvre jamais vue'
        );
        $this->assertNotNull($this->movies->blockedBy($second));
    }

    public function testRewatchingASeriesRestartsItFromTheBeginning(): void
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
        $this->seances->recordBackfill($serie, '2026-05-02');
        $this->assertSame(4, (int) $this->movies->find($serie)['episodes_watched']);

        $this->movies->markForRewatch($serie);

        $apres = $this->movies->find($serie);
        $this->assertSame('pool', $apres['status']);
        $this->assertSame(
            0,
            (int) $apres['episodes_watched'],
            'Revoir une serie, c est la reprendre au premier episode'
        );
        $this->assertSame(['2026-05-02'], $this->seances->viewingsForMovie($serie));
    }

    public function testRewatchDoesNothingOnAFilmStillInItsList(): void
    {
        $id = $this->film('Jamais vu');

        $this->movies->markForRewatch($id);

        $this->assertSame('pool', $this->movies->find($id)['status']);
        $this->assertSame([], $this->seances->viewingsForMovie($id));
    }

    public function testRewatchDoesNotResurrectAnArchivedFilm(): void
    {
        $id = $this->film('Archive');
        $this->movies->archive($id);

        $this->movies->markForRewatch($id);

        $this->assertSame(
            'archived',
            $this->movies->find($id)['status'],
            'Revoir ne doit concerner que les oeuvres vues, pas les archivees'
        );
    }
}
