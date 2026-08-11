<?php
namespace App\Tests\Utils;

use App\Utils\Access;
use PHPUnit\Framework\TestCase;

class AccessTest extends TestCase
{
    public function testAParentManagesBothLists(): void
    {
        $this->assertTrue(Access::canManagePool('adult', 'adult'));
        $this->assertTrue(
            Access::canManagePool('adult', 'kid'),
            'Un parent peut ajouter un film dans la liste des filles'
        );
    }

    public function testADaughterManagesOnlyHerOwnList(): void
    {
        $this->assertTrue(Access::canManagePool('kid', 'kid'));
        $this->assertFalse(
            Access::canManagePool('kid', 'adult'),
            'Une fille ne touche pas la liste des parents, elle la consulte et vote seulement'
        );
    }

    public function testAnUnknownPoolIsAlwaysRefused(): void
    {
        foreach (['adult', 'kid'] as $side) {
            $this->assertFalse(Access::canManagePool($side, 'grands-parents'));
            $this->assertFalse(Access::canManagePool($side, ''));
        }
    }

    public function testManageablePoolsListsThemInDisplayOrder(): void
    {
        $this->assertSame(['adult', 'kid'], Access::manageablePools('adult'));
        $this->assertSame(['kid'], Access::manageablePools('kid'));
    }

    public function testAnUnknownSideGetsTheDaughtersRestriction(): void
    {
        // Par prudence : un camp inattendu ne doit jamais ouvrir la liste des parents.
        $this->assertFalse(Access::canManagePool('inconnu', 'adult'));
        $this->assertSame(['kid'], Access::manageablePools('inconnu'));
    }
}
