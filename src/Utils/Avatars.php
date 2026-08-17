<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Catalogue d'avatars en pixel art.
 *
 * Chaque sprite est une grille 32x32 dessinee en ASCII, une ligne par rangee, un
 * caractere par pixel. '.' est transparent, '@' prend la couleur de theme du
 * profil, les autres lettres viennent de la palette propre au sprite : les
 * personnages ont chacun leurs teintes, une palette commune les aurait tous
 * ramenes aux memes gris.
 *
 * Conventions de dessin, communes aux vingt : contour d'un pixel noir pose
 * autour de la silhouette, lumiere en haut-gauche, trois niveaux de teinte,
 * fond transparent, vue de face, grosse tete. Chacun porte l'ecusson du profil,
 * seul element dont la couleur change d'une personne a l'autre.
 *
 * Le rendu decoupe le sprite en rectangles pleins, fusion horizontale puis
 * verticale, ce qui garde chaque sprite autour de la centaine de formes
 * malgre les 1024 pixels de la grille : les vingt avatars sont inlines sur la
 * page de choix de profil.
 *
 * Les sprites sont produits par un script de dessin garde hors du depot, puis
 * recopies ici : le fichier livre ne depend d'aucun outil pour etre lu ou
 * corrige a la main.
 */
final class Avatars
{
    public const FALLBACK = 'fantome';

    public const FAMILIES = [
        'nature' => 'Nature et exploration',
        'espace' => 'Espace et machines',
        'creatures' => 'Créatures',
        'magie' => 'Magie et mystère',
        'guerriers' => 'Guerriers',
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

    /** @return array<string, array{label: string, family: string}> */
    public static function all(): array
    {
        return [
            // Nature et exploration
            'ranger' => ['label' => 'Ranger des forêts', 'family' => 'nature'],
            'exploratrice' => ['label' => 'Exploratrice de la jungle', 'family' => 'nature'],
            'nomade' => ['label' => 'Nomade du désert', 'family' => 'nature'],
            // Espace et machines
            'pilote' => ['label' => 'Pilote spatial', 'family' => 'espace'],
            'robot' => ['label' => 'Robot compagnon', 'family' => 'espace'],
            'inventeur' => ['label' => 'Inventeur steampunk', 'family' => 'espace'],
            'chat' => ['label' => 'Chat astronaute', 'family' => 'espace'],
            // Créatures
            'dragon' => ['label' => 'Petit dragon', 'family' => 'creatures'],
            'panda' => ['label' => 'Panda aventurier', 'family' => 'creatures'],
            'fantome' => ['label' => 'Fantôme', 'family' => 'creatures'],
            'slime' => ['label' => 'Slime', 'family' => 'creatures'],
            'gardien' => ['label' => 'Gardien de cristal', 'family' => 'creatures'],
            // Magie et mystère
            'renard' => ['label' => 'Renard mage', 'family' => 'magie'],
            'elfe' => ['label' => 'Elfe des neiges', 'family' => 'magie'],
            'fee' => ['label' => 'Fée', 'family' => 'magie'],
            'corbeau' => ['label' => 'Voleur corbeau', 'family' => 'magie'],
            // Guerriers
            'ninja' => ['label' => 'Ninja cyber', 'family' => 'guerriers'],
            'viking' => ['label' => 'Viking', 'family' => 'guerriers'],
            'pirate' => ['label' => 'Capitaine pirate', 'family' => 'guerriers'],
            'champignon' => ['label' => 'Chevalier champignon', 'family' => 'guerriers'],
        ];
    }

    /**
     * @return array<string, array{palette: array<string, string>, rows: list<string>}>
     */
    private static function sprites(): array
    {
        return [
            'ranger' => [
                'palette' => ['p' => '#F2D2B6', 'v' => '#5C8E3E', 'V' => '#2F5A25', 'b' => '#704B2A', 'c' => '#7A5432', 'C' => '#4E351F', 'd' => '#8A6038', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................k...............',
                    '...........kkkkk@kkkk...........',
                    '..........kvvvv@@@vvVk..........',
                    '.........kvvvvVk@kvvvVk.........',
                    '........kvvvvvvvvvvvvvVk........',
                    '.......kVvvvvvvvvvvvvvvVk.......',
                    '........kVvvvvvvvvvvvvVk........',
                    '........kkvbbbbbbbbbbVkk........',
                    '.......kvvvbbbbbbbbbbvvVk.......',
                    '.......kvvvbbbbbbbbbbvvVk.......',
                    '.......kvvvppppppppppvvVk.......',
                    '.......kvvvppppppppppvvVk.......',
                    '.......kvvvpkkppppkkpvvVk.......',
                    '.......kvvvpkkppppkkpvvVk.......',
                    '.......kvvvppppppppppvvVk.......',
                    '.......kvvvppkppppkppvvVk.......',
                    '.......kvvvpppkkkkpppvvVk.......',
                    '.......kvvvppppppppppvvVk.......',
                    '.......kVVVppppppppppVVVk.......',
                    '........kkkkppppppppkkkk........',
                    '.......kcccccddddddccccCk.......',
                    '......kccccccddddddcccccCk......',
                    '.....kcccccccddddddccccccCk.....',
                    '.....kcccccccddddddccccccCk.....',
                    '.....kcccccccddddddccccccCk.....',
                    '.....kcccccccddddddccccccCk.....',
                    '.....kCccccccddddddccccccCk.....',
                    '......kCcccccddddddcccccCk......',
                    '.......kCCCCCddddddCCCCCk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'pilote' => [
                'palette' => ['o' => '#FF8A00', 'O' => '#B85F00', 'c' => '#8FEAFF', 'C' => '#D6F8FF', 'w' => '#F5F5F5', 'g' => '#A0A0A0', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '...........kkkkkkkkkk...........',
                    '..........koooooooooOk..........',
                    '.........koooooooooooOk.........',
                    '........koooooooooooooOk........',
                    '.......koOOooooooooooOOOk.......',
                    '......koOkkcccccccccckkoOk......',
                    '......koOkCCCCCccccccckoOk......',
                    '......koocCCCCCccccccccoOk......',
                    '......kooccccccccccccccoOk......',
                    '......kooccccccccccccccoOk......',
                    '......kooccccccccccccccoOk......',
                    '......kooccccccccccccccoOk......',
                    '......kooccccccccccccccoOk......',
                    '......kooccccccccccccccoOk......',
                    '......kOOkcccccccccccckoOk......',
                    '.......kOkkcccccccccckkOk.......',
                    '........kOooooooooooooOk........',
                    '.........kOooooooooooOk.........',
                    '........kkkoooooooooOkkk........',
                    '.......kggggggggggggggggk.......',
                    '......kwggggkkkkkkgggggggk......',
                    '.....kwwggggk@@@@kggggggwgk.....',
                    '.....kwwwwwgk@@@@kggwwwwwgk.....',
                    '.....kwwwwwgk@@@@kggwwwwwgk.....',
                    '.....kwwwwwgkkkkkkggwwwwwgk.....',
                    '.....kgwwwwwwwwwwwwwwwwwwgk.....',
                    '......kgwwwwwwwwwwwwwwwwgk......',
                    '.......kggggggggggggggggk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'dragon' => [
                'palette' => ['t' => '#2EC4B6', 'T' => '#1B7F76', 'n' => '#FFF5CC', 'i' => '#FFF5CC', 'y' => '#E6C34D', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '.........k............k.........',
                    '........kik..........kik........',
                    '........kiik........kiik........',
                    '........kiikkkkkkkkkkiik........',
                    '.........kiittttttttiik.........',
                    '.........ktttttttttttTk.........',
                    '........ktttttttttttttTk........',
                    '.......ktttttttttttttttTk.......',
                    '.......ktttttttttttttttTk.......',
                    '.......kttttTttttttTtttTk.......',
                    '.......ktttTkyttttyktttTk.......',
                    '.......kttttyyttttyytttTk.......',
                    '.......ktttttttttttttttTk.......',
                    '.......ktttttttttttttttTk.......',
                    '.......ktttttttttttttttTk.......',
                    '.......kttttnnnnnnnntttTk.......',
                    '...kkk.kTtttnnnnnnnntttTk.kkk...',
                    '..kttTk.kTttnknnnnknttTk.kttTk..',
                    '..kTttTk.kTtnnnnnnnntTk.ktttTk..',
                    '...kTtTkkkktnnnnnnnnTkkkkttTk...',
                    '....kTTktttttttttttttttTkTTk....',
                    '.....kktttttnnnnnnnnttttTkk.....',
                    '.....kttttttnnnnnnnntttttTk.....',
                    '.....ktttttTkkkkkknntttttTk.....',
                    '.....ktttttTk@@@@knntttttTk.....',
                    '.....ktttttTk@@@@knntttttTk.....',
                    '.....kTttttTk@@@@knntttttTk.....',
                    '......kTtttTkkkkkknnttttTk......',
                    '.......kTTTTnnnnnnnnTTTTk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'robot' => [
                'palette' => ['g' => '#D9D9D9', 'G' => '#8A8A8A', 'c' => '#00B8FF', 'r' => '#FF4444', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '...............kk...............',
                    '........kkkkkkkrrkkkkkkk........',
                    '.......kgggggggggggggggGk.......',
                    '.......kgggggggggggggggGk.......',
                    '.......kgGGGGGGGGGGGGGGGk.......',
                    '.......kGkGGGGGGGGGGGGkGk.......',
                    '.......kggGGGGGGGGGGGGgGk.......',
                    '.......kggGGGGGGGGGGGGgGk.......',
                    '.......kggGGcccGGGcccGgGk.......',
                    '.......kggGGcccGGGcccGgGk.......',
                    '.......kggGGcccGGGcccGgGk.......',
                    '.......kggGGGGGGGGGGGGgGk.......',
                    '.......kggGGGGGGGGGGGGgGk.......',
                    '.......kggGGGGGGGGGGGGgGk.......',
                    '.......kggGGkkGkkGkkGGgGk.......',
                    '.......kgGGGkkkkkkkkGGGGk.......',
                    '.......kGkGGGGGGGGGGGGkGk.......',
                    '.......kggGGGGGGGGGGGGgGk.......',
                    '.......kgggggggggggggggGk.......',
                    '.......kgggggggggggggggGk.......',
                    '.......kggggggGGGGgggggGk.......',
                    '......kgggggggGGGGggggggGk......',
                    '.....kgggggGGGGGGGGggggggGk.....',
                    '.....kggggGkkkkkkkkggggggGk.....',
                    '.....kggggGk@@@@@@kggggggGk.....',
                    '.....kggggGk@@@@@@kggggggGk.....',
                    '.....kGgggGk@@@@@@kggggggGk.....',
                    '......kGggGkkkkkkkkgggggGk......',
                    '.......kGGGGGGGGGGGGGGGGk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'renard' => [
                'palette' => ['f' => '#F48C28', 'F' => '#B35F14', 'n' => '#FFF2D8', 'N' => '#D9C4A0', 'v' => '#6B3FA0', 'V' => '#472A6B', 'b' => '#7A4A28', 'c' => '#5CCBFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '........k..............k........',
                    '.......kFk............kFk.......',
                    '......kfFk............kfFk......',
                    '.....kffFk.kkkkkkkkkk.kFfFk.....',
                    '.....kfNk.kfffffffffFk.kNFk.....',
                    '.....kfNkkfffffffffffFkkNfck....',
                    '.....kFFkfffffffffffffFkffcck...',
                    '......kkffffffffffffffffcccck...',
                    '.......kffffFFffffFFffffcccck...',
                    '.......kfffFkkfffFkkffffccck....',
                    '.......kfFFFkkfffFkkFFFFkbbk....',
                    '.......kfffffNNNNNNffffFkbbk....',
                    '.......kfffffNnnnnNffffFkbbk....',
                    '.......kffffNnnnnnnNfffFkbbk....',
                    '.......kffffNnnnnnnNfffFkbbk....',
                    '.......kffffNnkkkknNfffFkbbk....',
                    '.......kFfffNnkkkknNfffFkbbk....',
                    '........kFffNnnkknnNffFkkbbk....',
                    '.........kFfNnnkknnNfFk.kbbk....',
                    '.......kkkkfNnnnnnnNFkkkkbbk....',
                    '......kvvvvVVVVVVVVvvvvvvbbk....',
                    '.....kvvvvVkkkkkkkkvvvvvvbbk....',
                    '.....kvvvvVk@@@@@@kvvvvvvbbk....',
                    '.....kvvvvVk@@@@@@kvvvvvvbbk....',
                    '.....kvvvvVk@@@@@@kvvvvvvbbk....',
                    '.....kVvvvVkkkkkkkkvvvvvvbbk....',
                    '......kVvvvvvvvvvvvvvvvvVbbk....',
                    '.......kVVVVVVVVVVVVVVVVkbbk....',
                    '........kkkkkkkkkkkkkkkk.kk.....',
                    '................................',
                ],
            ],
            'elfe' => [
                'palette' => ['w' => '#FFFFFF', 'W' => '#8FAFD4', 'p' => '#DDEFFF', 'c' => '#6CA7FF', 'C' => '#3F6CB8', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '...........kkkkkkkkkk...........',
                    '..........kwwwwwwwwwwk..........',
                    '.........kwwwwwwwwwwwwk.........',
                    '........kwwwwwwwwwwwwwwk........',
                    '........kwwwwwwwwwwwwwwk........',
                    '........kkwwwwwwwwwwwwkk........',
                    '.......kwwWWkWWkWWkWWkwwk.......',
                    '......kkwWppppppppppppWwkk......',
                    '.....kppWWppppppppppppWWppk.....',
                    '....kpppWWppppppppppppWWpppk....',
                    '...kppppWWpWWWppppWWWpWWppppk...',
                    '....kkppWWppkkppppkkppWWppkk....',
                    '......kkwWppkkppppkkppWwkk......',
                    '.......kwWppppppppppppWwk.......',
                    '.......kwWppppppppppppWwk.......',
                    '........kpppppkkkkpppppk........',
                    '.........kppppppppppppk.........',
                    '........k.kppppppppppk.k........',
                    '.......kCkkkppppppppkkkCk.......',
                    '......kcccccCCCCCCCCccccCk......',
                    '.....kcccccCkkkkkkCCcccccCk.....',
                    '.....kcccccCk@@@@kCCcccccCk.....',
                    '.....kcccccCk@@@@kCCcccccCk.....',
                    '.....kcccccCk@@@@kCCcccccCk.....',
                    '.....kCccccCkkkkkkCCcccccCk.....',
                    '......kCccccCCCCCCCCccccCk......',
                    '.......kCCCCCCCCCCCCCCCCk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'ninja' => [
                'palette' => ['k' => '#111111', 'g' => '#444444', 'G' => '#2A2A2A', 'r' => '#DD2222', 'R' => '#FF6666', 'w' => '#999999'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '...........kkkkkkkkkk...........',
                    '..........kkkkkkkkkkkk..........',
                    '.........kkkkkkkkkkkkkk.........',
                    '........kkkkkkkkkkkkkkkk........',
                    '........kkkkkkkkkkkkkkkk........',
                    '........kkkkkkkkkkkkkkkk........',
                    '........kkkkkkkkkkkkkkkk........',
                    '.......kgggggggggggggggGk.......',
                    '.......kggrRRRRrrrrrrrgGkkk.....',
                    '.......kggrrrrrrrrrrrrgGkkk.....',
                    '.......kggrrrrrrrrrrrrgGk.kk....',
                    '.......kGGGGGGGGGGGGGGGGk.kk....',
                    '........kkkkkkkkkkkkkkkk........',
                    '........kkkkkkkkkkkkkkkk........',
                    '.........kkkkkkkkkkkkkk.........',
                    '..........kkkkkkkkkkkk..........',
                    '........kkkkkkkkkkkkkkkk........',
                    '.......kgggggggggggggggGk.......',
                    '......kgggggggggggggggggGk......',
                    '.....kgggggggggggggggggggGk.....',
                    '.....krrrrrrrrrrrrrrrrrrrrk.....',
                    '.....krrrrrrkkkkkkrrrrrrrrk.....',
                    '.....krrrrrrk@@@@krrrrrrrrk.....',
                    '.....kGggggGk@@@@kgggggggGk.....',
                    '......kGgggGk@@@@kggggggGk......',
                    '.......kGGGGkkkkkkGGGGGGk.......',
                    '........kkkk......kkkkkk........',
                    '................................',
                ],
            ],
            'exploratrice' => [
                'palette' => ['a' => '#D9C08A', 'A' => '#A8905E', 'v' => '#6F8A45', 'V' => '#4A5E2C', 'b' => '#5A3A20', 'p' => '#F2D2B6', 'g' => '#8FEAFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '............kkkkkkkk............',
                    '...........kaaaaaaaAk...........',
                    '..........kaaaaaaaaaAk..........',
                    '.........kaaAAAAAAaaaAk.........',
                    '.........kaAkkkkkkaaaAk.........',
                    '.....kkkkAAAk@@@@kAAAAAkkkk.....',
                    '....kaaaAkkkk@@@@kaAkkkaaaAk....',
                    '....kAAAAaaAk@@@@kaaaaaAAAAk....',
                    '.....kkkkpppkkkkkkpppppkkkk.....',
                    '........kppppppppppppppk........',
                    '........kppppppppppppppk........',
                    '........kpppkkppppkkpppk........',
                    '........kpppkkppppkkpppk........',
                    '........kppppppppppppppk........',
                    '........kppppkppppkppppk........',
                    '........kpppppkkkkpppppk........',
                    '.........kppppppppppppk.........',
                    '..........kppppppppppk..........',
                    '........kkkkppppppppkkkk........',
                    '.......kvvbbvvvvvvvvbbvVk.......',
                    '......kvvvbbvvvvvvvvbbvvVk......',
                    '.....kvvvvbbvvvvvvvvbbvvvVk.....',
                    '.....kvvvvbbvVVVVVVvbbvvvVk.....',
                    '.....kvvvvbbVkkkkkkvbbvvvVk.....',
                    '.....kvvvvbbVkgkkgkvbbvvvVk.....',
                    '.....kVvvvbbVkkkkkkvbbvvvVk.....',
                    '......kVvvbbVkkkkkkvbbvvVk......',
                    '.......kVVbbVVVVVVVVbbVVk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'panda' => [
                'palette' => ['w' => '#FFFFFF', 'W' => '#D2D8E0', 'k' => '#111111', 'r' => '#D53030', 'e' => '#4E8A43'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '........kkk..........kkk........',
                    '.......kkkkkkkkkkkkkkkkkk.......',
                    '......k...kwwwwwwwwwWk...k......',
                    '......k..kwwwwwwwwwwwWk..k......',
                    '......k.kwwwwwwwwwwwwwWk.k......',
                    '.......kwwwWWWwwwwWWWwwWk.......',
                    '......kwwwWkkkWwwWkkkWwwWk......',
                    '......kwwWkkkkkWWkkkkkWwWk......',
                    '......kwWkkkWkkkkkkWkkkwWk......',
                    '......kwWkkkkkkkkkkkkkkwWk......',
                    '......kwWkkkkkkkkkkkkkkwWk......',
                    '......kwwWkkkkkwWkkkkkwwWk......',
                    '......kwwwWkkkWWWWkkkwwwWk......',
                    '......kWwwwwwWkkkwwwwwwwWk......',
                    '.......kWwwwwWkkkwwwwwwWk.......',
                    '........kWwwwWkkkwwwwwWk........',
                    '.........kWwwwwwwwwwwWk.........',
                    '......kkkkkwWWWWWWwwWkkkkk......',
                    '...kkkrrrrrrkkkkkkrrrrrrrrk.....',
                    '..keeerrrrrrk@@@@krrrrrrrrk.....',
                    '..keeerrrrrrk@@@@krrrrrrrrk.....',
                    '..keeerrrrrrk@@@@krrrrrrrrk.....',
                    '...kkkwwwwwWkkkkkkwwrrwwwWk.....',
                    '.....kwwwwwwwwwwwwwwrrwwwWk.....',
                    '.....kWwwwwwwwwwwwwwwwwwwWk.....',
                    '......kWwwwwwwwwwwwwwwwwWk......',
                    '.......kWWWWWWWWWWWWWWWWk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'fantome' => [
                'palette' => ['w' => '#FFFFFF', 'c' => '#D8EEFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '............kkk..kk.............',
                    '..........kkw@@kk@@kk...........',
                    '.........kwwck@@@@kwck..........',
                    '........kwwwckk@@kkwwck.........',
                    '.......kwwwwwwwwwwwwwwck........',
                    '......kwwwwwwwwwwwwwwwwck.......',
                    '.....kwwwwwwwwwwwwwwwwwwck......',
                    '.....kwwwwwwwwwwwwwwwwwwck......',
                    '....kwwwwwwwccwwwwccwwwwwck.....',
                    '....kwwwwwwckkwwwckkwwwwwck.....',
                    '....kwwwwwwckkwwwckkwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwcwwwwcwwwwwwck.....',
                    '....kwwwwwwwckcccckwwwwwwck.....',
                    '....kwwwwwwwwckkkkwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwcccccwwwwwwwwwwwwck.....',
                    '....kwwwcccccwwwwwwwwwwwwck.....',
                    '....kwwwcccccwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwwwwwwwwwwwwwwwwwwck.....',
                    '....kwwwccwwwccwwwccwwwccckk....',
                    '....kwwckkwwckkwwckkwwckkwwck...',
                    '....kccckkccckkccckkccckkccck...',
                    '.....kkk..kkk..kkk..kkk..kkk....',
                    '................................',
                ],
            ],
            'viking' => [
                'palette' => ['g' => '#AAAAAA', 'G' => '#777777', 'i' => '#F6E8C3', 'r' => '#B55322', 'R' => '#8A3A14', 'c' => '#4678B8', 'C' => '#2F5488', 'p' => '#F2D2B6', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '...........kkkkkkkkkk...........',
                    '.....kk...kgggggggggGk...kk.....',
                    '....kiik.kgggggGGggggGk.kiik....',
                    '...kiik.kggggggGGgggggGk.kiik...',
                    '...kiikkgggggggGGggggggGkkiik...',
                    '....kiikGggggggGGggggggGkiik....',
                    '.....kk.kGgggggGGgggggGk.kk.....',
                    '.........kGggggGGggggGk.........',
                    '.........kkggggGGgggGkk.........',
                    '........kppppppppppppppk........',
                    '........kpppkkppppkkpppk........',
                    '........kpppkkppppkkpppk........',
                    '........kppppppppppppppk........',
                    '.........kkrrrrrrrrrRkk.........',
                    '.........krrrpppppprrRk.........',
                    '........krrrrpkkkkprrrRk........',
                    '........krrrrpkkkkprrrRk........',
                    '........krrrrrrrrrrrrrRk........',
                    '........krrrrrrrrrrrrrRk........',
                    '.......kcRrrrrrrrrrrrrRCk.......',
                    '......kcCkRrrrrrrrrrrRkcCk......',
                    '.....kccCkkrrrrrrrrrRkkccCk.....',
                    '.....kccccccCCCCCCcccccccCk.....',
                    '.....kcccccCkkkkkkcccccccCk.....',
                    '.....kcccccCk@@@@kcccccccCk.....',
                    '.....kCccccCk@@@@kcccccccCk.....',
                    '......kCcccCk@@@@kccccccCk......',
                    '.......kCCCCkkkkkkCCCCCCk.......',
                    '........kkkk......kkkkkk........',
                    '................................',
                ],
            ],
            'inventeur' => [
                'palette' => ['o' => '#B87333', 'O' => '#7E4C1E', 'y' => '#FFD87A', 'b' => '#70513A', 'B' => '#4E3826', 'p' => '#F2D2B6', 'c' => '#8FEAFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '..........k.k.k.k.k.k...........',
                    '.........kykykykykykyk..........',
                    '.........kyyyyyyyyyyyyk.........',
                    '........kkyyyyyyyyyyyykk........',
                    '.......kooOkkkOooOkkkOoOk.......',
                    '.......koOkcckkoOkcckkoOk.......',
                    '.......koOkcckkoOkcckkoOk.......',
                    '.......kOOkkkkkoOkkkkkoOk.......',
                    '........kppkkkppppkkkppk........',
                    '........kppppppppppppppk........',
                    '........kpppkkppppkkpppk........',
                    '........kpppkkppppkkpppk........',
                    '........kppppppppppppppk........',
                    '........kppppkppppkppppk........',
                    '........kpppppkkkkpppppk........',
                    '.........kppppppppppppk.........',
                    '........k.kppppppppppk.k........',
                    '.......kBkkkppppppppkkkBk.......',
                    '......kbbbbbbBBBBBBbbbbbBk......',
                    '.....kbbBbbBbBBBBBBbbbbbbBk.....',
                    '.....kbBk@@kbBBBBBBbbbbbbBk.....',
                    '.....kbb@B@@bBBBBBBbbbbbbBk.....',
                    '.....kbb@@B@bBBBBBBbbbbbbBk.....',
                    '.....kBBk@@kbBBBBBBbbbbbbBk.....',
                    '......kBbbbbbBBBBBBbbbbbBk......',
                    '.......kBBBBBBBBBBBBBBBBk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'slime' => [
                'palette' => ['c' => '#4ECFFF', 'C' => '#2A9BC6', 'w' => '#FFFFFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '...............kk...............',
                    '............kkk@@kk.............',
                    '........kk.kccc@@cCkkk..........',
                    '.......kwwkccccccccccCk.........',
                    '.......kkwwcccccccccccCk........',
                    '......kccccccccccccccccCk.......',
                    '.....kccccccCCccccCCccccCk......',
                    '....kccccccCkkcccCkkcccccCk.....',
                    '....kccccccCkkcccCkkcccccCk.....',
                    '...kccccccccccccccccccccccCk....',
                    '...kcccccccccCccccCcccccccCk....',
                    '...kccccccccCkCCCCkcccccccCk....',
                    '...kcccccccccCkkkkccccccccCk....',
                    '...kccccccccccccccccccccccCk....',
                    '...kccccccccccccccccccccccCk....',
                    '...kccccccccccccccccccccccCk....',
                    '...kccccccccccccccccccccccCk....',
                    '...kccccccccCCCCCCCCccccccCk....',
                    '...kccccccccCCCCCCCCccccccCk....',
                    '...kCCCCCCCCcccccccCCCCCCCCk....',
                    '....kkkkkkkkCCCCCCCkkkkkkkk.....',
                    '............kkkkkkk.............',
                    '................................',
                ],
            ],
            'nomade' => [
                'palette' => ['a' => '#D6B178', 'A' => '#A88453', 'b' => '#8E643A', 'B' => '#5E4022', 'd' => '#C09A66', 'e' => '#4BAA5A', 'o' => '#D87829', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '...........kkkkkkkkkk...........',
                    '..........kaaaaaaaaaAk..........',
                    '.........kaaaaaaaaaaaAk.........',
                    '........kAaaaaaaaaaaaaAk........',
                    '........kkaaaaaaaaaaaAkk........',
                    '.......kaaaAAAAAAAAAAaaAk.......',
                    '.......kaaAddddddddddAaAk.......',
                    '.......kaaAddddddddddAaAk.......',
                    '.......kaaAkkkddddkkkaaAk.......',
                    '.......kaaAdeeddddeedAaAk.......',
                    '.......kaaAdeeddddeedAaAk.......',
                    '.......kaaAddddddddddAaAk.......',
                    '.......kaaAddddddddddAaAk.......',
                    '.......kaAAddddddddddAAAk.......',
                    '.......kAkkkkkkkkkkkkkkAk.......',
                    '.......kAkookkkkkkooookAk.......',
                    '.......kaoook@@@@koooooAk.......',
                    '.....kkkaoook@@@@koooooAk.......',
                    '....koobboook@@@@kooooobBk......',
                    '....koobBkookkkkkkooookbbBk.....',
                    '....koobBkkooooooooookkbbBk.....',
                    '.....kbbbbbbbbbbbbbbbbbbbBk.....',
                    '.....kbbbbbbbbbbbbbbbbbbbBk.....',
                    '.....kBbbbbbbbbbbbbbbbbbbBk.....',
                    '......kBbbbbbbbbbbbbbbbbBk......',
                    '.......kBBBBBBBBBBBBBBBBk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'chat' => [
                'palette' => ['g' => '#AAAAAA', 'G' => '#777777', 'i' => '#88DFFF', 'c' => '#3A6BFF', 'C' => '#24479E', 'w' => '#FFFFFF', 'e' => '#7BCF7A', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '............kkkkkkk.............',
                    '..........kkiiiiiiikk...........',
                    '........kkiiiiiiiiiiikk.........',
                    '.......kiiiiiiiiiiiiiiik........',
                    '......kiiGkkkiiiiiikkkgik.......',
                    '.....kiiigGkkiiiiiikkggiik......',
                    '.....kiiiGeGkiiiiiikGeGiik......',
                    '....kiiiikkkgggggggGkkkiiik.....',
                    '....kiiiikkgggggggggGkkiiik.....',
                    '...kiiiiikggGggggggGgGkiiiik....',
                    '...kiiiiiggGkeggggekgggiiiik....',
                    '...kiiiiigggeeggggeegggiiiik....',
                    '...kiiiiiggggggGGggggggiiiik....',
                    '...kiiiiigggGGGkkGGGgggiiiik....',
                    '...kiiiiigggGwwkkwwGgggiiiik....',
                    '...kiiiiwGGGwwkkkkwwGGGwiiik....',
                    '....kiiiiGggGwwwwwwGggGiiik.....',
                    '....kiiiikGgGwwwwwwGgGkiiik.....',
                    '.....kiiikkGGGGGGGGGGkkiik......',
                    '.....kiiikkkgggggggGkkkiik......',
                    '......kiiiiiiiiiiiiiiiiiCkk.....',
                    '.....kcciiiiiiiiiiiiiiiccGGk....',
                    '.....kccciiiiiiiiiiiiiicCkgGk...',
                    '.....kccccCkkkkkkkkcccccCkGGk...',
                    '.....kccccCk@@@@@@kcccccCkkGk...',
                    '.....kCcccCk@@@@@@kccccccCkk....',
                    '......kCccCk@@@@@@kcccccCk......',
                    '.......kCCCkkkkkkkkCCCCCk.......',
                    '........kkk........kkkkk........',
                    '................................',
                ],
            ],
            'pirate' => [
                'palette' => ['k' => '#111111', 'c' => '#2D4F8C', 'C' => '#1B3357', 'r' => '#DD4444', 'g' => '#BBBBBB', 'G' => '#8A8A8A', 'p' => '#F2D2B6', 'w' => '#FFFFFF'],
                'rows' => [
                    '................................',
                    '................................',
                    '.......k........................',
                    '......krk.......................',
                    '.....krrk...kkkkkkkk............',
                    '....krrrk..kkkkkkkkkk...........',
                    '....krrk..kk@@@@@@kkkk..........',
                    '....krk...kk@@@@@@kkkk..........',
                    '.....k.kk..k@@@@@@kkk..kk.......',
                    '....k.kkk..kkkkkkkkk...kkk.k....',
                    '....k..kkkkkkkkkkkkkkkkkk..k....',
                    '.........kppppppppppppk.........',
                    '........kppkkkkkpppppppk........',
                    '.........kkkkkkkkkkkkkk.........',
                    '........kppkkkkkppwkkppk........',
                    '........kppkkkkkppwkkppk........',
                    '........kppkkkkkppwwwppk........',
                    '.........kkkkkkkggggGkk.........',
                    '.........kgggppppppggGk.........',
                    '........kggggpkkkkpgggGk........',
                    '........kgggggggggggggGk........',
                    '.......kcGggggggggggggGCk.......',
                    '......kcCkGggggggggggGkcCk......',
                    '.....kccCkkgggggggggGkkccCk.....',
                    '.....kcccccccCCCCCCccccccCk.....',
                    '.....kcccccccCCCCCCccccccCk.....',
                    '.....kcccccccCCCCCCccccccCk.....',
                    '.....kCccccccCCCCCCccccccCk.....',
                    '......kCcccccCCCCCCcccccCk......',
                    '.......kCCCCCCCCCCCCCCCCk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'fee' => [
                'palette' => ['r' => '#F7A8D8', 'R' => '#C97BA8', 'e' => '#8BE3B5', 'E' => '#5CAF84', 'i' => '#DFFAFF', 'y' => '#FFD84D', 'p' => '#F2D2B6', 'w' => '#FFFFFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '...........kkkkkkkkkk...........',
                    '..........krrrrrrrrrrk..........',
                    '.........krrrrrrrrrrrrk.........',
                    '........krrrrrrrrrrrrrrk........',
                    '........krrrrrrrrrrrrrrk........',
                    '.......krrrrrrrrrrrrrrrrk.......',
                    '.......krrkRRkkRRkkRRkkrk.......',
                    '.......krRppppppppppppRrk.......',
                    '.......krRppppppppppppRrk.......',
                    '.......krRppppppppppppRrk.......',
                    '.......krRppkkppppkkppRrk.......',
                    '....kk.krRppkkppppkkppRrk.kk....',
                    '...kiikkrRppppppppppppRrkkiik...',
                    '..kiiiikrRppppppppppppRrkiiiik..',
                    '..kiiiiirRpppkppppkpppRkyykiik..',
                    '..kiiiikrRppppkkkkppppRy@@yiik..',
                    '...kiikkrRppppppppppppRkyykik...',
                    '....kk.krrkppppppppppkrrkk.k....',
                    '........kkkkppppppppkkkkwwk.....',
                    '.........keeeeeeeeeeeEkkwwk.....',
                    '........keeeeeeeeeeeeeEkwwk.....',
                    '........keeeeeeeeeeeeeEkwwk.....',
                    '.......kkeeeeeeeeeeeeeEkwwk.....',
                    '......kEEeeeeeeeeeeeeeeEwwk.....',
                    '.......kkEeeeeeeeeeeeeEkkk......',
                    '.........kEeeeeeeeeeeEk.........',
                    '..........kEEEEEEEEEEk..........',
                    '...........kkkkkkkkkk...........',
                    '................................',
                ],
            ],
            'champignon' => [
                'palette' => ['r' => '#DD4444', 'R' => '#A82A2A', 'w' => '#FFFFFF', 'g' => '#B0B0B0', 'G' => '#7E7E7E', 'e' => '#4A8F44', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '...........kkkkkkkkk............',
                    '........kkkrrrwwwrrRkkk.........',
                    '......kkrrrrrrwwwrrrrrRkk.......',
                    '.....krrwwwrrrwwwrrrrrrrRk......',
                    '....krrrwwwrrrrrrrrwwwrrrRk.....',
                    '....krrrwwwrrrrrrrrwwwrrrRk.....',
                    '...krrrrrrrwwwrrrrrwwwrrrrRk....',
                    '...krwwwrrrwwwrrrrrrrrwwwrRk....',
                    '...kRwwwrrrwwwrrrrrrrrwwwrRk....',
                    '....kwwwrrrrrrrrrrrrrrwwwrRk....',
                    '....kRrrrrrrrrrrrrrrrrrrrRk.....',
                    '.....kRRrrrrrrrrrrrrrrrRRk......',
                    '......kkRrrrrrrrrrrrRrRkk.......',
                    '........kggRrrRRRRrRkgGk........',
                    '........kgGkwwkkkkwwkgGk........',
                    '........kgGkkkkkkkkkkgGk........',
                    '........kgggggggggggggGk........',
                    '........kGggggggggggggGk........',
                    '.........kGggggggggggGk.........',
                    '....kkkkk.kGggggggggGk.kkkkk....',
                    '...keeeeekkkgggggggGkkkeeeeek...',
                    '...keeeeeggggggggggggggeeeeek...',
                    '...keeeeeggGGGGGGGGggggeeeeek...',
                    '...keeeeegGkkkkkkkkggggeeeeek...',
                    '...keeeeegGk@@@@@@kggggeeeeek...',
                    '...keeeeegGk@@@@@@kggggeeeeek...',
                    '...keeeeegGk@@@@@@kggggeeeeek...',
                    '...keeeeegGkkkkkkkkggggeeeeek...',
                    '...keeeeeGGGGGGGGGGGGGGeeeeek...',
                    '....kkkkkkkkkkkkkkkkkkkkkkkk....',
                    '................................',
                ],
            ],
            'corbeau' => [
                'palette' => ['k' => '#111111', 'd' => '#26262E', 'D' => '#17171C', 'v' => '#5F3FA0', 'V' => '#3E2870', 'g' => '#CCCCCC', 'y' => '#FFD84D'],
                'rows' => [
                    '................................',
                    '................................',
                    '................................',
                    '................................',
                    '..........kkkkkkkkkkkk..........',
                    '.........kvvvvvvvvvvvVk.........',
                    '........kvvvvvvvvvvvvvVk........',
                    '.......kvvvvvvvvvvvvvvvVk.......',
                    '.......kVvvvvvvvvvvvvvvVk.......',
                    '.......kkvvvvvvvvvvvvvVkk.......',
                    '......kvvvvvvvvvvvvvvvvvVk......',
                    '......kvvvdDDDDddDDDDdvvVk......',
                    '......kvvvDkkkkdDkkkkdvvVk......',
                    '......kvvvDkyykdDkyykdvvVk......',
                    '......kvvvDkyykdDkyykdvvVk......',
                    '......kvvvDkkkkdDkkkkdvvVk......',
                    '......kvvvdDddddddddDdvvVk......',
                    '......kvvvDkyyyyyyyykdvvVk......',
                    '......kvvvDkkyyyyyykkdvvVkk.....',
                    '......kvvvDk.kyyyyk.kdvvvggk....',
                    '......kvvvDk..kyyk..kDvvvggk....',
                    '......kvvVk.kkkkkkkk.kvvvggk....',
                    '......kvvVkkDDDDDDdDkkvvvggk....',
                    '.....kvvvvvVkkkkkkdvvvvvvggk....',
                    '.....kvvvvvVk@@@@kdvvvvvvggk....',
                    '.....kvvvvvVk@@@@kdvvvvvVggk....',
                    '.....kvvvvvVk@@@@kdvvvvVkkkk....',
                    '.....kVvvvvVkkkkkkdvvvvVkkkk....',
                    '......kVvvvvvddddddvvvvvVk......',
                    '.......kVVVVVDDDDDDVVVVVk.......',
                    '........kkkkkkkkkkkkkkkk........',
                    '................................',
                ],
            ],
            'gardien' => [
                'palette' => ['g' => '#707070', 'G' => '#4A4A4A', 'c' => '#3ADFFF', 'C' => '#1E9BB8', 'w' => '#C8FFFF', 'k' => '#111111'],
                'rows' => [
                    '................................',
                    '...............k................',
                    '..............kCk...............',
                    '............kkCcCkkk............',
                    '...........kgGkCkggGk...........',
                    '..........kgggggggggGk..........',
                    '.........kgggggggggggGk.........',
                    '........kgggggggggggggGk........',
                    '........kgggggggggggggGk........',
                    '........kgggggggggggggGk........',
                    '........kgggggggggggggGk........',
                    '........kggGGGGGGGGGGgGk........',
                    '........kgGkkkkkkkkkkgGk........',
                    '........kgGkwwwkkwwwkgGk........',
                    '......k.kgGkwwwkkwwwkgGk.k......',
                    '.....kCkkgGkkkkkkkkkkgGkkCk.....',
                    '....kcCkkgggggggggggggGkkcCk....',
                    '...kCcCkkGgggGGGGGGgggGkkccCk...',
                    '....kCCkkkGgGkkkkkkggGkkkcCk....',
                    '.....kggGkkGGkkkkkkgGkkggGk.....',
                    '....kgggGkkkgggggggGkkkgggGk....',
                    '...kgggggggggggggggggggggggGk...',
                    '..kgggggggggGGGGGGggggggggggGk..',
                    '..kggggggggGkkkkkkggggggggggGk..',
                    '..kggggggggGk@@@@kggggggggggGk..',
                    '..kggggggggGk@@@@kggggggggggGk..',
                    '..kGgggggggGk@@@@kggggggggggGk..',
                    '...kGggggggGkkkkkkgggggggggGk...',
                    '....kGggggggggggggggggggggGk....',
                    '.....kGGGGGGGGGGGGGGGGGGGGk.....',
                    '......kkkkkkkkkkkkkkkkkkkk......',
                    '................................',
                ],
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
        $sprite = self::sprites()[$key];
        $shapes = self::spriteToShapes($sprite['rows'], $sprite['palette'], $accent);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="%d" height="%d" '
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
     * plage. Sans la fusion verticale, un corps uni coute une forme par rangee
     * et les vingt avatars de la page de reglages pesent le double.
     *
     * @param list<string> $rows
     * @param array<string, string> $palette
     */
    private static function spriteToShapes(array $rows, array $palette, string $accent): string
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

                $fill = $char === '@' ? $accent : ($palette[$char] ?? '#f472b6');
                $shapes .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>',
                    $x,
                    $y,
                    $w,
                    $h,
                    $fill
                );
            }
        }

        return $shapes;
    }
}
