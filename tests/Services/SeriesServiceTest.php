<?php
namespace App\Tests\Services;

use App\Services\SeriesService;
use PHPUnit\Framework\TestCase;

class SeriesServiceTest extends TestCase
{
    /** Deux saisons de 8 épisodes, durées réalistes façon Heartstopper. */
    private function episodes(): array
    {
        $episodes = [];
        $number = 0;
        foreach ([1 => [27, 33, 30, 29, 31, 28, 30, 32], 2 => [26, 34, 29, 31, 27, 33, 30, 28]] as $season => $runtimes) {
            foreach ($runtimes as $i => $runtime) {
                $number++;
                $episodes[] = [
                    'number' => $number,
                    'season' => $season,
                    'episode_in_season' => $i + 1,
                    'title' => 'S' . $season . 'E' . ($i + 1),
                    'runtime' => $runtime,
                ];
            }
        }

        return $episodes;
    }

    public function testFirstEveningTakesTheConfiguredNumberOfEpisodes(): void
    {
        $evening = SeriesService::nextEvening($this->episodes(), 0, 2);

        $this->assertSame(1, $evening['from']);
        $this->assertSame(2, $evening['to']);
        $this->assertSame('S1E1 à S1E2', $evening['label']);
        $this->assertSame(27 + 33, $evening['total_runtime']);
        $this->assertFalse($evening['finishes']);
    }

    public function testASingleEpisodeEveningIsLabelledWithoutARange(): void
    {
        $evening = SeriesService::nextEvening($this->episodes(), 2, 1);

        $this->assertSame(3, $evening['from']);
        $this->assertSame(3, $evening['to']);
        $this->assertSame('S1E3', $evening['label'], 'Une seule soiree ne doit pas afficher "a S1E3"');
        $this->assertSame(30, $evening['total_runtime']);
        $this->assertFalse($evening['finishes']);
    }

    public function testAnEveningCanCrossASeasonBoundary(): void
    {
        // 7 épisodes déjà vus sur les 8 de la saison 1 : il en reste un, la
        // soirée à 2 épisodes prend S1E8 puis enchaîne sur S2E1.
        $evening = SeriesService::nextEvening($this->episodes(), 7, 2);

        $this->assertSame(8, $evening['from']);
        $this->assertSame(9, $evening['to']);
        $this->assertSame('S1E8 à S2E1', $evening['label']);
        $this->assertSame(32 + 26, $evening['total_runtime']);
        $this->assertFalse($evening['finishes']);
    }

    public function testTheLastEveningCanBeShorterThanConfiguredAndFinishesTheSeries(): void
    {
        // 15 épisodes vus sur 16, un seul épisode reste pour une soirée réglée à 2.
        $evening = SeriesService::nextEvening($this->episodes(), 15, 2);

        $this->assertSame(16, $evening['from']);
        $this->assertSame(16, $evening['to']);
        $this->assertSame('S2E8', $evening['label']);
        $this->assertSame(28, $evening['total_runtime']);
        $this->assertTrue($evening['finishes']);
    }

    public function testAFullEveningThatLandsExactlyOnTheLastEpisodeAlsoFinishes(): void
    {
        $evening = SeriesService::nextEvening($this->episodes(), 14, 2);

        $this->assertSame(15, $evening['from']);
        $this->assertSame(16, $evening['to']);
        $this->assertTrue($evening['finishes']);
    }

    public function testAnEveningInTheMiddleOfTheSeriesDoesNotFinish(): void
    {
        $evening = SeriesService::nextEvening($this->episodes(), 4, 3);

        $this->assertSame(5, $evening['from']);
        $this->assertSame(7, $evening['to']);
        $this->assertFalse($evening['finishes']);
    }
}
