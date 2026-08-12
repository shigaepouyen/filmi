<?php
namespace App\Tests\Services;

use App\Services\ScheduleService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ScheduleServiceTest extends TestCase
{
    public function testNextSaturdayFromMidweek(): void
    {
        // 2026-08-12 est un mercredi.
        $next = ScheduleService::nextSeanceDate(new DateTimeImmutable('2026-08-12'));

        $this->assertSame('2026-08-15', $next->format('Y-m-d'));
        $this->assertSame('Sat', $next->format('D'));
    }

    public function testSaturdayItselfIsTheNextSeance(): void
    {
        $next = ScheduleService::nextSeanceDate(new DateTimeImmutable('2026-08-15'));

        $this->assertSame('2026-08-15', $next->format('Y-m-d'));
    }

    public function testSundayRollsToTheFollowingSaturday(): void
    {
        $next = ScheduleService::nextSeanceDate(new DateTimeImmutable('2026-08-16'));

        $this->assertSame('2026-08-22', $next->format('Y-m-d'));
    }

    public function testParentsOpenWhenThereIsNoHistory(): void
    {
        $this->assertSame('adult', ScheduleService::defaultChooserSide([]));
    }

    public function testAlternatesFromTheLastDoneSeance(): void
    {
        $seances = [
            ['status' => 'done', 'chooser_side' => 'adult'],
            ['status' => 'done', 'chooser_side' => 'kid'],
        ];

        $this->assertSame('kid', ScheduleService::defaultChooserSide($seances));
    }

    public function testPlannedSeancesAreIgnored(): void
    {
        $seances = [
            ['status' => 'planned', 'chooser_side' => 'kid'],
            ['status' => 'done', 'chooser_side' => 'kid'],
        ];

        $this->assertSame('adult', ScheduleService::defaultChooserSide($seances));
    }

    public function testSkippedSeanceCarriesItsSideOverIntact(): void
    {
        $seances = [
            ['status' => 'skipped', 'chooser_side' => 'kid'],
            ['status' => 'done', 'chooser_side' => 'adult'],
        ];

        $this->assertSame('kid', ScheduleService::defaultChooserSide($seances));
    }

    public function testTwoSkippedSeancesInARowStillCarryTheSameSide(): void
    {
        $seances = [
            ['status' => 'skipped', 'chooser_side' => 'adult'],
            ['status' => 'skipped', 'chooser_side' => 'adult'],
            ['status' => 'done', 'chooser_side' => 'kid'],
        ];

        $this->assertSame('adult', ScheduleService::defaultChooserSide($seances));
    }

    public function testDerogationDoesNotBreakTheFollowingAlternation(): void
    {
        // Deux semaines filles d'affilée, la seconde par dérogation.
        $seances = [
            ['status' => 'done', 'chooser_side' => 'kid'],
            ['status' => 'done', 'chooser_side' => 'kid'],
            ['status' => 'done', 'chooser_side' => 'adult'],
        ];

        $this->assertSame('adult', ScheduleService::defaultChooserSide($seances));
    }

    public function testOnlySkippedHistoryFallsBackOnThatSide(): void
    {
        $seances = [['status' => 'skipped', 'chooser_side' => 'kid']];

        $this->assertSame('kid', ScheduleService::defaultChooserSide($seances));
    }

    public function testOpposite(): void
    {
        $this->assertSame('kid', ScheduleService::opposite('adult'));
        $this->assertSame('adult', ScheduleService::opposite('kid'));
        $this->assertSame('adult', ScheduleService::opposite('n importe quoi'));
    }

    public function testGoingAgainstTheNaturalAlternationIsADerogation(): void
    {
        $this->assertTrue(ScheduleService::isDerogation('kid', 'adult'));
        $this->assertTrue(ScheduleService::isDerogation('adult', 'kid'));
    }

    public function testFollowingTheNaturalAlternationIsNotADerogation(): void
    {
        $this->assertFalse(ScheduleService::isDerogation('adult', 'adult'));
        $this->assertFalse(ScheduleService::isDerogation('kid', 'kid'));
    }

    public function testFlippingTwiceCancelsTheDerogation(): void
    {
        // Le cas reel : on inverse le tour, puis on se ravise. Le drapeau ne doit
        // pas rester leve, sinon le badge devient permanent et le palmares compte
        // une derogation qui n'a pas eu lieu.
        $naturel = 'adult';
        $apresPremiereInversion = ScheduleService::opposite($naturel);
        $apresSecondeInversion = ScheduleService::opposite($apresPremiereInversion);

        $this->assertTrue(ScheduleService::isDerogation($apresPremiereInversion, $naturel));
        $this->assertFalse(ScheduleService::isDerogation($apresSecondeInversion, $naturel));
    }
}
