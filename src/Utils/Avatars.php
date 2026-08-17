<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Catalogue d'avatars en pixel art.
 *
 * Chaque sprite est une grille 16x16 dessinee en ASCII, une ligne par rangee,
 * un caractere par pixel. '.' est transparent, '@' prend la couleur de theme
 * du profil (chaque sprite doit l'utiliser quelque part), les autres lettres
 * viennent de la palette partagee PALETTE. Le rendu fusionne les pixels
 * consecutifs de meme couleur d'une rangee en un seul <rect>, ce qui tient
 * chaque sprite autour de 20 a 45 formes malgre la grille 16x16 : les 24
 * avatars sont inlines sur la page de choix de profil.
 */
final class Avatars
{
    public const FALLBACK = 'alien';

    public const FAMILIES = [
        'frissons' => 'Frissons',
        'scifi' => 'Science-fiction',
        'aventure' => 'Aventure',
        'fantastique' => 'Fantastique',
        'coree' => 'Corée et Kpop',
    ];

    /** Palette d'accent par thème de profil. */
    private const COLORS = [
        'slate' => '#64748b',
        'rose' => '#f472b6',
        'violet' => '#a78bfa',
        'emerald' => '#34d399',
        'amber' => '#fbbf24',
        'sky' => '#38bdf8',
        'indigo' => '#818cf8',
    ];

    /** Palette partagee des sprites, volontairement restreinte. */
    private const PALETTE = [
        'k' => '#150c26', 'K' => '#33244a',
        'w' => '#f5f2ff',
        'p' => '#ffd6a5', 'o' => '#d99a66',
        'n' => '#f2ede0', 'N' => '#c9bfa3',
        'b' => '#4a3220', 'B' => '#8a6239',
        's' => '#cbd5e1', 'S' => '#64748b',
        'g' => '#7bcf7a', 'G' => '#3f9a4d',
        'r' => '#e8703f', 'R' => '#b8461f',
        'c' => '#c97a3d', 'C' => '#8a4a1e',
        't' => '#4fb3bf', 'T' => '#2f7f8a',
        'y' => '#ffe27a', 'Y' => '#caa23a',
        'x' => '#d94f4f',
        'v' => '#5b3a8a', 'V' => '#301c4d',
        'm' => '#8a2332', 'M' => '#4a0f18',
        'a' => '#d9cbb0', 'A' => '#a89572',
        'i' => '#e3eef2', 'I' => '#b3c8d1',
        'e' => '#3fae82', 'E' => '#215f45',
        'q' => '#c1502e', 'Q' => '#7a2f18',
    ];

    /** @return array<string, array{label: string, family: string}> */
    public static function all(): array
    {
        return [
            // Frissons
            'fantome' => ['label' => 'Fantôme', 'family' => 'frissons'],
            'squelette' => ['label' => 'Squelette fleuri', 'family' => 'frissons'],
            'momie' => ['label' => 'Momie', 'family' => 'frissons'],
            'vampire' => ['label' => 'Vampire', 'family' => 'frissons'],
            'loupgarou' => ['label' => 'Loup-garou', 'family' => 'frissons'],
            'yeti' => ['label' => 'Yeti', 'family' => 'frissons'],
            // Science-fiction
            'alien' => ['label' => 'Petit alien', 'family' => 'scifi'],
            'robot' => ['label' => 'Robot boîte de conserve', 'family' => 'scifi'],
            'astronaute' => ['label' => 'Astronaute au casque bulle', 'family' => 'scifi'],
            'tentacule' => ['label' => 'Créature à tentacules', 'family' => 'scifi'],
            // Aventure
            'detective' => ['label' => 'Détective en trench', 'family' => 'aventure'],
            'pirate' => ['label' => 'Pirate au bandeau', 'family' => 'aventure'],
            'cowboy' => ['label' => 'Cow-boy en poncho', 'family' => 'aventure'],
            'chevalier' => ['label' => 'Chevalier au heaume', 'family' => 'aventure'],
            'samourai' => ['label' => 'Samouraï au croissant', 'family' => 'aventure'],
            'ninja' => ['label' => 'Ninja au bandeau', 'family' => 'aventure'],
            'aviatrice' => ['label' => 'Aviatrice', 'family' => 'aventure'],
            'scaphandrier' => ['label' => 'Scaphandrier', 'family' => 'aventure'],
            // Fantastique
            'sorciere' => ['label' => 'Sorcière', 'family' => 'fantastique'],
            'dragon' => ['label' => 'Dragon', 'family' => 'fantastique'],
            'fee' => ['label' => 'Fée', 'family' => 'fantastique'],
            // Corée et Kpop
            'gumiho' => ['label' => 'Gumiho', 'family' => 'coree'],
            'dokkaebi' => ['label' => 'Dokkaebi', 'family' => 'coree'],
            'idole' => ['label' => 'Idole au micro-casque', 'family' => 'coree'],
        ];
    }

    /** @return array<string, array<int, string>> */
    private static function sprites(): array
    {
        return [
            'fantome' => [
                '................',
                '......nnnn......',
                '....nnnnnnnn....',
                '...nnnnnnnnnn...',
                '...nnnnnnnnnn...',
                '..nnnn@nn@nnnn..',
                '..nnnnnnnnnnnn..',
                '..nnnnnkknnnnn..',
                '..nnnnnnnnnnnn..',
                '..nnnnnnnnnnnn..',
                '.nnnnnnnnnnnnnn.',
                '.nnnnnnnnnnnnnn.',
                '.nnnnnnnnnnnnnn.',
                '.nnnnnnnnnnnnnn.',
                '.nnn...nn...nnn.',
                '................',
            ],
            'squelette' => [
                '....y.@..@.y....',
                '.....GnnnnG.....',
                '....nnnnnnnn....',
                '...nnnnnnnnnn...',
                '...nnnnnnnnnn...',
                '...nkknnnnkkn...',
                '...nkknnnnkkn...',
                '....nnnkknnn....',
                '....nnknknkn....',
                '.....nnnnnn.....',
                '......nnnn......',
                '....nknnnnkn....',
                '.....nknnkn.....',
                '.......@@.......',
                '................',
                '................',
            ],
            'momie' => [
                '................',
                '......aaaa......',
                '....aaaaaaaa....',
                '...kkkkkkkkkk...',
                '...aaaaaaaaaa...',
                '..aaaaaaaakkaa..',
                '..kkkkkkkkkkkk..',
                '..aaaaaaaaaaaa..',
                '..aaaaaaaaaaaa..',
                '..kkkkkkkkkkkk..',
                '...aaaaaaaaaa...',
                '...aaaaaaaaaa...',
                '..aaaaaaaaaaa@@.',
                '..aaaaaaaaaa@@@@',
                '...aaaaaaaa..@@.',
                '.............@..',
            ],
            'vampire' => [
                '................',
                '......kkkk......',
                '.....kkppkk.....',
                '..@@kkppppkk@@..',
                '.@@@pppppppp@@@.',
                '.@@@ppkppkpp@@@.',
                '.@@@pppppppp@@@.',
                '..@@pppkkppp@@..',
                '...@pppwwppp@...',
                '....pppppppp....',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                '..@@@@@@@@@@@@..',
                '...@@@@@@@@@@...',
            ],
            'loupgarou' => [
                '................',
                '...bbb....bbb...',
                '..bbbbb..bbbbb..',
                '...BBBBBBBBBB...',
                '..BBBkBBBBkBBB..',
                '..BBBBBBBBBBBB..',
                '...pppppppppp...',
                '....ppkppkpp....',
                '....pppppppp....',
                '.....ppkkpp.....',
                '.....kkkkkk.....',
                '..BBBBBBBBBBBB..',
                '.BBBBBBBB@@@@@B.',
                '.BBBBBBBBB@@@@@.',
                '..BBBBBBBBB@@@..',
                '................',
            ],
            'yeti' => [
                '....i..i...i....',
                '.....iiiiii.....',
                '..iiiiiiiiiiii..',
                '...iiiiiiiiii...',
                'iiiiiiiiiiiiiiii',
                '.iiiiIIIIIIiiii.',
                'iiiiIIkIIkIIiiii',
                '.iiiIIIkkIIIiii.',
                'iiiiiIIwwIIiiiii',
                '..iiiiiiiiiiii..',
                'iiiiiiiiiiiiiiii',
                '.iiiiiiiiiiiiii.',
                'iiiiii@@@@iiiiii',
                '..iiiii@@iiiii..',
                '................',
                '................',
            ],
            'alien' => [
                '....@......@....',
                '......gggg......',
                '.....gggggg.....',
                '....gggggggg....',
                '...gggggggggg...',
                '...gkkkggkkkg...',
                '...gkkkggkkkg...',
                '....gkggggkg....',
                '.....GGGGGG.....',
                '......GGGG......',
                '......GGGG......',
                '....@@@@@@@@....',
                '...@@@@@@@@@@...',
                '...@@@@@@@@@@...',
                '................',
                '................',
            ],
            'robot' => [
                '.......SS.......',
                '.......@@.......',
                '....ssssssss....',
                '....ssssssss....',
                '....skssssks....',
                '....ssssssss....',
                '....@@@@@@@@....',
                '....ssssssss....',
                '....SSSSSSSS....',
                '.....SSSSSS.....',
                '...ssssssssss...',
                '..SssssssssssS..',
                '..ssssssssssss..',
                '...SSSSSSSSSS...',
                '................',
                '................',
            ],
            'astronaute' => [
                '................',
                '.....wsssss.....',
                '...swssssssss...',
                '..swppppppppss..',
                '..ssppppppppss..',
                '..ssppkppkppss..',
                '..ssppppppppss..',
                '..sspppkkpppss..',
                '...ssppppppss...',
                '....ssssssss....',
                '....SSSSSSSS....',
                '...@@@@@@@@@@...',
                '..@@@@@@@@@@@@..',
                '..@@@@@@@@@@@@..',
                '................',
                '................',
            ],
            'tentacule' => [
                '................',
                '.....vvvvvv.....',
                '...vvvvvvvvvv...',
                '..vvvvvvvvvvvv..',
                '..vvvvvvvvvvvv..',
                '...wwwk@@kwww...',
                '...vvvvvvvvvv...',
                '..vvvvvvvvvvvv..',
                '.VVVVVVVVVVVVVV.',
                '.vvv.vv..vv.vvv.',
                '.vv..vv..vv..vv.',
                'vv...vv..vv...vv',
                'vv....vvvv....vv',
                '......vvvv......',
                '................',
                '................',
            ],
            'detective' => [
                '................',
                '.....bbbbbb.....',
                '....bbbbbbbb....',
                '....BBBBBBBB....',
                '..bbbbbbbbbbbb..',
                '..kkkkkkkkkkkk..',
                '.....pppppp.....',
                '....pkkppkkp....',
                '....pppppppp....',
                '....ppp..ppp....',
                '.....pppppp.....',
                '......pppp......',
                '..@@@@@@@@@@@@..',
                '.@@@@@kwwk@@@@@.',
                '.@@@@@@kk@@@@@@.',
                '.@@@@@@@@@@@@@@.',
            ],
            'pirate' => [
                '...k........k...',
                '..kkkk....kkkk..',
                'kkkkkkkwwkkkkkkk',
                '..kkkkkkkkkkkk..',
                '....pppppppp....',
                '....ppkpkkkp....',
                '....pppppkkk....',
                '....ppkppkpp....',
                '....pppkkppp....',
                '.....BBBBBB.....',
                '....BBBBBBBB....',
                '...kkkkkkkkkk...',
                '..kk@@@kkkkkkk..',
                '..kkkk@@@kkkkk..',
                '...kkkkyy@@@k...',
                '................',
            ],
            'cowboy' => [
                '................',
                '................',
                '....BBBBBBBB....',
                'BBBBBBBBBBBBBBBB',
                '.....bbbbbb.....',
                '....pppppppp....',
                '....ppkppkpp....',
                '....pppppppp....',
                '.....pppppp.....',
                '......pppp......',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                'SSSSSSSSSSSSSSSS',
                '@@@@@@@@@@@@@@@@',
                '.SSSSSSSSSSSSSS.',
                '.@@@@@@@@@@@@@@.',
            ],
            'chevalier' => [
                '.......@@.......',
                '......ssss......',
                '....ssssssss....',
                '...ssssssssss...',
                '...SssssssssS...',
                '...SSSSkkSSSS...',
                '...SSSSkkSSSS...',
                '...ssSSkkSSss...',
                '...ssssssssss...',
                '....ssssssss....',
                '...SSSSSSSSSS...',
                '...SSSSSSSSSS...',
                '..ssssssssssss..',
                '.@@@@@@@@@@@@@..',
                '..@@@@@@@@@@@...',
                '................',
            ],
            'samourai' => [
                '................',
                '.....mmmmmm.....',
                '....mmm@@mmm....',
                '...mmmmmmmmmm...',
                '...mmmmmmmmmm...',
                '....ppkppkpp....',
                '....pppppppp....',
                '....pppkkppp....',
                '.....pppppp.....',
                '...mmmmmmmmmm...',
                'mmmmmmmmmmmmmmmm',
                '.mmmmmmmmmmmmmm.',
                '..@@@@@@@@@@@@..',
                '....mmmmmmmm....',
                '................',
                '................',
            ],
            'ninja' => [
                '................',
                '................',
                '.....kkkkkk.....',
                '.@@@kkkkkkkk@@@.',
                '@@@.kkkkkkkk.@@@',
                '@@..ppkppkpp..@@',
                '....kkkkkkkk....',
                '....kkkkkkkk....',
                '.....kkkkkk.....',
                '......kkkk......',
                '...kkkkkkkkkk...',
                '....kkkkkkkk....',
                '....kkk@@kkk....',
                '.....kkkkkk.....',
                '................',
                '................',
            ],
            'aviatrice' => [
                '................',
                '.....bbbbbb.....',
                '....bbbbbbbb....',
                '....BbbbbbbB....',
                '....ssppppss....',
                '....sskppkss....',
                '....pppppppp....',
                '....ppkppkpp....',
                '....pppppppp....',
                '.....bbbbbb.....',
                '....bbbbbbbb....',
                '..bbbbbbbbbbbb..',
                '.@@@@@bbbbbbbbb.',
                '@@@@@bbbbbbbbbb.',
                '.@@@bbbbbbbbbb..',
                '................',
            ],
            'scaphandrier' => [
                '................',
                '.....cccccc.....',
                '...CccccccccC...',
                '..CccccccccccC..',
                '..cccccccccccc..',
                '...CwwwwwwwwC...',
                '...CwwppppwwC...',
                '...CwwpkkpwwC...',
                '...CwwppppwwC...',
                '..CccccccccccC..',
                '.CcccCccccCcccC.',
                '..@@@@@@@@@@@@..',
                '.@@@@@@@@@@@@@@.',
                '.@@@@@@@@@@@@@@.',
                '..@@@@@@@@@@@@..',
                '................',
            ],
            'sorciere' => [
                '.......v........',
                '.......vv.......',
                '......vvvv......',
                '.....vvvvvv.....',
                '....vvvvvvvv....',
                '..vvvvvvvvvv@v..',
                '...bpppppppp....',
                '....ppkppkpp....',
                '....pppppppp....',
                '.....pppppp.....',
                '......pppp......',
                '..vvvvvvvvvvvv..',
                '.vvvvvvvvvvvvvv.',
                '.vvvvvvvvvvvvvv.',
                '..@@@@@@@@@@@@..',
                '................',
            ],
            'dragon' => [
                '..QQ........QQ..',
                '.QQQQ......QQQQ.',
                '..qqqq....qqqq..',
                '....qqqqqqqq....',
                '...qqqqqqqqqq...',
                '...qqkqqqqkqq...',
                '...qqqqqqqqqq...',
                '....qqqqqqqq....',
                '.....qqqqqq.....',
                '.....QkQQkQ.....',
                '....QQQQQQQQ....',
                '..qqqqqqqqqqqq..',
                '.qqqqq@@@@qqqqq.',
                '.qqqqqqqqqqqqqq.',
                '..qqqqqqqqqqqq..',
                '................',
            ],
            'fee' => [
                '......yyyy......',
                '.....pppppp.....',
                '....BppppppB....',
                'www.pppppppp.www',
                'wwwwppppppppwwww',
                '.wwwppkppkppwww.',
                '....pppppppp....',
                '.....pppppp.....',
                '......pppp......',
                '....wwwwwwww....',
                '...ww@@@wwwww...',
                '...wwwwwwwwww...',
                '................',
                '................',
                '................',
                '................',
            ],
            'gumiho' => [
                '................',
                '..rrr......rrr..',
                '...RRR....RRR...',
                '...rrrrrrrrrr...',
                '..rrrrrrrrrrrr..',
                '...rrkrrrrkrr...',
                '....rrrrrrrr....',
                '.....nnnnnn.....',
                '.....nnkknn.....',
                '.......rr.......',
                '.....RRrrRR.....',
                '...rrRRrrRRrr...',
                '.RRrrRRrrRRrrRR.',
                'rrRRrrRRRRrrRRrr',
                '....@@@@@@@@....',
                '................',
            ],
            'dokkaebi' => [
                '.......kk.......',
                '......kyyk......',
                '....tttttttt....',
                '...tttttttttt...',
                '...tTttttttTt...',
                '...ttkttttktt...',
                '...tttttttttt...',
                '....tkwkkwkt....',
                '.....tttttt.....',
                '......TTTT......',
                '...tttttttttt...',
                '..tttttttttttt..',
                '..ttttttttttt@@.',
                '...tttttttttt@y.',
                '................',
                '................',
            ],
            'idole' => [
                '................',
                '.....kkkkkk.....',
                '....@kkkkkkk....',
                '....@@pppppp....',
                '....pppppppp....',
                '....ppkppkppk...',
                '....ppppppppk...',
                '....pppkkypk....',
                '....pppppppp....',
                '.....pppppp.....',
                '......pppp......',
                '..kkkkkkkkkkkk..',
                '.kkkkkk@@kkkkkk.',
                '.kkkkkkkkkkkkkk.',
                '..@@@@@@@@@@@@..',
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
     * Les couleurs d'accent utilisables par un profil. Une seule source, celle
     * que render() consulte, pour qu'aucune couleur validee ailleurs ne finisse
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
     * Fusionne les pixels consecutifs de meme couleur d'une rangee en un seul
     * rect. C'est ce qui garde chaque sprite a quelques dizaines de formes
     * malgre la grille 16x16.
     *
     * @param array<int, string> $rows
     */
    private static function spriteToShapes(array $rows, string $accent): string
    {
        $shapes = '';

        foreach ($rows as $y => $row) {
            $cells = preg_split('//u', $row, -1, PREG_SPLIT_NO_EMPTY);
            $count = count($cells);
            $x = 0;

            while ($x < $count) {
                $char = $cells[$x];
                if ($char === '.') {
                    $x++;
                    continue;
                }

                $run = 1;
                while ($x + $run < $count && $cells[$x + $run] === $char) {
                    $run++;
                }

                $fill = $char === '@' ? $accent : (self::PALETTE[$char] ?? '#f472b6');
                $shapes .= sprintf('<rect x="%d" y="%d" width="%d" height="1" fill="%s"/>', $x, $y, $run, $fill);

                $x += $run;
            }
        }

        return $shapes;
    }
}
