<?php
namespace App\Tests\Services;

use App\Services\RatingRules;
use PHPUnit\Framework\TestCase;

class RatingRulesTest extends TestCase
{
    public function testAFilmSeenIsRatable(): void
    {
        $this->assertTrue(RatingRules::isRatable('done', 'film', 'watched'));
    }

    public function testASeanceNotYetHeldIsNotRatable(): void
    {
        $this->assertFalse(RatingRules::isRatable('planned', 'film', 'pool'));
        $this->assertFalse(RatingRules::isRatable('skipped', null, null));
    }

    public function testAnIntermediateSeriesEveningIsNotRatable(): void
    {
        // Le piege : la soiree est bien en 'done', c'est la serie qui n'est pas
        // finie. Juger sur le seul statut de la seance laisserait passer une note
        // par soiree au lieu d'une note sur l'oeuvre.
        $this->assertFalse(RatingRules::isRatable('done', 'series', 'pool'));
    }

    public function testTheLastEveningOfASeriesIsRatable(): void
    {
        $this->assertTrue(RatingRules::isRatable('done', 'series', 'watched'));
    }

    public function testAMissingKindIsTreatedAsAFilm(): void
    {
        $this->assertTrue(RatingRules::isRatable('done', null, 'watched'));
    }

    public function testAnArchivedSeriesIsNotRatable(): void
    {
        $this->assertFalse(RatingRules::isRatable('done', 'series', 'archived'));
    }
}
