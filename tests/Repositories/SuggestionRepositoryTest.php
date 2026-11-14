<?php
namespace App\Tests\Repositories;

use App\Repositories\SuggestionRepository;
use App\Tests\Support\DbTestCase;

class SuggestionRepositoryTest extends DbTestCase
{
    private SuggestionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SuggestionRepository($this->db);
    }

    private function idea(int $tmdb = 1, string $kind = 'film'): array
    {
        return ['tmdb_id' => $tmdb, 'kind' => $kind, 'title' => 'Coco', 'year' => 2017, 'audience' => 'all', 'bet_type' => 'safe', 'why' => 'Aimé'];
    }

    public function testAddedIdeaStartsNewAndDuplicatesAreIgnoredWhateverTheirFate(): void
    {
        $id = $this->repo->add($this->idea());
        $this->assertNotNull($id);
        $this->assertSame('new', $this->repo->find($id)['status']);

        $this->repo->setStatus($id, 'rejected');
        $this->assertNull($this->repo->add($this->idea()), 'Une idée écartée ne revient pas');
        $this->assertTrue($this->repo->exists(1, 'film'));
        $this->assertFalse($this->repo->exists(1, 'series'));
    }

    public function testInvalidValuesFallBackInsteadOfBreakingTheCheckConstraints(): void
    {
        $id = $this->repo->add(['audience' => 'tous', 'bet_type' => 'nimporte'] + $this->idea());

        $row = $this->repo->find($id);
        $this->assertSame('all', $row['audience']);
        $this->assertNull($row['bet_type']);
    }

    public function testKeepAndRestoreMoveBetweenStatusesButTransferredIsFinal(): void
    {
        $id = $this->repo->add($this->idea());
        $this->repo->setStatus($id, 'kept');
        $this->assertCount(1, $this->repo->byStatus('kept'));

        $this->db->exec("INSERT INTO profiles (name, slug, side, avatar) VALUES ('A','a','adult','blob')");
        $this->db->exec("INSERT INTO movies (title, pool, added_by) VALUES ('Coco','kid',1)");
        $this->repo->markTransferred($id, (int) $this->db->lastInsertId());
        $this->repo->setStatus($id, 'rejected');

        $this->assertSame('transferred', $this->repo->find($id)['status']);
        $this->assertSame(['new' => 0, 'kept' => 0, 'rejected' => 0, 'transferred' => 1], $this->repo->counts());
    }

    public function testTheAudienceFilterKeepsWhatSuitsThatPublicAndWhatSuitsEveryone(): void
    {
        $rows = [['title' => 'Pour tous', 'audience' => 'all'], ['title' => 'Parents', 'audience' => 'adult'], ['title' => 'Filles', 'audience' => 'kid']];

        $titles = static fn (array $r): array => array_column($r, 'title');
        $this->assertSame(['Pour tous', 'Parents', 'Filles'], $titles(\App\Repositories\SuggestionRepository::filtered($rows, 'all')));
        $this->assertSame(['Pour tous', 'Parents'], $titles(\App\Repositories\SuggestionRepository::filtered($rows, 'adult')));
        $this->assertSame(['Pour tous', 'Filles'], $titles(\App\Repositories\SuggestionRepository::filtered($rows, 'kid')));
        // Un filtre inconnu ne cache rien.
        $this->assertCount(3, \App\Repositories\SuggestionRepository::filtered($rows, 'n-importe-quoi'));
    }
}
