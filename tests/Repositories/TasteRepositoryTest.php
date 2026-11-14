<?php
namespace App\Tests\Repositories;

use App\Repositories\ProfileRepository;
use App\Repositories\TasteRepository;
use App\Tests\Support\DbTestCase;

class TasteRepositoryTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $this->db->exec("INSERT INTO movies (title, year, pool, added_by, status, memo) VALUES ('Coco', 2017, 'kid', 3, 'watched', 'secret de famille')");
        $this->db->exec("INSERT INTO movies (title, year, pool, bet_type, added_by, memo) VALUES ('Alien', 1979, 'adult', 'discovery', 1, 'mémo parent')");
        $this->db->exec("INSERT INTO seances (date, chooser_side, status, movie_id) VALUES ('2026-10-03', 'kid', 'done', 1)");
        $this->db->exec("INSERT INTO ratings (seance_id, profile_id, score) VALUES (1, 1, 5), (1, 3, 4)");
        $this->db->exec("INSERT INTO votes (movie_id, profile_id) VALUES (2, 1), (2, 2)");
        $this->db->exec("INSERT INTO seance_picks (seance_id, movie_id, role, by_profile_id, reason) VALUES (1, 2, 'vetoed', 1, 'trop violent pour Soline')");
    }

    private function taste(bool $memos): array
    {
        return (new TasteRepository($this->db, new ProfileRepository($this->db)))->taste($memos);
    }

    public function testRatingsAreLabelledWithoutAnyFirstName(): void
    {
        $taste = $this->taste(true);

        $this->assertSame(['Parent 1' => 5, 'Fille 1' => 4], $taste['seen'][0]['ratings']);
        $json = json_encode($taste, JSON_UNESCAPED_UNICODE);
        foreach (['JC', 'Élodie', 'Zoé', 'Soline'] as $name) {
            $this->assertStringNotContainsString($name, str_replace('trop violent pour Soline', '', $json));
        }
    }

    public function testListsCarryVotesAndOnlyPoolStatusEntries(): void
    {
        $taste = $this->taste(false);

        $this->assertSame([], $taste['lists']['kid'], 'Coco est vu : il n\'est plus dans la liste');
        $this->assertSame('Alien', $taste['lists']['adult'][0]['title']);
        $this->assertSame(2, $taste['lists']['adult'][0]['votes']);
    }

    public function testFreeTextLeavesOnlyWhenMemosAreAllowed(): void
    {
        $without = json_encode($this->taste(false), JSON_UNESCAPED_UNICODE);
        $with = json_encode($this->taste(true), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('mémo parent', $without);
        $this->assertStringNotContainsString('trop violent', $without);
        $this->assertStringContainsString('mémo parent', $with);
        $this->assertStringContainsString('trop violent', $with);
    }

    public function testKnownIncludesEveryTitleEvenWatchedOnes(): void
    {
        $this->assertEqualsCanonicalizing(['Coco (2017)', 'Alien (1979)'], $this->taste(false)['known']);
    }
}
