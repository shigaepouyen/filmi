<?php
namespace App\Tests\Services;

use App\Services\DrawException;
use App\Services\DrawService;
use PHPUnit\Framework\TestCase;

class DrawServiceTest extends TestCase
{
    /** @return list<array{id:int,bet_type:string}> */
    private function pool(int $safe, int $discovery): array
    {
        $movies = [];
        $id = 1;
        for ($i = 0; $i < $safe; $i++) {
            $movies[] = ['id' => $id++, 'bet_type' => 'safe'];
        }
        for ($i = 0; $i < $discovery; $i++) {
            $movies[] = ['id' => $id++, 'bet_type' => 'discovery'];
        }

        return $movies;
    }

    public function testReturnsOneSafeThenTwoDiscoveries(): void
    {
        $result = DrawService::pick($this->pool(3, 5));

        $this->assertCount(3, $result['movies']);
        $this->assertSame(
            ['safe', 'discovery', 'discovery'],
            array_column($result['movies'], 'bet_type')
        );
        $this->assertFalse($result['reset']);
    }

    public function testNeverReturnsTheSameMovieTwice(): void
    {
        for ($run = 0; $run < 50; $run++) {
            $ids = array_column(DrawService::pick($this->pool(3, 5))['movies'], 'id');
            $this->assertSame($ids, array_unique($ids));
        }
    }

    public function testCooldownIdsAreNeverReturned(): void
    {
        // 2 safe et 3 discovery, dont 1 safe et 1 discovery en cooldown.
        $result = DrawService::pick($this->pool(2, 3), [1, 3]);

        $ids = array_column($result['movies'], 'id');
        $this->assertNotContains(1, $ids);
        $this->assertNotContains(3, $ids);
    }

    public function testShownIdsAreAvoidedWhenPossible(): void
    {
        // 2 safe (1,2) et 4 discovery (3..6). On a deja montre 1, 3 et 4.
        $result = DrawService::pick($this->pool(2, 4), [], [1, 3, 4]);

        $ids = array_column($result['movies'], 'id');
        $discoveries = array_slice($ids, 1);
        sort($discoveries);

        $this->assertSame(2, $ids[0], 'La valeur sure non montree est la seule possible');
        $this->assertSame([5, 6], $discoveries, 'Les deux decouvertes non montrees, ordre indifferent');
        $this->assertFalse($result['reset']);
    }

    public function testShownIdsAreIgnoredWhenPoolIsExhaustedAndResetIsFlagged(): void
    {
        // Pile de quoi servir le quota, mais tout a déjà été montré.
        $result = DrawService::pick($this->pool(1, 2), [], [1, 2, 3]);

        $this->assertCount(3, $result['movies']);
        $this->assertTrue($result['reset']);
    }

    public function testResetIsFlaggedWhenOnlyOneCategoryIsExhausted(): void
    {
        // 1 safe déjà montré, discoveries en nombre suffisant et non montrées.
        $result = DrawService::pick($this->pool(1, 3), [], [1]);

        $this->assertCount(3, $result['movies']);
        $this->assertTrue($result['reset']);
        $this->assertSame(1, $result['movies'][0]['id']);
    }

    public function testThrowsWhenSafeCategoryIsEmpty(): void
    {
        try {
            DrawService::pick($this->pool(0, 5));
            $this->fail('DrawException attendue');
        } catch (DrawException $e) {
            $this->assertSame(['safe' => 1], $e->missing);
            $this->assertSame(['safe' => 0, 'discovery' => 5], $e->available);
            $this->assertStringContainsString('valeur sûre', $e->getMessage());
        }
    }

    public function testThrowsWhenDiscoveriesAreInsufficient(): void
    {
        try {
            DrawService::pick($this->pool(2, 1));
            $this->fail('DrawException attendue');
        } catch (DrawException $e) {
            $this->assertSame(['discovery' => 1], $e->missing);
            $this->assertStringContainsString('découverte', $e->getMessage());
        }
    }

    public function testThrowsListingBothCategoriesWhenPoolIsEmpty(): void
    {
        try {
            DrawService::pick([]);
            $this->fail('DrawException attendue');
        } catch (DrawException $e) {
            $this->assertSame(['safe' => 1, 'discovery' => 2], $e->missing);
        }
    }

    public function testCooldownCanMakeTheDrawImpossibleAndThatIsReported(): void
    {
        // 1 seul safe, mis en cooldown : le tirage doit échouer, pas contourner le cooldown.
        try {
            DrawService::pick($this->pool(1, 4), [1]);
            $this->fail('DrawException attendue');
        } catch (DrawException $e) {
            $this->assertSame(['safe' => 1], $e->missing);
        }
    }

    public function testUnknownBetTypeIsIgnoredRatherThanCrashing(): void
    {
        $candidates = array_merge($this->pool(1, 2), [['id' => 99, 'bet_type' => null]]);

        $ids = array_column(DrawService::pick($candidates)['movies'], 'id');

        $this->assertNotContains(99, $ids);
    }

    public function testExtraMovieFieldsArePreserved(): void
    {
        $candidates = [
            ['id' => 1, 'bet_type' => 'safe', 'title' => 'Un film sûr'],
            ['id' => 2, 'bet_type' => 'discovery', 'title' => 'Une découverte'],
            ['id' => 3, 'bet_type' => 'discovery', 'title' => 'Une autre'],
        ];

        $result = DrawService::pick($candidates);

        $this->assertSame('Un film sûr', $result['movies'][0]['title']);
    }
}
