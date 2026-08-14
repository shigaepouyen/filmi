<?php
use App\Utils\FormatUtils;
use App\Utils\Security;

$periode = $awards['year'] === null ? 'depuis le début' : 'en ' . $awards['year'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Filmi, palmarès <?= Security::e($periode) ?></title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; color: #1f2937; margin: 2.5cm; }
        h1 { font-size: 26px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 24px 0 8px; }
        p.sub { color: #6b7280; margin: 0 0 24px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        th { color: #6b7280; font-weight: normal; text-transform: uppercase; font-size: 11px; }
        .chiffres { display: flex; gap: 28px; margin-bottom: 24px; }
        .chiffres div { font-size: 13px; color: #6b7280; }
        .chiffres strong { display: block; font-size: 24px; color: #1f2937; }
        .posters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .posters img { width: 64px; height: 96px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb; }
        ul.anecdotes { margin: 0 0 16px; padding-left: 18px; font-size: 13px; }
        ul.anecdotes li { margin-bottom: 4px; }
        @media print { body { margin: 1.5cm; } }
    </style>
</head>
<body>
    <h1>Le samedi soir de la famille</h1>
    <p class="sub">Palmarès <?= Security::e($periode) ?></p>

    <div class="chiffres">
        <div><strong><?= (int) $awards['watched'] ?></strong>œuvres vues</div>
        <div><strong><?= (int) $awards['skipped'] ?></strong>samedis sautés</div>
        <div><strong><?= $awards['total_minutes'] > 0 ? Security::e(FormatUtils::humanRuntime($awards['total_minutes'])) : '0 min' ?></strong>sur le canapé</div>
        <div><strong><?= $awards['avg_score'] === null ? 'aucune' : Security::e((string) $awards['avg_score']) ?></strong>note moyenne</div>
    </div>

    <?php if ($awards['podium'] !== []): ?>
        <h2>Podium</h2>
        <div class="posters">
            <?php foreach ($awards['podium'] as $rank => $entry): ?>
                <?php if (!empty($entry['movie_poster'])): ?>
                    <img src="<?= Security::e($entry['movie_poster']) ?>" alt="">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <table>
            <thead><tr><th>Rang</th><th>Œuvre</th><th>Note</th><th>Proposé par</th></tr></thead>
            <tbody>
            <?php foreach ($awards['podium'] as $rank => $entry): ?>
                <tr>
                    <td><?= $rank + 1 ?></td>
                    <td><?= Security::e((string) $entry['movie_title']) ?></td>
                    <td><?= Security::e((string) $entry['avg_score']) ?> / 5</td>
                    <td><?= Security::e((string) $entry['proposer_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($awards['longest'] !== null || $awards['oldest'] !== null || $awards['top_decade'] !== null): ?>
        <h2>Anecdotes</h2>
        <ul class="anecdotes">
            <?php if ($awards['longest'] !== null): ?>
                <li>Le plus long : <?= Security::e((string) $awards['longest']['movie_title']) ?>
                    (<?= Security::e(FormatUtils::humanRuntime((int) $awards['longest']['duration_minutes'])) ?>)</li>
            <?php endif; ?>
            <?php if ($awards['shortest'] !== null && $awards['shortest']['movie_id'] !== ($awards['longest']['movie_id'] ?? null)): ?>
                <li>Le plus court : <?= Security::e((string) $awards['shortest']['movie_title']) ?>
                    (<?= Security::e(FormatUtils::humanRuntime((int) $awards['shortest']['duration_minutes'])) ?>)</li>
            <?php endif; ?>
            <?php if ($awards['oldest'] !== null): ?>
                <li>Le plus ancien : <?= Security::e((string) $awards['oldest']['movie_title']) ?> (<?= (int) $awards['oldest']['movie_year'] ?>)</li>
            <?php endif; ?>
            <?php if ($awards['newest'] !== null && $awards['newest']['movie_id'] !== ($awards['oldest']['movie_id'] ?? null)): ?>
                <li>Le plus récent : <?= Security::e((string) $awards['newest']['movie_title']) ?> (<?= (int) $awards['newest']['movie_year'] ?>)</li>
            <?php endif; ?>
            <?php if ($awards['top_decade'] !== null): ?>
                <li>La décennie fétiche : les années <?= (int) $awards['top_decade']['decade'] ?>
                    (<?= (int) $awards['top_decade']['count'] ?> œuvre<?= (int) $awards['top_decade']['count'] > 1 ? 's' : '' ?>)</li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>

    <h2>Par personne</h2>
    <table>
        <thead><tr><th>Prénom</th><th>Films retenus</th><th>Note moyenne donnée</th><th>Vetos</th></tr></thead>
        <tbody>
        <?php foreach ($awards['by_person'] as $person): ?>
            <tr>
                <td><?= Security::e($person['name']) ?></td>
                <td><?= (int) $person['proposed'] ?></td>
                <td><?= $person['avg_given'] === null ? 'aucune' : Security::e((string) $person['avg_given']) ?></td>
                <td><?= (int) $person['veto_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Qui a choisi</h2>
    <table>
        <thead><tr><th>Tour</th><th>Séances</th></tr></thead>
        <tbody>
            <tr><td>Parents</td><td><?= (int) $awards['by_side']['adult'] ?></td></tr>
            <tr><td>Filles</td><td><?= (int) $awards['by_side']['kid'] ?></td></tr>
        </tbody>
    </table>
</body>
</html>
