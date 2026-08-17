<?php
namespace App\Tests\Services;

use App\Services\ChoiceRules;
use PHPUnit\Framework\TestCase;

class ChoiceRulesTest extends TestCase
{
    public function testTheRealTrioIsAccepted(): void
    {
        $this->assertTrue(ChoiceRules::accepts([7, 8, 9], 8, [7, 8, 9], []));
    }

    public function testOrderDoesNotMatter(): void
    {
        $this->assertTrue(ChoiceRules::accepts([9, 7, 8], 9, [7, 8, 9], []));
    }

    public function testAFilmSmuggledIntoTheShortlistIsRefused(): void
    {
        $this->assertFalse(
            ChoiceRules::accepts([7, 8, 9, 42], 42, [7, 8, 9], []),
            'Un film ajoute a la main ne doit pas pouvoir etre retenu'
        );
    }

    public function testAShortlistNarrowedToASingleFilmIsRefused(): void
    {
        // Sinon on choisit un film en effacant les deux autres, ce qui leur
        // epargne le cooldown auquel une proposition non retenue a droit.
        $this->assertFalse(ChoiceRules::accepts([8], 8, [7, 8, 9], []));
    }

    public function testAChosenFilmOutsideItsOwnShortlistIsRefused(): void
    {
        $this->assertFalse(ChoiceRules::accepts([7, 8, 9], 42, [7, 8, 9], []));
    }

    public function testAnEmptyShortlistIsRefused(): void
    {
        $this->assertFalse(ChoiceRules::accepts([], 8, [7, 8, 9], []));
        $this->assertFalse(ChoiceRules::accepts([], 8, null, [7, 8, 9]));
    }

    public function testAMissingScoreOrChosenIdIsRefused(): void
    {
        $this->assertFalse(ChoiceRules::accepts([7, 8, 9], 0, [7, 8, 9], []));
    }

    public function testWithoutSessionMemoryOnlyDrawableFilmsPass(): void
    {
        // Session expiree entre le tirage et le choix : on ne connait plus le trio
        // exact, mais un film hors pool des parents reste refuse.
        $this->assertTrue(ChoiceRules::accepts([7, 8, 9], 8, null, [7, 8, 9, 10, 11]));
        $this->assertFalse(ChoiceRules::accepts([7, 8, 99], 8, null, [7, 8, 9, 10, 11]));
    }

    public function testWithoutSessionMemoryASubsetIsToleratedRatherThanBlocking(): void
    {
        // On ne peut pas exiger l'egalite : le trio exact est justement ce qu'on a
        // perdu. La famille doit pouvoir valider son choix du samedi soir.
        $this->assertTrue(ChoiceRules::accepts([7, 8], 7, null, [7, 8, 9, 10]));
    }
}
