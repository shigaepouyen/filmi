<?php
namespace App\Tests\Repositories;

use App\Repositories\ProfileRepository;
use App\Tests\Support\DbTestCase;

class ProfileRepositoryTest extends DbTestCase
{
    private ProfileRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();
        $this->repo = new ProfileRepository($this->db);
    }

    public function testAllReturnsFourProfilesAdultsFirst(): void
    {
        $all = $this->repo->all();

        $this->assertCount(4, $all);
        $this->assertSame(['JC', 'Élodie', 'Zoé', 'Soline'], array_column($all, 'name'));
        $this->assertSame('adult', $all[0]['side']);
    }

    public function testFindBySlug(): void
    {
        $zoe = $this->repo->findBySlug('zoe');

        $this->assertSame('Zoé', $zoe['name']);
        $this->assertSame('kid', $zoe['side']);
        $this->assertSame('idole', $zoe['avatar']);
        $this->assertNull($this->repo->findBySlug('inconnu'));
    }

    public function testBySideFiltersCamps(): void
    {
        $this->assertSame(['JC', 'Élodie'], array_column($this->repo->bySide('adult'), 'name'));
        $this->assertSame(['Zoé', 'Soline'], array_column($this->repo->bySide('kid'), 'name'));
    }

    public function testUpdateChangesNameAvatarAndColor(): void
    {
        $id = $this->repo->findBySlug('soline')['id'];

        $this->repo->update($id, 'Soso', 'gumiho', 'amber');
        $updated = $this->repo->find($id);

        $this->assertSame('Soso', $updated['name']);
        $this->assertSame('gumiho', $updated['avatar']);
        $this->assertSame('amber', $updated['color']);
        $this->assertSame('soline', $updated['slug'], 'Le slug ne doit jamais bouger');
    }

    public function testUpdateRejectsUnknownAvatarAndKeepsPrevious(): void
    {
        $id = $this->repo->findBySlug('jc')['id'];

        $this->repo->update($id, 'JC', 'mickey', 'slate');

        $this->assertSame('detective', $this->repo->find($id)['avatar']);
    }

    public function testFindReturnsNullOnUnknownId(): void
    {
        $this->assertNull($this->repo->find(999));
    }
}
