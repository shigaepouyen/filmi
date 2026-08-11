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

    public function testCardLinksToTheDetailedMoviePage(): void
    {
        $html = $this->render($this->movie(['id' => 42]));

        $this->assertStringContainsString('href="/movie.php?id=42"', $html);
    }

    public function testSynopsisIsShownAndTruncatedWhenLong(): void
    {
        $long = str_repeat('Une histoire de renard fantastique. ', 10);
        $html = $this->render($this->movie(['overview' => $long]));

        $this->assertStringContainsString('…', $html);
        $this->assertStringNotContainsString(trim($long), $html);
    }

    public function testShortSynopsisIsShownWithoutTruncation(): void
    {
        $html = $this->render($this->movie(['overview' => 'Court résumé.']));

        $this->assertStringContainsString('Court résumé.', $html);
        $this->assertStringNotContainsString('Court résumé.…', $html);
    }

    public function testNoWarningWhenNoSubscriptionIsConfigured(): void
    {
        $html = $this->render($this->movie(['providers' => '["Disney+"]']));

        $this->assertStringNotContainsString('hors abonnement', $html);
    }

    public function testWarningAppearsWhenProvidersAreOutsideTheSubscribedBrands(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(['providers' => '["Disney+"]']),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'subscribedBrands' => ['Netflix'],
        ]);

        $this->assertStringContainsString('hors abonnement', $html);
    }

    public function testNoWarningWhenAProviderIsSubscribed(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(['providers' => '["Netflix","Max"]']),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'subscribedBrands' => ['Netflix'],
        ]);

        $this->assertStringNotContainsString('hors abonnement', $html);
    }

    /**
     * Les 20 plateformes réellement renvoyées par TMDb pour "Orgueil et
     * Préjugés" en production (location/achat mêlés aux abonnements). Sans
     * plafonnement, la carte affichait une puce par plateforme et poussait le
     * titre, le synopsis et le bouton de vote hors de l'écran sur mobile.
     */
    private function orgueilEtPrejugesProviders(): string
    {
        $names = [
            'Canal+', 'Sooner', 'Molotov TV', 'Cine+ OCS Amazon Channel', 'Paramount Plus',
            'SFR Play', 'Paramount+ Amazon Channel', 'HBO Max', 'Paramount Plus Premium',
            'HBO Max Amazon Channel', 'Apple TV Store', 'Google Play Movies', 'Canal VOD',
            'Orange VOD', 'YouTube', 'Rakuten TV', 'Amazon Video', 'Pathé Home',
            'VIVA by videofutur', 'Premiere Max',
        ];

        return json_encode(array_map(
            static fn (string $name): array => ['id' => null, 'name' => $name, 'logo' => '/logo.jpg'],
            $names
        ), JSON_UNESCAPED_UNICODE);
    }

    public function testTwentyRealPlatformsWithNoSubscriptionShowThemAll(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(['title' => 'Orgueil et Préjugés', 'providers' => $this->orgueilEtPrejugesProviders()]),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'subscribedBrands' => [],
        ]);

        // Etat transitoire, avant que la famille ait coche ses abonnements : les
        // 20 plateformes reelles se reduisent a 17 marques distinctes une fois les
        // declinaisons Paramount+ et HBO Max fusionnees, et la carte les montre
        // toutes. C'est volontairement non plafonne : le filtre utile est le
        // perimetre d'abonnement, pas une limite arbitraire.
        $this->assertSame(17, substr_count($html, 'bg-emerald-500/20'));
        $this->assertStringNotContainsString('hors abonnement', $html);
    }

    public function testASubscriptionCutsTheTwentyPlatformsDownToWhatMatters(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(['title' => 'Orgueil et Préjugés', 'providers' => $this->orgueilEtPrejugesProviders()]),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'subscribedBrands' => ['Netflix', 'Canal+', 'Disney+', 'HBO Max'],
        ]);

        // C'est le vrai remede au mur de pastilles : quatre abonnements coches,
        // quatre pastilles au maximum, sans plafond a maintenir.
        $this->assertLessThanOrEqual(4, substr_count($html, 'bg-emerald-500/20'));
        $this->assertStringNotContainsString('hors abonnement', $html);
    }

    public function testTwentyRealPlatformsWithASubscriptionShowOnlyTheMatchingOnes(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(['title' => 'Orgueil et Préjugés', 'providers' => $this->orgueilEtPrejugesProviders()]),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'subscribedBrands' => ['Canal+', 'HBO Max'],
        ]);

        $this->assertSame(2, substr_count($html, 'bg-emerald-500/20'));
        $this->assertStringContainsString('Canal+', $html);
        $this->assertStringContainsString('HBO Max', $html);
        $this->assertStringNotContainsString('YouTube', $html);
        $this->assertStringNotContainsString('hors abonnement', $html);
    }

    public function testTwentyRealPlatformsOutsideTheSubscriptionShowNoChipOnlyTheWarning(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(['title' => 'Orgueil et Préjugés', 'providers' => $this->orgueilEtPrejugesProviders()]),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'subscribedBrands' => ['Netflix'],
        ]);

        $this->assertSame(0, substr_count($html, 'bg-emerald-500/20'));
        $this->assertStringContainsString('hors abonnement', $html);
        $this->assertStringNotContainsString('+', explode('hors abonnement', $html)[0] ?? '');
    }

    public function testTheWholeCardIsClickableAndTheControlsStayAboveTheLink(): void
    {
        $html = $this->render($this->movie());

        // Regression : le lien couvrant la carte etait en z-0, donc peint SOUS le
        // titre et l'affiche (un positionne en z-index auto passe apres un z-0 dans
        // l'ordre du DOM). Taper sur la carte ne faisait rien et la fiche du film
        // etait inatteignable. Le lien doit etre au-dessus du contenu, et les
        // commandes interactives au-dessus du lien.
        $this->assertMatchesRegularExpression(
            '/<a [^>]*class="absolute inset-0 z-10[^"]*"/',
            $html,
            'Le lien de la carte doit couvrir la carte au-dessus du contenu'
        );
        $this->assertStringNotContainsString('absolute inset-0 z-0', $html);
        $this->assertStringContainsString('relative z-20', $html, 'La rangee de vote doit rester cliquable');
    }

    public function testTheChooseButtonStaysAboveTheCardLink(): void
    {
        $html = ViewRenderer::component('movie_card', [
            'movie' => $this->movie(),
            'startTime' => '19:15',
            'myVotes' => [],
            'profile' => ['id' => 1, 'name' => 'JC'],
            'choosable' => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/<form method="post" class="relative z-20[^"]*">\s*<input type="hidden" name="csrf"/',
            $html,
            'Le bouton de choix du soir ne doit pas etre avale par le lien de la carte'
        );
    }
}
