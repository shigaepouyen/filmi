<?php
declare(strict_types=1);

namespace App\Utils;

final class Avatars
{
    public const FALLBACK = 'alien';

    public const FAMILIES = [
        'scifi' => 'Science-fiction',
        'aventure' => 'Aventure',
        'fantastique' => 'Fantastique',
        'frissons' => 'Frissons',
        'classiques' => 'Classiques',
        'coree' => 'Corée',
        'kpop' => 'Kpop',
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

    /** @return array<string, array{label: string, family: string}> */
    public static function all(): array
    {
        return [
            'alien' => ['label' => 'Petit alien', 'family' => 'scifi'],
            'robot' => ['label' => 'Robot boîte de conserve', 'family' => 'scifi'],
            'astronaute' => ['label' => 'Astronaute au casque bulle', 'family' => 'scifi'],
            'aventuriere' => ['label' => 'Aventurière au chapeau de feutre', 'family' => 'aventure'],
            'scaphandrier' => ['label' => 'Scaphandrier', 'family' => 'aventure'],
            'aviatrice' => ['label' => 'Aviatrice', 'family' => 'aventure'],
            'sorcier' => ['label' => 'Apprenti sorcier', 'family' => 'fantastique'],
            'gardien' => ['label' => 'Gardien galactique', 'family' => 'fantastique'],
            'dinosaure' => ['label' => 'Petit dinosaure', 'family' => 'fantastique'],
            'vampire' => ['label' => 'Vampire', 'family' => 'frissons'],
            'traqueur' => ['label' => 'Traqueur de spectres', 'family' => 'frissons'],
            'creature' => ['label' => 'Créature poilue amicale', 'family' => 'frissons'],
            'detective' => ['label' => 'Détective en trench', 'family' => 'classiques'],
            'cowboy' => ['label' => 'Cow-boy en poncho', 'family' => 'classiques'],
            'gumiho' => ['label' => 'Gumiho', 'family' => 'coree'],
            'dokkaebi' => ['label' => 'Dokkaebi', 'family' => 'coree'],
            'haechi' => ['label' => 'Haechi', 'family' => 'coree'],
            'erudit' => ['label' => 'Érudit en hanbok', 'family' => 'coree'],
            'tigre' => ['label' => 'Tigre du folklore', 'family' => 'coree'],
            'zombie' => ['label' => 'Zombie de bureau', 'family' => 'coree'],
            'idole' => ['label' => 'Idole au micro-casque', 'family' => 'kpop'],
            'danseur' => ['label' => 'Danseur en bomber', 'family' => 'kpop'],
            'fan' => ['label' => 'Fan au lightstick', 'family' => 'kpop'],
            'trainee' => ['label' => 'Trainee', 'family' => 'kpop'],
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

    public static function render(string $key, string $color = 'indigo', int $size = 96): string
    {
        if (!self::exists($key)) {
            $key = self::FALLBACK;
        }

        $accent = self::COLORS[$color] ?? self::COLORS['indigo'];
        $body = self::traits($key, $accent);
        $label = htmlspecialchars(self::all()[$key]['label'], ENT_QUOTES);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="%d" height="%d" role="img" aria-label="%s">'
            . '<circle cx="48" cy="48" r="46" fill="%s" opacity="0.18"/>%s</svg>',
            $size,
            $size,
            $label,
            $accent,
            $body
        );
    }

    /** Yeux et joues communs à tous les avatars. */
    private static function face(int $cx = 48, int $cy = 52): string
    {
        return sprintf(
            '<circle cx="%d" cy="%d" r="4" fill="#1f2937"/><circle cx="%d" cy="%d" r="4" fill="#1f2937"/>'
            . '<circle cx="%d" cy="%d" r="3.5" fill="#fda4af" opacity="0.7"/>'
            . '<circle cx="%d" cy="%d" r="3.5" fill="#fda4af" opacity="0.7"/>'
            . '<path d="M%d %d q6 5 12 0" stroke="#1f2937" stroke-width="2" fill="none" stroke-linecap="round"/>',
            $cx - 9, $cy, $cx + 9, $cy,
            $cx - 18, $cy + 7, $cx + 18, $cy + 7,
            $cx - 6, $cy + 11
        );
    }

    /** Tete humaine commune, la plupart des avatars la partagent. */
    private static function humanHead(int $cy = 52, string $fill = '#fcd9b6'): string
    {
        return sprintf('<circle cx="48" cy="%d" r="24" fill="%s"/>', $cy, $fill);
    }

    private static function traits(string $key, string $accent): string
    {
        return match ($key) {
            'alien' => self::alien($accent),
            'robot' => self::robot($accent),
            'astronaute' => self::astronaute($accent),
            'aventuriere' => self::aventuriere($accent),
            'scaphandrier' => self::scaphandrier($accent),
            'aviatrice' => self::aviatrice($accent),
            'sorcier' => self::sorcier($accent),
            'gardien' => self::gardien($accent),
            'dinosaure' => self::dinosaure($accent),
            'vampire' => self::vampire($accent),
            'traqueur' => self::traqueur($accent),
            'creature' => self::creature($accent),
            'detective' => self::detective($accent),
            'cowboy' => self::cowboy($accent),
            'gumiho' => self::gumiho($accent),
            'dokkaebi' => self::dokkaebi($accent),
            'haechi' => self::haechi($accent),
            'erudit' => self::erudit($accent),
            'tigre' => self::tigre($accent),
            'zombie' => self::zombie($accent),
            'idole' => self::idole($accent),
            'danseur' => self::danseur($accent),
            'fan' => self::fan($accent),
            'trainee' => self::trainee($accent),
        };
    }

    private static function alien(string $accent): string
    {
        return '<ellipse cx="48" cy="52" rx="26" ry="28" fill="#bbf7d0"/>'
            . '<path d="M34 26 q-6 -12 2 -16" stroke="#86efac" stroke-width="4" fill="none" stroke-linecap="round"/>'
            . '<path d="M62 26 q6 -12 -2 -16" stroke="#86efac" stroke-width="4" fill="none" stroke-linecap="round"/>'
            . '<circle cx="36" cy="12" r="4" fill="' . $accent . '"/>'
            . '<circle cx="62" cy="12" r="4" fill="' . $accent . '"/>'
            . '<ellipse cx="39" cy="50" rx="6" ry="8" fill="#1f2937"/>'
            . '<ellipse cx="57" cy="50" rx="6" ry="8" fill="#1f2937"/>'
            . '<circle cx="30" cy="62" r="3.5" fill="#fda4af" opacity="0.7"/>'
            . '<circle cx="66" cy="62" r="3.5" fill="#fda4af" opacity="0.7"/>'
            . '<path d="M42 64 q6 5 12 0" stroke="#1f2937" stroke-width="2" fill="none" stroke-linecap="round"/>';
    }

    private static function robot(string $accent): string
    {
        return '<rect x="24" y="26" width="48" height="46" rx="14" fill="#cbd5e1"/>'
            . '<circle cx="20" cy="52" r="7" fill="#94a3b8"/>'
            . '<circle cx="76" cy="52" r="7" fill="#94a3b8"/>'
            . '<line x1="48" y1="26" x2="48" y2="14" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>'
            . '<circle cx="48" cy="11" r="5" fill="' . $accent . '"/>'
            . '<rect x="40" y="66" width="16" height="8" rx="4" fill="' . $accent . '" opacity="0.8"/>'
            . self::face();
    }

    private static function astronaute(string $accent): string
    {
        return '<ellipse cx="48" cy="78" rx="22" ry="14" fill="#e2e8f0"/>'
            . '<circle cx="48" cy="48" r="27" fill="#fcd9b6"/>'
            . '<circle cx="48" cy="48" r="27" fill="none" stroke="' . $accent . '" stroke-width="4"/>'
            . '<ellipse cx="38" cy="36" rx="6" ry="4" fill="#ffffff" opacity="0.5"/>'
            . '<rect x="40" y="70" width="16" height="8" rx="4" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function aventuriere(string $accent): string
    {
        return self::humanHead()
            . '<ellipse cx="48" cy="30" rx="30" ry="8" fill="#a16207"/>'
            . '<path d="M30 30 q0 -18 18 -18 q18 0 18 18" fill="#a16207"/>'
            . '<rect x="30" y="26" width="36" height="6" rx="3" fill="' . $accent . '"/>'
            . '<path d="M34 72 L48 86 L62 72 Z" fill="' . $accent . '"/>'
            . '<rect x="76" y="58" width="8" height="20" rx="4" fill="#a16207"/>'
            . self::face();
    }

    private static function scaphandrier(string $accent): string
    {
        return '<circle cx="48" cy="52" r="26" fill="#94a3b8"/>'
            . '<circle cx="48" cy="52" r="18" fill="#e2e8f0"/>'
            . '<circle cx="31" cy="36" r="3" fill="#e2e8f0"/>'
            . '<circle cx="65" cy="36" r="3" fill="#e2e8f0"/>'
            . '<circle cx="31" cy="68" r="3" fill="#e2e8f0"/>'
            . '<circle cx="65" cy="68" r="3" fill="#e2e8f0"/>'
            . '<path d="M72 62 q14 6 10 26" stroke="' . $accent . '" stroke-width="5" fill="none" stroke-linecap="round"/>'
            . self::face();
    }

    private static function aviatrice(string $accent): string
    {
        return self::humanHead(50)
            . '<path d="M24 44 q0 -26 24 -26 q24 0 24 26 q-6 -6 -24 -6 q-18 0 -24 6z" fill="#78350f"/>'
            . '<circle cx="37" cy="40" r="8" fill="#e2e8f0" stroke="' . $accent . '" stroke-width="3"/>'
            . '<circle cx="59" cy="40" r="8" fill="#e2e8f0" stroke="' . $accent . '" stroke-width="3"/>'
            . '<rect x="45" y="38" width="6" height="4" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function sorcier(string $accent): string
    {
        return self::humanHead()
            . '<path d="M24 34 L48 4 L72 34 Q48 26 24 34 Z" fill="#4c1d95"/>'
            . '<ellipse cx="48" cy="34" rx="26" ry="6" fill="#4c1d95"/>'
            . '<path d="M46 12 l2 -5 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 z" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function gardien(string $accent): string
    {
        return self::humanHead(fill: '#c7d2fe')
            . '<path d="M14 78 Q48 60 82 78 L82 92 L14 92 Z" fill="#4338ca"/>'
            . '<rect x="24" y="48" width="48" height="9" rx="4.5" fill="#4338ca"/>'
            . '<rect x="20" y="18" width="72" height="10" rx="5" fill="' . $accent . '" opacity="0.3" transform="rotate(-35 56 20)"/>'
            . '<rect x="20" y="18" width="72" height="4" rx="2" fill="' . $accent . '" transform="rotate(-35 56 20)"/>'
            . self::face();
    }

    private static function dinosaure(string $accent): string
    {
        return '<ellipse cx="48" cy="54" rx="26" ry="24" fill="#86efac"/>'
            . '<path d="M30 34 q6 -14 18 -14 q12 0 18 14" fill="#4ade80"/>'
            . '<path d="M40 20 l4 -8 l4 8 M52 20 l4 -8 l4 8" fill="none" stroke="' . $accent . '" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<ellipse cx="30" cy="66" rx="6" ry="4" fill="#4ade80"/>'
            . self::face();
    }

    private static function vampire(string $accent): string
    {
        return '<path d="M20 80 L36 38 L48 58 L60 38 L76 80 Z" fill="#1e293b"/>'
            . '<circle cx="48" cy="50" r="22" fill="#f1f5f9"/>'
            . '<path d="M40 26 Q48 34 56 26 Q52 20 48 20 Q44 20 40 26 Z" fill="#1e293b"/>'
            . '<path d="M42 65 l3 6 M54 65 l-3 6" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>'
            . '<circle cx="48" cy="74" r="4" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function traqueur(string $accent): string
    {
        return self::humanHead(fill: '#e2e8f0')
            . '<path d="M22 40 Q26 20 48 20 Q70 20 74 40 Q48 30 22 40 Z" fill="#334155"/>'
            . '<rect x="66" y="46" width="16" height="24" rx="5" fill="#334155"/>'
            . '<path d="M78 48 Q90 54 80 66" stroke="#334155" stroke-width="4" fill="none" stroke-linecap="round"/>'
            . '<rect x="30" y="78" width="6" height="14" rx="2" fill="' . $accent . '"/>'
            . '<path d="M33 78 L18 68 L18 60 L46 70 Z" fill="' . $accent . '" opacity="0.35"/>'
            . self::face();
    }

    private static function creature(string $accent): string
    {
        return '<path d="M14 56 Q14 30 24 26 Q30 18 37 26 Q43 16 49 26 Q55 18 61 26 Q68 18 74 26 Q84 30 84 56 Q86 86 49 86 Q12 86 14 56 Z" fill="#d8b4fe"/>'
            . '<ellipse cx="48" cy="66" rx="13" ry="10" fill="#f3e8ff"/>'
            . '<circle cx="82" cy="62" r="7" fill="' . $accent . '"/>'
            . '<path d="M74 68 Q82 72 82 62" stroke="' . $accent . '" stroke-width="4" fill="none" stroke-linecap="round"/>'
            . self::face();
    }

    private static function detective(string $accent): string
    {
        return self::humanHead()
            . '<path d="M28 70 Q48 86 68 70 L64 90 L32 90 Z" fill="#78716c"/>'
            . '<path d="M44 70 L48 82 L52 70 Z" fill="' . $accent . '"/>'
            . '<path d="M24 30 Q24 12 48 12 Q72 12 72 30 Q48 22 24 30 Z" fill="#78716c"/>'
            . '<ellipse cx="48" cy="30" rx="30" ry="6" fill="#78716c"/>'
            . '<rect x="26" y="27" width="44" height="5" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function cowboy(string $accent): string
    {
        return self::humanHead()
            . '<path d="M10 34 q38 -20 76 0 q-14 8 -38 8 q-24 0 -38 -8z" fill="#92400e"/>'
            . '<ellipse cx="48" cy="26" rx="16" ry="10" fill="#92400e"/>'
            . '<path d="M16 68 L80 68 L92 92 L4 92 Z" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function gumiho(string $accent): string
    {
        return self::humanHead(fill: '#fed7aa')
            . '<path d="M26 34 l6 -20 l10 16z" fill="#fb923c"/>'
            . '<path d="M70 34 l-6 -20 l-10 16z" fill="#fb923c"/>'
            . '<path d="M20 78 q28 -18 56 0 q-28 10 -56 0z" fill="' . $accent . '" opacity="0.85"/>'
            . '<path d="M46 60 q2 3 4 0" stroke="#7c2d12" stroke-width="2" fill="none" stroke-linecap="round"/>'
            . self::face();
    }

    private static function dokkaebi(string $accent): string
    {
        return self::humanHead(fill: '#93c5fd')
            . '<path d="M34 32 q-6 -16 8 -20 q0 10 -4 20z" fill="#1e3a8a"/>'
            . '<path d="M62 32 q6 -16 -8 -20 q0 10 4 20z" fill="#1e3a8a"/>'
            . '<ellipse cx="78" cy="66" rx="8" ry="16" fill="' . $accent . '"/>'
            . '<circle cx="74" cy="58" r="2.5" fill="#1e3a8a"/>'
            . '<circle cx="82" cy="62" r="2.5" fill="#1e3a8a"/>'
            . '<circle cx="76" cy="72" r="2.5" fill="#1e3a8a"/>'
            . self::face();
    }

    private static function haechi(string $accent): string
    {
        return '<circle cx="74" cy="50" r="9" fill="#f59e0b"/><circle cx="66" cy="68" r="9" fill="#f59e0b"/>'
            . '<circle cx="48" cy="76" r="9" fill="#f59e0b"/><circle cx="30" cy="68" r="9" fill="#f59e0b"/>'
            . '<circle cx="22" cy="50" r="9" fill="#f59e0b"/><circle cx="30" cy="32" r="9" fill="#f59e0b"/>'
            . '<circle cx="48" cy="24" r="9" fill="#f59e0b"/><circle cx="66" cy="32" r="9" fill="#f59e0b"/>'
            . '<circle cx="48" cy="50" r="20" fill="#fde68a"/>'
            . '<circle cx="36" cy="30" r="5" fill="#fde68a"/><circle cx="60" cy="30" r="5" fill="#fde68a"/>'
            . '<ellipse cx="48" cy="67" rx="8" ry="6" fill="#fde68a"/>'
            . '<circle cx="48" cy="70" r="2.2" fill="#1f2937"/>'
            . '<path d="M44 20 q4 -8 8 0" fill="none" stroke="' . $accent . '" stroke-width="3" stroke-linecap="round"/>'
            . self::face();
    }

    private static function erudit(string $accent): string
    {
        return self::humanHead()
            . '<path d="M30 74 q18 -10 36 0 l-3 8 l-30 0 z" fill="' . $accent . '"/>'
            . '<ellipse cx="48" cy="34" rx="38" ry="6" fill="#1e293b"/>'
            . '<circle cx="48" cy="22" r="12" fill="#1e293b"/>'
            . '<rect x="82" y="46" width="6" height="30" rx="3" fill="' . $accent . '"/>'
            . '<path d="M82 46 q6 4 6 14" fill="none" stroke="' . $accent . '" stroke-width="2"/>'
            . self::face();
    }

    private static function tigre(string $accent): string
    {
        return '<circle cx="48" cy="52" r="25" fill="#fb923c"/>'
            . '<circle cx="26" cy="34" r="8" fill="#fb923c"/>'
            . '<circle cx="70" cy="34" r="8" fill="#fb923c"/>'
            . '<path d="M36 30 q4 6 0 10 M60 30 q-4 6 0 10 M48 26 q3 6 0 10" stroke="#1f2937" stroke-width="3" fill="none" stroke-linecap="round"/>'
            . '<ellipse cx="48" cy="60" rx="8" ry="5" fill="' . $accent . '" opacity="0.5"/>'
            . self::face();
    }

    private static function zombie(string $accent): string
    {
        return self::humanHead(fill: '#bef264')
            . '<path d="M28 42 q20 -12 40 0" fill="none" stroke="' . $accent . '" stroke-width="2" stroke-linecap="round"/>'
            . '<rect x="30" y="70" width="36" height="14" rx="6" fill="#e2e8f0"/>'
            . '<rect x="44" y="70" width="8" height="14" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function idole(string $accent): string
    {
        return self::humanHead()
            . '<path d="M24 46 Q24 20 48 20 Q72 20 72 46 Q60 36 48 36 Q36 36 24 46 Z" fill="#1f2937"/>'
            . '<path d="M70 44 Q82 54 58 65" fill="none" stroke="' . $accent . '" stroke-width="2.5" stroke-linecap="round"/>'
            . '<circle cx="58" cy="65" r="3" fill="' . $accent . '"/>'
            . '<rect x="14" y="78" width="68" height="14" rx="4" fill="#1f2937"/>'
            . '<rect x="14" y="78" width="68" height="5" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function danseur(string $accent): string
    {
        return self::humanHead()
            . '<path d="M14 34 Q48 50 82 34 Q78 14 48 14 Q18 14 14 34 Z" fill="#1f2937"/>'
            . '<ellipse cx="48" cy="18" rx="22" ry="6" fill="#1f2937"/>'
            . '<path d="M10 92 Q10 62 48 62 Q86 62 86 92 Z" fill="#1f2937"/>'
            . '<rect x="34" y="62" width="28" height="7" rx="3.5" fill="' . $accent . '"/>'
            . self::face();
    }

    private static function fan(string $accent): string
    {
        return self::humanHead(50, '#fde68a')
            . '<rect x="26" y="30" width="44" height="7" rx="3.5" fill="' . $accent . '"/>'
            . '<rect x="70" y="40" width="6" height="26" rx="3" fill="#e5e7eb"/>'
            . '<circle cx="73" cy="36" r="7" fill="' . $accent . '" class="filmi-lightstick"/>'
            . self::face();
    }

    private static function trainee(string $accent): string
    {
        return self::humanHead()
            . '<path d="M24 44 q0 -24 24 -24 q24 0 24 24 q-8 -6 -24 -6 q-16 0 -24 6z" fill="#7c2d12"/>'
            . '<path d="M28 60 L74 84" stroke="' . $accent . '" stroke-width="6" stroke-linecap="round"/>'
            . '<rect x="80" y="66" width="8" height="16" rx="3" fill="' . $accent . '"/>'
            . '<path d="M40 58 q4 3 8 0 M48 58 q4 3 8 0" stroke="#7c2d12" stroke-width="2" fill="none" stroke-linecap="round" opacity="0.6"/>'
            . self::face();
    }
}
