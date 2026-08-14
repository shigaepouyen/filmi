<?php
declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

/**
 * Rattrapage refusé : date déjà prise, dans le futur, invalide, ou œuvre déjà
 * vue. Les pages l'attrapent pour afficher un message clair au lieu d'une 500.
 */
final class BackfillException extends RuntimeException
{
}
