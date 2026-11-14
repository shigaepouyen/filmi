<?php
namespace App\Tests\Services;

use App\Services\AiPrompt;
use PHPUnit\Framework\TestCase;

class AiPromptTest extends TestCase
{
    private function taste(bool $memos): array
    {
        return [
            'seen' => [['title' => 'Coco', 'year' => 2017, 'kind' => 'film', 'ratings' => ['Parent 1' => 5, 'Fille 2' => 4]]],
            'lists' => [
                'adult' => [['title' => 'Alien', 'year' => 1979, 'kind' => 'film', 'votes' => 2, 'memo' => 'pour la nostalgie']],
                'kid' => [['title' => 'Encanto', 'year' => 2021, 'kind' => 'film', 'votes' => 1]],
            ],
            'vetoes' => [['title' => 'Saw', 'year' => 2004, 'by' => 'Parent 1']],
            'known' => ['Coco (2017)'],
            'memos' => $memos,
        ];
    }

    public function testSeenTitlesAreFlaggedStrongAndMemosWeak(): void
    {
        $text = AiPrompt::user($this->taste(true), ['Ratatouille'], 8);

        $this->assertStringContainsString('POIDS FORT', $text);
        $this->assertStringContainsString('POIDS MOYEN', $text);
        $this->assertStringContainsString('POIDS FAIBLE', $text);
        $this->assertStringContainsString('Coco (2017) : Parent 1 5/5, Fille 2 4/5', $text);
        $this->assertStringContainsString('pour la nostalgie', $text);
        $this->assertStringContainsString('Ratatouille', $text, 'Les idées déjà suggérées sont exclues');
    }

    public function testMemosAreAbsentWhenNotAllowed(): void
    {
        $text = AiPrompt::user($this->taste(false), [], 8);

        $this->assertStringNotContainsString('POIDS FAIBLE', $text);
        $this->assertStringNotContainsString('pour la nostalgie', $text);
    }

    public function testSchemaConstrainsKindAudienceAndBet(): void
    {
        $item = AiPrompt::schema()['properties']['suggestions']['items']['properties'];

        $this->assertSame(['film', 'series'], $item['kind']['enum']);
        $this->assertSame(['all', 'kid', 'adult'], $item['audience']['enum']);
        $this->assertSame(['safe', 'discovery'], $item['bet']['enum']);
    }

    public function testSystemPromptForbidsRepeatingAnythingTheFamilyAlreadyKnows(): void
    {
        // Sans cette regle ferme, un modele leger renvoyait huit films deja en liste sur huit.
        $this->assertStringContainsString('RÈGLE ABSOLUE', AiPrompt::SYSTEM);
        $this->assertStringContainsString('AUCUNE de ces sections', AiPrompt::SYSTEM);
        $this->assertStringContainsString("jamais l'une de ces œuvres elles-mêmes", AiPrompt::SYSTEM);
    }

    private function mixedTaste(): array
    {
        $taste = $this->taste(false);
        $taste['seen'] = [
            ['title' => 'Coco', 'year' => 2017, 'kind' => 'film', 'ratings' => ['Parent 1' => 5, 'Fille 2' => 4]],
            ['title' => 'Alien', 'year' => 1979, 'kind' => 'film', 'ratings' => ['Parent 1' => 5]],
            ['title' => 'Encanto', 'year' => 2021, 'kind' => 'film', 'ratings' => ['Fille 1' => 5]],
            ['title' => 'Sans note', 'year' => 2000, 'kind' => 'film', 'ratings' => []],
        ];

        return $taste;
    }

    public function testEveryoneIsTheDefaultAndKeepsAllTheRatings(): void
    {
        $text = AiPrompt::user($this->mixedTaste(), [], 8);

        $this->assertStringContainsString('Toute la famille', $text);
        $this->assertStringContainsString('Coco (2017) : Parent 1 5/5, Fille 2 4/5', $text);
        $this->assertStringContainsString('Alien (1979)', $text);
        $this->assertStringContainsString('Encanto (2021)', $text);
        $this->assertStringNotContainsString('PRIORITAIRE', $text);
        // Un public inconnu retombe sur toute la famille.
        $this->assertStringContainsString('Toute la famille', AiPrompt::user($this->mixedTaste(), [], 8, 'martiens'));
    }

    public function testAskingForTheParentsKeepsOnlyTheirRatingsAndPrioritisesTheirList(): void
    {
        $text = AiPrompt::user($this->mixedTaste(), [], 8, 'adult');

        $this->assertStringContainsString('UNIQUEMENT les parents', $text);
        $this->assertStringContainsString('Coco (2017) : Parent 1 5/5', $text);
        $this->assertStringNotContainsString('Fille 2 4/5', $text, 'la note des filles est retiree');
        $this->assertStringContainsString('Alien (1979) : Parent 1 5/5', $text);
        $this->assertStringNotContainsString('Encanto (2021) :', $text, 'une oeuvre notee seulement par les filles n oriente pas');
        $this->assertStringContainsString('Sans note (2000) : pas de note', $text);
        $this->assertStringContainsString('Proposées par les parents (PRIORITAIRE pour cette demande)', $text);
        $this->assertStringContainsString('Proposées par les filles (contexte secondaire)', $text);
    }

    public function testAskingForTheGirlsKeepsOnlyTheirRatingsAndPrioritisesTheirList(): void
    {
        $text = AiPrompt::user($this->mixedTaste(), ['Ratatouille'], 8, 'kid');

        $this->assertStringContainsString('UNIQUEMENT les filles', $text);
        $this->assertStringContainsString('ni violentes ni effrayantes', $text);
        $this->assertStringContainsString('Coco (2017) : Fille 2 4/5', $text);
        $this->assertStringNotContainsString('Parent 1 5/5', $text);
        $this->assertStringContainsString('Encanto (2021) : Fille 1 5/5', $text);
        $this->assertStringNotContainsString('Alien (1979) :', $text);
        $this->assertStringContainsString('Proposées par les filles (PRIORITAIRE pour cette demande)', $text);
        // Ce qui est deja connu le reste, quel que soit le public.
        $this->assertStringContainsString('Ratatouille', $text);
    }
}
