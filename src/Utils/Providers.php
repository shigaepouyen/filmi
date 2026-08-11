<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Normalisation des plateformes de streaming TMDb.
 *
 * `movies.providers` existe sous deux formes en base :
 *   - ancienne : une liste de noms ["Netflix","Netflix Standard with Ads"]
 *   - nouvelle : une liste d'objets [{"id":8,"name":"Netflix","logo":"/xxx.jpg"}]
 * Les 18 films existants au moment de la v2 restent dans l'ancienne forme jusqu'à
 * leur prochain rafraîchissement TMDb : toute lecture doit accepter les deux.
 */
final class Providers
{
    private const LOGO_BASE = 'https://image.tmdb.org/t/p/w92';

    /** Suffixes de revente : le fournisseur commercial n'est pas la marque. */
    private const RESALE_SUFFIXES = [' Amazon Channel', ' Apple TV Channel'];

    /** Suffixes d'offre : la formule n'est pas la marque. Ordre important, le plus long d'abord. */
    private const OFFER_SUFFIXES = [' Standard with Ads', ' with Ads', ' Premium'];

    private const ALIASES = [
        'Disney Plus' => 'Disney+',
        'Paramount Plus' => 'Paramount+',
        'Netflix Standard' => 'Netflix',
        'Apple TV Plus' => 'Apple TV+',
    ];

    /**
     * Accepte l'une ou l'autre forme lue en base (déjà décodée du JSON) et renvoie
     * toujours la forme nouvelle : une liste de ['id' => ?int, 'name' => string, 'logo' => ?string].
     */
    public static function normalise(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $name = trim($item);
                if ($name === '') {
                    continue;
                }
                $result[] = ['id' => null, 'name' => $name, 'logo' => null];
                continue;
            }

            if (is_array($item)) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $result[] = [
                    'id' => isset($item['id']) ? (int) $item['id'] : null,
                    'name' => $name,
                    'logo' => $item['logo'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Réduit un nom d'offre commercial à sa marque, selon les règles établies sur
     * les 30 noms réellement présents en production (voir le plan v2).
     */
    public static function brand(string $name): string
    {
        $value = trim($name);

        foreach (self::RESALE_SUFFIXES as $suffix) {
            if (str_ends_with($value, $suffix)) {
                $value = substr($value, 0, -strlen($suffix));
                break;
            }
        }

        foreach (self::OFFER_SUFFIXES as $suffix) {
            if (str_ends_with($value, $suffix)) {
                $value = substr($value, 0, -strlen($suffix));
                break;
            }
        }

        $value = trim($value);

        return self::ALIASES[$value] ?? $value;
    }

    /** Clé de comparaison insensible à la casse et aux espaces répétés. */
    public static function brandKey(string $brand): string
    {
        $collapsed = (string) preg_replace('/\s+/u', ' ', trim($brand));

        return mb_strtolower($collapsed, 'UTF-8');
    }

    public static function logoUrl(?string $logoPath): ?string
    {
        return $logoPath === null || $logoPath === '' ? null : self::LOGO_BASE . $logoPath;
    }

    /**
     * Marques distinctes présentes dans une liste de plateformes (forme ancienne ou
     * nouvelle, mélange toléré), avec un logo si au moins une occurrence en fournit un.
     *
     * @return list<array{brand: string, logo: ?string}> trié par nom de marque
     */
    public static function brands(array $providers): array
    {
        $byKey = [];

        foreach ($providers as $provider) {
            $name = is_string($provider) ? $provider : (string) ($provider['name'] ?? '');
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $brand = self::brand($name);
            $key = self::brandKey($brand);
            $logo = is_array($provider) ? ($provider['logo'] ?? null) : null;

            if (!isset($byKey[$key])) {
                $byKey[$key] = ['brand' => $brand, 'logo' => $logo];
            } elseif ($byKey[$key]['logo'] === null && $logo !== null) {
                $byKey[$key]['logo'] = $logo;
            }
        }

        $result = array_values($byKey);
        usort($result, static fn (array $a, array $b): int => strcasecmp($a['brand'], $b['brand']));

        return $result;
    }

    /**
     * Périmètre d'abonnement : vrai si aucune des plateformes du film n'y figure.
     * Toujours faux si le périmètre n'est pas configuré (pas d'avertissement dans ce cas).
     *
     * @param list<array{id: ?int, name: string, logo: ?string}> $providers déjà normalisés
     * @param list<string> $subscribedBrands
     */
    public static function needsWarning(array $providers, array $subscribedBrands): bool
    {
        if ($subscribedBrands === []) {
            return false;
        }

        $subscribedKeys = array_map([self::class, 'brandKey'], $subscribedBrands);

        foreach ($providers as $provider) {
            $name = is_string($provider) ? $provider : (string) ($provider['name'] ?? '');
            $brandKey = self::brandKey(self::brand($name));
            if (in_array($brandKey, $subscribedKeys, true)) {
                return false;
            }
        }

        return true;
    }
}
