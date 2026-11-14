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
}
