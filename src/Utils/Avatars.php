<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Catalogue d'avatars facon borne d'arcade.
 *
 * Chaque sprite est une grille 16x16 dessinee en ASCII, une ligne par rangee, un
 * caractere par pixel. '.' est transparent, '@' prend la couleur de theme du
 * profil, 'k' est le detail sombre (yeux, cockpit, fente).
 *
 * L'idiome est volontairement pauvre, et c'est ce qui le rend lisible : une
 * silhouette trapue, des membres de deux pixels d'epaisseur, aucune ombre,
 * aucun reflet, aucun contour. Les bornes des annees 80 etaient monochromes,
 * donc le corps entier prend la couleur du profil : quatre personnes, quatre
 * couleurs, reconnaissables d'un coup d'oeil de l'autre bout du canape.
 *
 * Ce qui distingue les vingt n'est pas le detail mais l'EMPRISE AU SOL : large
 * et plat pour le crabe, etroit et haut pour la meduse, en croix pour
 * l'intercepteur, en fleche pour le chasseur. C'est ce qui les separe encore a
 * 36 pixels dans l'en-tete, la ou un dessin detaille redevient une tache.
 *
 * Les sprites sont symetriques par construction : le script de dessin n'en
 * decrit que la moitie gauche et la reflete.
 *
 * Personnages originaux : le vocabulaire graphique de l'arcade est emprunte, pas
 * les dessins de Taito ou de Namco, qui restent leur propriete.
 */
final class Avatars
{
    public const FALLBACK = 'crabe';

    public const FAMILIES = [
        'envahisseurs' => 'Envahisseurs',
        'vaisseaux' => 'Vaisseaux',
        'machines' => 'Machines',
        'creatures' => 'Créatures',
    ];

    /** Palette d'accent par theme de profil. */
    private const COLORS = [
        'slate' => '#64748b',
        'rose' => '#f472b6',
        'violet' => '#a78bfa',
        'emerald' => '#34d399',
        'amber' => '#fbbf24',
        'sky' => '#38bdf8',
        'indigo' => '#818cf8',
    ];

    /** Le detail sombre, commun aux vingt : la teinte du fond de l'application,
     *  pour qu'un oeil se lise comme un trou et non comme une seconde couleur. */
    private const DARK = '#0f172a';

    /** @return array<string, array{label: string, family: string}> */
    public static function all(): array
    {
        return [
            // Envahisseurs
            'crabe' => ['label' => 'Crabe', 'family' => 'envahisseurs'],
            'meduse' => ['label' => 'Méduse', 'family' => 'envahisseurs'],
            'mite' => ['label' => 'Mite', 'family' => 'envahisseurs'],
            'scarabee' => ['label' => 'Scarabée', 'family' => 'envahisseurs'],
            'araignee' => ['label' => 'Araignée', 'family' => 'envahisseurs'],
            'ver' => ['label' => 'Ver', 'family' => 'envahisseurs'],
            // Vaisseaux
            'chasseur' => ['label' => 'Chasseur', 'family' => 'vaisseaux'],
            'navette' => ['label' => 'Navette', 'family' => 'vaisseaux'],
            'soucoupe' => ['label' => 'Soucoupe', 'family' => 'vaisseaux'],
            'intercepteur' => ['label' => 'Intercepteur', 'family' => 'vaisseaux'],
            'croiseur' => ['label' => 'Croiseur', 'family' => 'vaisseaux'],
            // Machines
            'tourelle' => ['label' => 'Tourelle', 'family' => 'machines'],
            'satellite' => ['label' => 'Satellite', 'family' => 'machines'],
            'drone' => ['label' => 'Drone', 'family' => 'machines'],
            'sentinelle' => ['label' => 'Sentinelle', 'family' => 'machines'],
            'mine' => ['label' => 'Mine', 'family' => 'machines'],
            // Créatures
            'blob' => ['label' => 'Blob', 'family' => 'creatures'],
            'oeil' => ['label' => 'Œil', 'family' => 'creatures'],
            'champignon' => ['label' => 'Champignon', 'family' => 'creatures'],
            'chauve' => ['label' => 'Chauve-souris', 'family' => 'creatures'],
        ];
    }

    /** @return array<string, list<string>> */
    private static function sprites(): array
    {
        return [
            'crabe' => [
                '................',
                '................',
                '.@............@.',
                '.@@..........@@.',
                '..@@..@@@@..@@..',
                '...@@@@@@@@@@...',
                '..@@@@@@@@@@@@..',
                '.@@@kk@@@@kk@@@.',
                '@@@@kk@@@@kk@@@@',
                '@@@@@@@@@@@@@@@@',
                '..@@@@@@@@@@@@..',
                '...@@@@@@@@@@...',
                '..@@..@@@@..@@..',
                '.@@...@..@...@@.',
                '................',
                '................',
            ],
            'meduse' => [
                '................',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@@@@@@@@....',
                '...@@@@@@@@@@...',
                '...@kk@@@@kk@...',
                '...@kk@@@@kk@...',
                '...@@@@@@@@@@...',
                '....@@@@@@@@....',
                '....@@@@@@@@....',
                '....@.@@@@.@....',
                '....@.@@@@.@....',
                '...@..@..@..@...',
                '...@..@..@..@...',
                '..@...@..@...@..',
                '................',
            ],
            'mite' => [
                '................',
                '......@..@......',
                '.....@....@.....',
                '@@...@@@@@@...@@',
                '@@@..@@@@@@..@@@',
                '@@@@.@k@@k@.@@@@',
                '@@@@@@k@@k@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@.@@@@@@.@@@@',
                '@@@..@@@@@@..@@@',
                '@@...@@@@@@...@@',
                '.@...@@..@@...@.',
                '.....@....@.....',
                '................',
                '................',
            ],
            'scarabee' => [
                '................',
                '................',
                '.....@@..@@.....',
                '...@@@@@@@@@@...',
                '..@@@kk@@kk@@@..',
                '..@@@kk@@kk@@@..',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                '@@@@@@@@@@@@@@@@',
                '@@@@@.@@@@.@@@@@',
                '@@@@@.@@@@.@@@@@',
                '@@@@@.@@@@.@@@@@',
                '.@@@@.@@@@.@@@@.',
                '.@.@..@..@..@.@.',
                '................',
                '................',
            ],
            'araignee' => [
                '................',
                '................',
                '@..............@',
                '.@@...@..@...@@.',
                '..@@.@@@@@@.@@..',
                '...@@kk@@kk@@...',
                '@..@@kk@@kk@@..@',
                '.@@@@@@@@@@@@@@.',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '@..@@.@@@@.@@..@',
                '..@...@..@...@..',
                '.@....@..@....@.',
                '@.....@..@.....@',
                '................',
                '................',
            ],
            'ver' => [
                '................',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@kk@@kk@....',
                '....@kk@@kk@....',
                '....@@@@@@@@....',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@@@@@@@@....',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@@@@@@@@....',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@..@@..@....',
                '................',
            ],
            'chasseur' => [
                '................',
                '.......@@.......',
                '.......@@.......',
                '......@@@@......',
                '......@@@@......',
                '.....@k@@k@.....',
                '.....@k@@k@.....',
                '....@@@@@@@@....',
                '....@@@@@@@@....',
                '...@@@@@@@@@@...',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '@@..@@@@@@@@..@@',
                '@....@@@@@@....@',
                '......@..@......',
                '................',
            ],
            'navette' => [
                '................',
                '.......@@.......',
                '......@@@@......',
                '......@@@@......',
                '.....@k@@k@.....',
                '.....@k@@k@.....',
                '.....@@@@@@.....',
                '.....@@@@@@.....',
                '....@@@@@@@@....',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '.@@..@@@@@@..@@.',
                '.@@..@@@@@@..@@.',
                '.@@..@@@@@@..@@.',
                '..@...@..@...@..',
                '................',
            ],
            'soucoupe' => [
                '................',
                '................',
                '................',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@kk@@kk@....',
                '...@@@@@@@@@@...',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '.@@@@@@@@@@@@@@.',
                '..@@@@@@@@@@@@..',
                '...@.@.@@.@.@...',
                '.....@.@@.@.....',
                '................',
                '................',
                '................',
            ],
            'intercepteur' => [
                '................',
                '@@............@@',
                '.@@..........@@.',
                '..@@........@@..',
                '...@@.@@@@.@@...',
                '....@@@@@@@@....',
                '....@k@@@@k@....',
                '.....@k@@k@.....',
                '.....@@@@@@.....',
                '....@@@@@@@@....',
                '...@@.@@@@.@@...',
                '..@@........@@..',
                '.@@..........@@.',
                '@@............@@',
                '................',
                '................',
            ],
            'croiseur' => [
                '................',
                '................',
                '................',
                '.....@@..@@.....',
                '.....@k@@k@.....',
                '....@@@@@@@@....',
                '....@@@@@@@@....',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '.@@@@@@@@@@@@@@.',
                '..@.@.@@@@.@.@..',
                '................',
                '................',
                '................',
                '................',
            ],
            'tourelle' => [
                '................',
                '.......@@.......',
                '.......@@.......',
                '.......@@.......',
                '.......@@.......',
                '....@@@@@@@@....',
                '...@@kk@@kk@@...',
                '...@@kk@@kk@@...',
                '..@@@@@@@@@@@@..',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@.@@.@@@@.@@.@@',
                '................',
                '................',
            ],
            'satellite' => [
                '................',
                '.......@@.......',
                '.......@@.......',
                '.....@@@@@@.....',
                '....@kk@@kk@....',
                '....@kk@@kk@....',
                '.....@@@@@@.....',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '.....@@@@@@.....',
                '....@@@@@@@@....',
                '.....@@..@@.....',
                '................',
                '................',
            ],
            'drone' => [
                '................',
                '................',
                '@@@..........@@@',
                '.@............@.',
                '..@@........@@..',
                '...@@@@@@@@@@...',
                '...@kk@@@@kk@...',
                '...@kk@@@@kk@...',
                '...@@@@@@@@@@...',
                '..@@........@@..',
                '.@............@.',
                '@@@..........@@@',
                '................',
                '................',
                '................',
                '................',
            ],
            'sentinelle' => [
                '................',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '....@kk@@kk@....',
                '....@kk@@kk@....',
                '....@@@@@@@@....',
                '.....@@..@@.....',
                '.....@@..@@.....',
                '....@@@@@@@@....',
                '...@@@@@@@@@@...',
                '...@@@@@@@@@@...',
                '....@@@@@@@@....',
                '.....@@..@@.....',
                '...@@@@@@@@@@...',
                '..@@@@@@@@@@@@..',
                '................',
            ],
            'mine' => [
                '................',
                '......@..@......',
                '......@..@......',
                '@...@@@@@@@@...@',
                '.@.@@@@@@@@@@.@.',
                '..@@@@@@@@@@@@..',
                '@@@@kk@@@@kk@@@@',
                '@@@@kk@@@@kk@@@@',
                '@@@@@@@@@@@@@@@@',
                '..@@@@@@@@@@@@..',
                '.@.@@@@@@@@@@.@.',
                '@...@@@@@@@@...@',
                '......@..@......',
                '......@..@......',
                '................',
                '................',
            ],
            'blob' => [
                '................',
                '................',
                '.....@@..@@.....',
                '...@@@@@@@@@@...',
                '..@@kk@@@@kk@@..',
                '.@@@kk@@@@kk@@@.',
                '.@@@@@@@@@@@@@@.',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '.@@@@@@@@@@@@@@.',
                '..@@@@@@@@@@@@..',
                '...@@.@@@@.@@...',
                '................',
                '................',
            ],
            'oeil' => [
                '................',
                '................',
                '....@@@..@@@....',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                '@@@@@@kkkk@@@@@@',
                '@@@@@@kkkk@@@@@@',
                '@@@@@@kkkk@@@@@@',
                '@@@@@@kkkk@@@@@@',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                '..@@@@@@@@@@@@..',
                '....@@@..@@@....',
                '................',
                '................',
            ],
            'champignon' => [
                '................',
                '................',
                '....@@@..@@@....',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '@@@@@@@@@@@@@@@@',
                '@@@..@@@@@@..@@@',
                '@@....@@@@....@@',
                '.@@@@@@@@@@@@@@.',
                '....@@@@@@@@....',
                '....@kk@@kk@....',
                '....@kk@@kk@....',
                '....@@@@@@@@....',
                '...@@@@@@@@@@...',
                '..@@@@@@@@@@@@..',
                '................',
            ],
            'chauve' => [
                '................',
                '................',
                '.....@.@@.@.....',
                '.....@@@@@@.....',
                '....@@@@@@@@....',
                '....@kk@@kk@....',
                '...@@@@@@@@@@...',
                '@@@@@@@@@@@@@@@@',
                '@@@@@@@@@@@@@@@@',
                '@@@.@@@@@@@@.@@@',
                '@@..@@@@@@@@..@@',
                '@....@@@@@@....@',
                '.....@.@@.@.....',
                '................',
                '................',
                '................',
            ],
        ];
    }

    /** @return array<string, array<string, string>> */
    public static function byFamily(): array
    {
        $grouped = array_fill_keys(array_keys(self::FAMILIES), []);
        foreach (self::all() as $key => $avatar) {
            $grouped[$avatar['family']][$key] = $avatar['label'];
        }

        return $grouped;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Les couleurs utilisables par un profil. Une seule source, celle que
     * render() consulte, pour qu'aucune couleur validee ailleurs ne finisse
     * silencieusement remplacee par la couleur de repli.
     *
     * @return list<string>
     */
    public static function colors(): array
    {
        return array_keys(self::COLORS);
    }

    public static function colorExists(string $color): bool
    {
        return isset(self::COLORS[$color]);
    }

    public static function render(string $key, string $color = 'indigo', int $size = 96): string
    {
        if (!self::exists($key)) {
            $key = self::FALLBACK;
        }

        $accent = self::COLORS[$color] ?? self::COLORS['indigo'];
        $label = htmlspecialchars(self::all()[$key]['label'], ENT_QUOTES);
        $shapes = self::spriteToShapes(self::sprites()[$key], $accent);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="%d" height="%d" '
            . 'shape-rendering="crispEdges" role="img" aria-label="%s">%s</svg>',
            $size,
            $size,
            $label,
            $shapes
        );
    }

    /**
     * Decoupe le sprite en rectangles pleins : on etend d'abord vers la droite,
     * puis vers le bas tant que la rangee suivante repete exactement la meme
     * plage. Une silhouette monochrome se resume ainsi a une trentaine de formes.
     *
     * @param list<string> $rows
     */
    private static function spriteToShapes(array $rows, string $accent): string
    {
        $grid = array_map(
            static fn (string $row): array => preg_split('//u', $row, -1, PREG_SPLIT_NO_EMPTY),
            $rows
        );
        $hauteur = count($grid);
        $largeur = $hauteur > 0 ? count($grid[0]) : 0;
        $pris = array_fill(0, $hauteur, array_fill(0, $largeur, false));
        $shapes = '';

        for ($y = 0; $y < $hauteur; $y++) {
            for ($x = 0; $x < $largeur; $x++) {
                $char = $grid[$y][$x];
                if ($pris[$y][$x] || $char === '.') {
                    continue;
                }

                $w = 1;
                while ($x + $w < $largeur && !$pris[$y][$x + $w] && $grid[$y][$x + $w] === $char) {
                    $w++;
                }

                $h = 1;
                while ($y + $h < $hauteur) {
                    $rangeeIdentique = true;
                    for ($i = 0; $i < $w; $i++) {
                        if ($pris[$y + $h][$x + $i] || $grid[$y + $h][$x + $i] !== $char) {
                            $rangeeIdentique = false;
                            break;
                        }
                    }
                    if (!$rangeeIdentique) {
                        break;
                    }
                    $h++;
                }

                for ($dy = 0; $dy < $h; $dy++) {
                    for ($dx = 0; $dx < $w; $dx++) {
                        $pris[$y + $dy][$x + $dx] = true;
                    }
                }

                $shapes .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>',
                    $x,
                    $y,
                    $w,
                    $h,
                    $char === '@' ? $accent : self::DARK
                );
            }
        }

        return $shapes;
    }
}
