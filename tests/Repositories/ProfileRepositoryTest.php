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

    public function testUpdateRejectsAColourOutsideThePaletteAndKeepsPrevious(): void
    {
        $id = $this->repo->findBySlug('jc')['id'];
        $avant = $this->repo->find($id)['color'];

        $this->repo->update($id, 'JC', 'detective', 'chartreuse');

        $apres = $this->repo->find($id);
        $this->assertSame($avant, $apres['color'], 'Une couleur hors palette ne doit pas etre ecrite');
        $this->assertSame('JC', $apres['name'], 'Le reste de la mise a jour passe quand meme');
    }

    public function testUpdateAcceptsEveryColourOfThePalette(): void
    {
        $id = $this->repo->findBySlug('jc')['id'];

        foreach (\App\Utils\Avatars::colors() as $couleur) {
            $this->repo->update($id, 'JC', 'detective', $couleur);
            $this->assertSame($couleur, $this->repo->find($id)['color']);
        }
    }

    public function testUpdateTrimsAnOverlongName(): void
    {
        $id = $this->repo->findBySlug('jc')['id'];

        $this->repo->update($id, str_repeat('é', 200), 'detective', 'slate');

        $this->assertSame(
            30,
            mb_strlen($this->repo->find($id)['name']),
            'Le nom doit etre borne cote serveur, pas seulement par le formulaire'
        );
    }

    public function testFindReturnsNullOnUnknownId(): void
    {
        $this->assertNull($this->repo->find(999));
    }

    public function testUpdateRejectsAnEmptyName(): void
    {
        $id = $this->repo->findBySlug('jc')['id'];

        foreach (['', '   '] as $emptyName) {
            try {
                $this->repo->update($id, $emptyName, 'detective', 'slate');
                $this->fail('InvalidArgumentException attendue pour un nom vide.');
            } catch (\InvalidArgumentException $e) {
                // attendu
            }
        }

        $unchanged = $this->repo->find($id);
        $this->assertSame('JC', $unchanged['name']);
        $this->assertSame('detective', $unchanged['avatar']);
        $this->assertSame('slate', $unchanged['color']);
    }
}
