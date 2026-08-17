<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Utils\Avatars;
use PDO;

final class ProfileRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** Adultes d'abord, puis enfants, dans l'ordre de création. */
    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM profiles ORDER BY CASE side WHEN 'adult' THEN 0 ELSE 1 END, id"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM profiles WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM profiles WHERE slug = ?');
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public function bySide(string $side): array
    {
        $stmt = $this->db->prepare('SELECT * FROM profiles WHERE side = ? ORDER BY id');
        $stmt->execute([$side]);

        return $stmt->fetchAll();
    }

    public const NAME_MAX = 30;

    /**
     * L'avatar inconnu est ignoré, l'ancien est conservé. Le slug et le camp ne
     * bougent pas. Une couleur hors palette est ignorée de la même façon : sans
     * ce filtre elle serait écrite en base pour finir remplacée en silence par la
     * couleur de repli à chaque affichage.
     */
    public function update(int $id, string $name, string $avatar, string $color): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException("Le nom du profil ne peut pas être vide.");
        }
        $name = mb_substr($name, 0, self::NAME_MAX);

        $sets = ['name = ?'];
        $params = [$name];

        if (Avatars::exists($avatar)) {
            $sets[] = 'avatar = ?';
            $params[] = $avatar;
        }

        if (Avatars::colorExists($color)) {
            $sets[] = 'color = ?';
            $params[] = $color;
        }

        $params[] = $id;

        $this->db->prepare(
            'UPDATE profiles SET ' . implode(', ', $sets) . ' WHERE id = ?'
        )->execute($params);
    }
}
