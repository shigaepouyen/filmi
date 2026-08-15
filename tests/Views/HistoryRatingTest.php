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
            'movie_title' => 'Brazil',
            'movie_poster' => null,
            'episodes_label' => null,
            'proposer_name' => 'JC',
        ], $overrides);
    }

    private function render(array $seance, array $myRatings = []): string
    {
        return ViewRenderer::page('history', [
            'seances' => [$seance],
            'vetoes' => [],
            'myRatings' => $myRatings,
        ]);
    }

    public function testAWatchedSeanceOffersTheFiveButtons(): void
    {
        $html = $this->render($this->seance());

        $this->assertStringContainsString('filmiRating(12, null, 4.5)', $html);
        foreach ([1, 2, 3, 4, 5] as $etoile) {
            $this->assertStringContainsString("pick({$etoile})", $html);
        }
    }

    public function testMyExistingRatingIsHandedToTheComponent(): void
    {
        $html = $this->render($this->seance(), [12 => 3]);

        $this->assertStringContainsString('filmiRating(12, 3, 4.5)', $html);
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

    public function testTheReplacementConfirmationIsPresent(): void
    {
        $html = $this->render($this->seance(), [12 => 2]);

        $this->assertStringContainsString('confirmReplace()', $html);
        $this->assertStringContainsString('Remplacer ta note', $html);
    }
}
