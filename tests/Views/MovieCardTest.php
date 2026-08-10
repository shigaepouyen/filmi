<?php
namespace App\Tests\Views;

use App\Utils\ViewRenderer;
use PHPUnit\Framework\TestCase;

class MovieCardTest extends TestCase
{
    private function movie(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'title' => 'Le Voyage de Chihiro',
            'year' => 2001,
            'runtime' => 125,
            'poster_url' => 'https://image.tmdb.org/t/p/w342/chihiro.jpg',
            'bet_type' => 'safe',
            'memo' => 'Pour leur montrer Miyazaki',
            'certification' => 'Tous publics',
            'providers' => '["Netflix","Max"]',
            'proposer_name' => 'JC',
            'proposer_avatar' => 'detective',
            'proposer_color' => 'slate',
            'vote_count' => 2,
            'voter_names' => 'Zoé, Soline',
        ], $overrides);
    }

    private function render(array $movie, array $myVotes = []): string
    {
        return ViewRenderer::component('movie_card', [
            'movie' => $movie,
            'startTime' => '19:15',
            'myVotes' => $myVotes,
            'profile' => ['id' => 1, 'name' => 'JC'],
        ]);
    }

    public function testShowsTitleYearProposerAndVoteCount(): void
    {
        $html = $this->render($this->movie());

        $this->assertStringContainsString('Le Voyage de Chihiro', $html);
        $this->assertStringContainsString('2001', $html);
        $this->assertStringContainsString('JC', $html);
        $this->assertStringContainsString('2', $html);
    }

    public function testShowsRuntimeAndComputedEndTime(): void
    {
        $html = $this->render($this->movie());

        $this->assertStringContainsString('2 h 05', $html);
        $this->assertStringContainsString('21:20', $html);
    }

    public function testShowsProvidersAndCertification(): void
    {
        $html = $this->render($this->movie());

        $this->assertStringContainsString('Netflix', $html);
        $this->assertStringContainsString('Max', $html);
        $this->assertStringContainsString('Tous publics', $html);
    }

    public function testUnknownRuntimeDoesNotShowAnEndTime(): void
    {
        $html = $this->render($this->movie(['runtime' => null]));

        $this->assertStringContainsString('durée inconnue', $html);
        $this->assertStringNotContainsString('fin vers', $html);
    }

    public function testEmptyProvidersShowsTheAbsenceExplicitly(): void
    {
        $html = $this->render($this->movie(['providers' => '[]']));

        $this->assertStringContainsString('aucune plateforme connue', $html);
    }

    public function testMalformedProvidersJsonDoesNotCrash(): void
    {
        $html = $this->render($this->movie(['providers' => 'pas du json']));

        $this->assertStringContainsString('aucune plateforme connue', $html);
    }

    public function testMyVoteIsReflectedInTheInitialState(): void
    {
        $voted = $this->render($this->movie(), [7]);
        $notVoted = $this->render($this->movie(), [99]);

        $this->assertStringContainsString('voted: true', $voted);
        $this->assertStringContainsString('voted: false', $notVoted);
    }

    public function testTitleIsEscaped(): void
    {
        $html = $this->render($this->movie(['title' => '<script>alert(1)</script>']));

        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function testMemoIsRenderedAndEscaped(): void
    {
        $html = $this->render($this->movie(['memo' => 'Parce que "ça" compte']));

        $this->assertStringContainsString('&quot;', $html);
    }

    public function testKidPoolMovieWithoutBetTypeShowsNoTag(): void
    {
        $html = $this->render($this->movie(['bet_type' => null]));

        $this->assertStringNotContainsString('valeur sûre', $html);
        $this->assertStringNotContainsString('découverte', $html);
    }

    public function testChooseButtonIsAbsentByDefault(): void
    {
        $this->assertStringNotContainsString('celui-là ce soir', $this->render($this->movie()));
    }

    public function testChooseButtonAppearsWhenChoosable(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'choosable' => true,
        ]);

        $this->assertStringContainsString('celui-là ce soir', $html);
        $this->assertStringContainsString('name="action" value="choose"', $html);
    }
}
