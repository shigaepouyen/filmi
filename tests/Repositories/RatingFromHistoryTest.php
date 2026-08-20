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

    public function testFamilyRatingGivesOneLinePerPerson(): void
    {
        $film = $this->movies->add([
            'title' => 'Brazil', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
        $this->seances->recordBackfill($film, '2026-07-04');
        $seance = (int) $this->seances->watchSeanceForMovie($film)['id'];

        $this->seances->rate($seance, $this->jc, 4);
        $this->seances->rate($seance, $this->zoe, 5);

        $note = $this->seances->familyRating($film);

        $this->assertSame(4.5, $note['average']);
        $this->assertSame(2, $note['count'], 'Le compteur compte les personnes, pas les notes');
        $this->assertSame(['JC', 'Zoé'], array_column($note['rows'], 'name'));
        $this->assertSame([4.0, 5.0], array_column($note['rows'], 'average'));
        $this->assertSame('2026-07-04', $note['rows'][0]['scores'][0]['date']);
    }

    public function testFamilyRatingIsEmptyWhenNobodyRated(): void
    {
        $film = $this->movies->add([
            'title' => 'Alien', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
        $this->seances->recordBackfill($film, '2026-07-11');

        $note = $this->seances->familyRating($film);

        $this->assertNull($note['average']);
        $this->assertSame(0, $note['count']);
        $this->assertSame([], $note['rows']);
    }

    public function testARewatchGivesTheSamePersonASingleAveragedLine(): void
    {
        $film = $this->revuDeuxFois();

        $note = $this->seances->familyRating($film);

        $this->assertSame(1, $note['count'], 'JC a note deux fois mais reste une seule personne');
        $this->assertSame(4.0, $note['rows'][0]['average'], 'Moyenne de ses deux notes');
        $this->assertSame([5, 3], array_column($note['rows'][0]['scores'], 'score'));
        // Le plus recent d'abord, pour que le detail se lise du dernier au premier.
        $this->assertSame(
            ['2026-07-18', '2026-06-06'],
            array_column($note['rows'][0]['scores'], 'date')
        );
    }

    public function testEachPersonWeighsTheSameWhateverTheNumberOfViewings(): void
    {
        // Le piege que la moyenne des notes brutes tendait : JC note deux fois,
        // Zoe une seule, et JC pesait deux tiers de la note familiale.
        $film = $this->revuDeuxFois();
        $seconde = (int) $this->seances->watchSeanceForMovie($film)['id'];
        $this->seances->rate($seconde, $this->zoe, 1);

        $note = $this->seances->familyRating($film);

        // Moyenne des moyennes : (4 + 1) / 2, et non (5 + 3 + 1) / 3 = 3.
        $this->assertSame(2.5, $note['average']);
        $this->assertSame(2, $note['count']);
    }

    public function testFamilyRatingIgnoresOtherWorks(): void
    {
        $brazil = $this->movies->add([
            'title' => 'Brazil', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
        $alien = $this->movies->add([
            'title' => 'Alien', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
        $this->seances->recordBackfill($brazil, '2026-07-04');
        $this->seances->recordBackfill($alien, '2026-07-11');
        $this->seances->rate((int) $this->seances->watchSeanceForMovie($brazil)['id'], $this->jc, 2);
        $this->seances->rate((int) $this->seances->watchSeanceForMovie($alien)['id'], $this->jc, 5);

        $this->assertSame(2.0, $this->seances->familyRating($brazil)['average']);
        $this->assertSame(5.0, $this->seances->familyRating($alien)['average']);
    }

    /** Un film vu le 6 juin puis revu le 18 juillet, note 3 puis 5 par JC. */
    private function revuDeuxFois(): int
    {
        $film = $this->movies->add([
            'title' => 'Solaris', 'pool' => 'adult', 'bet_type' => 'safe', 'added_by' => $this->jc,
        ]);
        $this->seances->recordBackfill($film, '2026-06-06');
        $this->seances->rate((int) $this->seances->watchSeanceForMovie($film)['id'], $this->jc, 3);

        $this->movies->markForRewatch($film);
        $this->seances->recordBackfill($film, '2026-07-18');
        $this->seances->rate((int) $this->seances->watchSeanceForMovie($film)['id'], $this->jc, 5);

        return $film;
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
