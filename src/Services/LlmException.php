<?php
declare(strict_types=1);

namespace App\Services;

/** Echec d'un appel au modele : reseau, quota, refus ou reponse inexploitable. */
final class LlmException extends \RuntimeException
{
}
