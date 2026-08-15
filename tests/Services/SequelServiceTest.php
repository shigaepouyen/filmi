<?php
namespace App\Tests\Services;

use App\Services\SequelService;
use PHPUnit\Framework\TestCase;

class SequelServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function film(
        int $id,
        string $title,
        ?int $collection = 1078456,
        ?int $rank = null,
        string $status = 'pool',
        int $ignoreOrder = 0
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'collection_id' => $collection,
            'collection_name' => $collection === null ? null : 'Violent Night - Saga',
            'collection_rank' => $rank,
            'status' => $status,
            'ignore_order' => $ignoreOrder,
        ];
    }

    public function testASequelIsBlockedWhileTheFirstFilmWaitsInAList(): void
    {
        $premier = $this->film(1, 'Violent Night', rank: 1);
        $suite = $this->film(2, 'Violent Night 2', rank: 2);

        $bloquant = SequelService::blockedBy($suite, [$premier, $suite]);

        $this->assertNotNull($bloquant);
        $this->assertSame('Violent Night', $bloquant['title']);
    }

    public function testTheFirstFilmIsNeverBlockedByItsOwnSequel(): void
    {
        $premier = $this->film(1, 'Violent Night', rank: 1);
        $suite = $this->film(2, 'Violent Night 2', rank: 2);

        $this->assertNull(SequelService::blockedBy($premier, [$premier, $suite]));
    }

    public function testWatchingTheFirstFilmReleasesTheSequel(): void
    {
        $premier = $this->film(1, 'Violent Night', rank: 1, status: 'watched');
        $suite = $this->film(2, 'Violent Night 2', rank: 2);

        $this->assertNull(SequelService::blockedBy($suite, [$premier, $suite]));
    }

    public function testAnArchivedPredecessorDoesNotBlock(): void
    {
        $premier = $this->film(1, 'Violent Night', rank: 1, status: 'archived');
        $suite = $this->film(2, 'Violent Night 2', rank: 2);

        $this->assertNull(
            SequelService::blockedBy($suite, [$premier, $suite]),
            'Un film archive est sorti du jeu, il ne doit plus retenir sa suite'
        );
    }

    public function testAPredecessorAbsentFromTheListsDoesNotBlock(): void
    {
        // Le premier n'a jamais ete ajoute : on suppose qu'il a ete vu avant Filmi.
        $suite = $this->film(2, 'Violent Night 2', rank: 2);

        $this->assertNull(SequelService::blockedBy($suite, [$suite]));
    }

    public function testTheOldestUnwatchedPredecessorIsTheOneNamed(): void
    {
        $un = $this->film(1, 'John Wick', collection: 404609, rank: 1);
        $deux = $this->film(2, 'John Wick 2', collection: 404609, rank: 2);
        $trois = $this->film(3, 'John Wick 3', collection: 404609, rank: 3);

        $bloquant = SequelService::blockedBy($trois, [$un, $deux, $trois]);

        $this->assertSame('John Wick', $bloquant['title'], 'C est par le premier qu il faut commencer');
    }

    public function testABlockingPredecessorCountsEvenFromTheOtherList(): void
    {
        // L'ordre de visionnage est une propriete de la famille, pas d'une liste :
        // le pool n'entre pas dans la regle, seul le statut compte.
        $premier = $this->film(1, 'Violent Night', rank: 1);
        $premier['pool'] = 'adult';
        $suite = $this->film(2, 'Violent Night 2', rank: 2);
        $suite['pool'] = 'kid';

        $this->assertNotNull(SequelService::blockedBy($suite, [$premier, $suite]));
    }

    public function testIgnoreOrderReleasesTheFilm(): void
    {
        $premier = $this->film(1, 'La Guerre des etoiles', collection: 10, rank: 1);
        $episodeUn = $this->film(2, 'La Menace fantome', collection: 10, rank: 4, ignoreOrder: 1);

        $this->assertNull(
            SequelService::blockedBy($episodeUn, [$premier, $episodeUn]),
            'L echappatoire existe pour les sagas dont l ordre de sortie n est pas celui de l histoire'
        );
    }

    public function testAFilmWithoutCollectionIsNeitherBlockedNorBlocking(): void
    {
        $sansSaga = $this->film(1, 'Saisi a la main', collection: null, rank: null);
        $autre = $this->film(2, 'Violent Night 2', rank: 2);

        $this->assertNull(SequelService::blockedBy($sansSaga, [$sansSaga, $autre]));
        $this->assertNull(
            SequelService::blockedBy($autre, [$sansSaga, $autre]),
            'Un film sans rang ne peut retenir personne'
        );
    }

    public function testTwoDifferentSagasDoNotInterfere(): void
    {
        $violent = $this->film(1, 'Violent Night', collection: 1078456, rank: 1);
        $wick = $this->film(2, 'John Wick 2', collection: 404609, rank: 2);

        $this->assertNull(SequelService::blockedBy($wick, [$violent, $wick]));
    }

    public function testBlockedIdsListsEveryHeldBackFilm(): void
    {
        $catalogue = [
            $this->film(1, 'Violent Night', rank: 1),
            $this->film(2, 'Violent Night 2', rank: 2),
            $this->film(3, 'John Wick', collection: 404609, rank: 1, status: 'watched'),
            $this->film(4, 'John Wick 2', collection: 404609, rank: 2),
        ];

        $this->assertSame([2], SequelService::blockedIds($catalogue));
    }
}
