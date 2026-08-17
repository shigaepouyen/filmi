<?php
namespace App\Tests\Views;

use App\Utils\Avatars;
use PHPUnit\Framework\TestCase;

class AvatarPickerTest extends TestCase
{
    private function render(string $currentAvatar = 'crabe', string $currentColor = 'violet'): string
    {
        $inputName = 'avatar';

        ob_start();
        require dirname(__DIR__, 2) . '/views/components/avatar_picker.php';

        return (string) ob_get_clean();
    }

    public function testXDataAttributeIsWellFormed(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('x-data="{ choix: \'crabe\' }"', $html);
        $this->assertStringNotContainsString('choix: "', $html);
    }

    public function testEveryAvatarGetsARadioInputAndAnInlineSvg(): void
    {
        $html = $this->render();

        $this->assertSame(20, preg_match_all('/<input type="radio"/', $html));
        $this->assertSame(20, preg_match_all('/<svg /', $html));
    }

    public function testTheCurrentAvatarIsTheOnlyCheckedRadio(): void
    {
        $html = $this->render('blob');

        $this->assertSame(1, preg_match_all('/checked/', $html));
        $this->assertMatchesRegularExpression('/value="blob"[^>]*checked/', $html);
    }

    public function testEveryFamilyLegendIsRendered(): void
    {
        $html = $this->render();

        foreach (Avatars::FAMILIES as $label) {
            $this->assertStringContainsString(htmlspecialchars($label), $html);
        }
    }

    public function testNoAttributeIsBrokenByAnUnescapedQuote(): void
    {
        // Une valeur hostile ne doit jamais casser un attribut. Le texte injecte
        // peut rester visible dans la sortie, ce qui compte est qu'il soit inerte :
        // aucun guillemet non echappe, donc aucun attribut evenementiel vivant.
        $html = $this->render('" onmouseover="alert(1)');

        $this->assertStringNotContainsString('onmouseover="', $html);
        $this->assertStringContainsString('&quot; onmouseover=&quot;', $html);
        $this->assertStringContainsString('class="space-y-6"', $html);
    }
}
