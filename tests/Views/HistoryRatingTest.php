<?php
namespace App\Tests\Views;

use App\Utils\ViewRenderer;
use PHPUnit\Framework\TestCase;

class HistoryRatingTest extends TestCase
{
    private function seance(array $overrides = []): array
    {
        return array_merge([
            'id' => 12,
            'date' => '2026-07-04',
            'status' => 'done',
            'chooser_side' => 'adult',
            'derogation' => 0,
            'derogation_note' => null,
            'veto_count' => 0,
            'avg_score' => 4.5,
            'movie_kind' => 'film',
            'movie_status' => 'watched',
            'movie_title' => 'Brazil',
            'movie_poster' => null,
            'episodes_label' => null,
            'proposer_name' => 'JC',
        ], $overrides);
    }

    private function render(array $seance, array $myRatings = [], array $mySkipped = []): string
    {
        return ViewRenderer::page('history', [
            'seances' => [$seance],
            'vetoes' => [],
            'myRatings' => $myRatings,
            'mySkippedRatings' => $mySkipped,
        ]);
    }

    public function testAWatchedSeanceOffersTheFiveButtons(): void
    {
        $html = $this->render($this->seance());

        $this->assertStringContainsString('filmiRating(12, null, 4.5, false)', $html);
        foreach ([1, 2, 3, 4, 5] as $etoile) {
            $this->assertStringContainsString("pick({$etoile})", $html);
        }
    }

    public function testMyExistingRatingIsHandedToTheComponent(): void
    {
        $html = $this->render($this->seance(), [12 => 3]);

        $this->assertStringContainsString('filmiRating(12, 3, 4.5, false)', $html);
    }

    public function testTheButtonsSitOutsideTheLinkToTheSeance(): void
    {
        $html = $this->render($this->seance());

        $lien = substr($html, (int) strpos($html, '<a href="/seance.php?id=12"'));
        $finLien = substr($lien, 0, (int) strpos($lien, '</a>'));

        $this->assertStringNotContainsString(
            'pick(',
            $finLien,
            'Un bouton dans le lien ferait naviguer la page au lieu de noter'
        );
    }

    public function testASeanceWithoutFilmHasNoRatingBlock(): void
    {
        $html = $this->render($this->seance([
            'status' => 'skipped',
            'movie_title' => null,
            'avg_score' => null,
        ]));

        // "pick(" apparait dans le script partage : on teste l'absence du bloc lui-meme.
        $this->assertStringNotContainsString('filmiRating(', $html);
        $this->assertStringNotContainsString('@click="pick(', $html);
    }

    public function testAPlannedSeanceCannotBeRatedYet(): void
    {
        $html = $this->render($this->seance(['status' => 'planned', 'avg_score' => null]));

        $this->assertStringNotContainsString('filmiRating(', $html);
    }

    public function testAnIntermediateSeriesEveningOffersNoRating(): void
    {
        $html = $this->render($this->seance([
            'movie_kind' => 'series',
            'movie_status' => 'pool',
            'episodes_label' => 'épisodes 3 à 5',
            'avg_score' => null,
        ]));

        $this->assertStringNotContainsString('filmiRating(', $html);
    }

    public function testTheCrossClosesTheLineOnlyWhenNothingIsRatedYet(): void
    {
        $vierge = $this->render($this->seance());
        $this->assertStringContainsString('@click="close()"', $vierge);
        $this->assertStringContainsString('x-show="score === null"', $vierge);

        // Le bouton reste dans le DOM mais Alpine le masque : c'est la meme
        // regle des deux cotes, le serveur refusant de fermer une ligne notee.
        $note = $this->render($this->seance(), [12 => 4]);
        $this->assertStringContainsString('filmiRating(12, 4, 4.5, false)', $note);
    }

    public function testAClosedLineIsHandedToTheComponentAndOffersToReopen(): void
    {
        $html = $this->render($this->seance(), [], [12 => true]);

        $this->assertStringContainsString('filmiRating(12, null, 4.5, true)', $html);
        $this->assertStringContainsString('Noter quand même', $html);
        $this->assertStringContainsString('@click="reopen()"', $html);
    }

    public function testTheReplacementConfirmationIsPresent(): void
    {
        $html = $this->render($this->seance(), [12 => 2]);

        $this->assertStringContainsString('confirmReplace()', $html);
        $this->assertStringContainsString('Remplacer ta note', $html);
    }
}
