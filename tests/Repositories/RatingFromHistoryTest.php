<?php
namespace App\Tests\Repositories;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SeanceRepository;
use App\Tests\Support\DbTestCase;

class RatingFromHistoryTest extends DbTestCase
{
    private SeanceRepository $seances;
    private MovieRepository $movies;
    private int $jc;
    private int $zoe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $profiles = new ProfileRepository($this->db);
        $this->jc = $profiles->findBySlug('jc')['id'];
        $this->zoe = $profiles->findBySlug('zoe')['id'];
        $this->movies = new MovieRepository($this->db);
        $this->seances = new SeanceRepository($this->db, $this->movies);
    }

    private function seanceVue(string $date = '2026-07-04', string $titre = 'Brazil'): int
    {
        $film = $this->movies->add([
            'title' => $titre, 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
        $this->seances->recordBackfill($film, $date);

        return (int) $this->seances->watchSeanceForMovie($film)['id'];
    }

    public function testNoRatingYetIsReportedAsNull(): void
    {
        $seance = $this->seanceVue();

        $this->assertNull($this->seances->ratingFor($seance, $this->jc));
        $this->assertNull($this->seances->averageFor($seance));
    }

    public function testARatingIsReadBackForItsOwnProfileOnly(): void
    {
        $seance = $this->seanceVue();
        $this->seances->rate($seance, $this->jc, 4);

        $this->assertSame(4, $this->seances->ratingFor($seance, $this->jc));
        $this->assertNull(
            $this->seances->ratingFor($seance, $this->zoe),
            'La note de JC ne doit pas etre attribuee a Zoe'
        );
    }

    public function testTheAverageCombinesEveryonesRatings(): void
    {
        $seance = $this->seanceVue();
        $this->seances->rate($seance, $this->jc, 4);
        $this->seances->rate($seance, $this->zoe, 5);

        $this->assertSame(4.5, $this->seances->averageFor($seance));
    }

    public function testChangingARatingReplacesItRatherThanAddingASecond(): void
    {
        $seance = $this->seanceVue();
        $this->seances->rate($seance, $this->jc, 2);
        $this->seances->rate($seance, $this->jc, 5);

        $this->assertSame(5, $this->seances->ratingFor($seance, $this->jc));
        $this->assertSame(
            1,
            (int) $this->db->query('SELECT COUNT(*) FROM ratings')->fetchColumn(),
            'Une personne n a qu une note par seance'
        );
    }

    public function testMyRatingsIndexesEverySeanceIRated(): void
    {
        $premiere = $this->seanceVue('2026-07-04', 'Brazil');
        $seconde = $this->seanceVue('2026-07-11', 'Alien');
        $this->seanceVue('2026-07-18', 'Solaris');

        $this->seances->rate($premiere, $this->jc, 3);
        $this->seances->rate($seconde, $this->jc, 5);
        $this->seances->rate($seconde, $this->zoe, 1);

        $mesNotes = $this->seances->myRatings($this->jc);

        $this->assertSame([$premiere => 3, $seconde => 5], $mesNotes);
        $this->assertArrayNotHasKey(
            $seconde,
            array_diff_key($this->seances->myRatings($this->zoe), [$seconde => 1]),
            'myRatings ne melange pas les profils'
        );
    }

    public function testClosingTheLineIsNotARatingAndCountsNowhere(): void
    {
        $seance = $this->seanceVue();
        $this->seances->skipRating($seance, $this->jc);

        $this->assertTrue($this->seances->hasSkippedRating($seance, $this->jc));
        $this->assertSame([$seance => true], $this->seances->mySkippedRatings($this->jc));
        $this->assertNull($this->seances->ratingFor($seance, $this->jc));
        $this->assertNull(
            $this->seances->averageFor($seance),
            'Un tour passe ne doit pas peser sur la moyenne'
        );
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM ratings')->fetchColumn());
    }

    public function testClosingTwiceStaysASingleRow(): void
    {
        $seance = $this->seanceVue();
        $this->seances->skipRating($seance, $this->jc);
        $this->seances->skipRating($seance, $this->jc);

        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM rating_skips')->fetchColumn());
    }

    public function testReopeningClearsTheSkip(): void
    {
        $seance = $this->seanceVue();
        $this->seances->skipRating($seance, $this->jc);
        $this->seances->reopenRating($seance, $this->jc);

        $this->assertFalse($this->seances->hasSkippedRating($seance, $this->jc));
        $this->assertSame([], $this->seances->mySkippedRatings($this->jc));
    }

    public function testRatingAfterClosingReopensTheLine(): void
    {
        $seance = $this->seanceVue();
        $this->seances->skipRating($seance, $this->jc);
        $this->seances->rate($seance, $this->jc, 4);

        $this->assertFalse(
            $this->seances->hasSkippedRating($seance, $this->jc),
            'Noter, c est revenir sur son tour passe : les deux etats s excluent'
        );
        $this->assertSame(4, $this->seances->ratingFor($seance, $this->jc));
    }

    public function testASkipBelongsToItsOwnProfile(): void
    {
        $seance = $this->seanceVue();
        $this->seances->skipRating($seance, $this->zoe);

        $this->assertFalse($this->seances->hasSkippedRating($seance, $this->jc));
        $this->assertSame([], $this->seances->mySkippedRatings($this->jc));

        $noms = array_column($this->seances->ratingSkips($seance), 'name');
        $this->assertSame(['Zoé'], $noms);
    }

    public function testRatingsOutsideTheAllowedRangeAreRefused(): void
    {
        $seance = $this->seanceVue();

        foreach ([0, 6, -1] as $invalide) {
            try {
                $this->seances->rate($seance, $this->jc, $invalide);
                $this->fail("La note {$invalide} aurait du etre refusee");
            } catch (\InvalidArgumentException) {
                // attendu
            }
        }

        $this->assertNull($this->seances->ratingFor($seance, $this->jc));
    }
}
