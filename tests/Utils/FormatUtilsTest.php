<?php
namespace App\Tests\Utils;

use App\Utils\FormatUtils;
use PHPUnit\Framework\TestCase;

class FormatUtilsTest extends TestCase
{
    public function testHumanRuntime(): void
    {
        $this->assertSame('1 h 52', FormatUtils::humanRuntime(112));
        $this->assertSame('2 h', FormatUtils::humanRuntime(120));
        $this->assertSame('47 min', FormatUtils::humanRuntime(47));
        $this->assertSame('durée inconnue', FormatUtils::humanRuntime(null));
        $this->assertSame('durée inconnue', FormatUtils::humanRuntime(0));
    }

    public function testEndTime(): void
    {
        $this->assertSame('21:07', FormatUtils::endTime('19:15', 112));
        $this->assertSame('19:15', FormatUtils::endTime('19:15', 0));
        $this->assertNull(FormatUtils::endTime('19:15', null));
    }

    public function testEndTimeCrossesMidnight(): void
    {
        $this->assertSame('00:35', FormatUtils::endTime('22:30', 125));
    }

    public function testEndTimeToleratesMalformedStart(): void
    {
        $this->assertNull(FormatUtils::endTime('n importe quoi', 112));
    }

    public function testFrenchDate(): void
    {
        $this->assertSame('samedi 15 août', FormatUtils::frenchDate('2026-08-15'));
        $this->assertSame('samedi 3 janvier', FormatUtils::frenchDate('2026-01-03'));
    }

    public function testEndTimeRefusesANegativeRuntime(): void
    {
        $this->assertNull(FormatUtils::endTime('19:15', -1));
        $this->assertNull(FormatUtils::endTime('00:10', -1000));
    }

    public function testFrenchDateReturnsTheInputWhenItCannotBeParsed(): void
    {
        $this->assertSame('pas une date', FormatUtils::frenchDate('pas une date'));
        $this->assertSame('', FormatUtils::frenchDate(''));
    }

    public function testFrenchDateRefusesACalendarInvalidDate(): void
    {
        // 2026 n'est pas bissextile et fevrier n'a jamais 30 jours :
        // la date doit ressortir telle quelle, pas etre glissee en mars.
        $this->assertSame('2026-02-30', FormatUtils::frenchDate('2026-02-30'));
        $this->assertSame('2026-13-01', FormatUtils::frenchDate('2026-13-01'));
        $this->assertSame('2026-02-29', FormatUtils::frenchDate('2026-02-29'));
    }

    public function testFrenchDateAcceptsARealLeapDay(): void
    {
        $this->assertSame('jeudi 29 février', FormatUtils::frenchDate('2024-02-29'));
    }

    public function testShortDateAbbreviatesTheMonth(): void
    {
        $this->assertSame('4 juil.', FormatUtils::shortDate('2026-07-04'));
        $this->assertSame('25 déc.', FormatUtils::shortDate('2026-12-25'));
        $this->assertSame('9 août', FormatUtils::shortDate('2026-08-09'));
    }

    public function testShortDateLeavesShortMonthsAlone(): void
    {
        // Tronquer « mai » ou « juin » ne gagnerait rien et donnerait « mai. ».
        $this->assertSame('2 mai', FormatUtils::shortDate('2026-05-02'));
        $this->assertSame('1 juin', FormatUtils::shortDate('2026-06-01'));
        $this->assertSame('3 mars', FormatUtils::shortDate('2026-03-03'));
    }

    public function testShortDateReturnsTheInputWhenItIsNotADate(): void
    {
        // Meme garde que frenchDate : createFromFormat est laxiste et accepterait
        // un 30 fevrier en le decalant au 2 mars.
        $this->assertSame('2026-02-30', FormatUtils::shortDate('2026-02-30'));
        $this->assertSame('n importe quoi', FormatUtils::shortDate('n importe quoi'));
    }
}
