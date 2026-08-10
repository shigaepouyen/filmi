<?php
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
        p.sub { color: #6b7280; margin: 0 0 24px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        th { color: #6b7280; font-weight: normal; text-transform: uppercase; font-size: 11px; }
        .chiffres { display: flex; gap: 28px; margin-bottom: 24px; }
        .chiffres div { font-size: 13px; color: #6b7280; }
        .chiffres strong { display: block; font-size: 24px; color: #1f2937; }
        @media print { body { margin: 1.5cm; } }
    </style>
</head>
<body>
    <h1>Le samedi soir de la famille</h1>
    <p class="sub">Palmarès <?= Security::e($periode) ?></p>

    <div class="chiffres">
        <div><strong><?= (int) $awards['watched'] ?></strong>films vus</div>
        <div><strong><?= (int) $awards['skipped'] ?></strong>samedis sautés</div>
        <div><strong><?= (int) $awards['derogations'] ?></strong>dérogations</div>
        <div><strong><?= $awards['avg_score'] === null ? 'aucune' : Security::e((string) $awards['avg_score']) ?></strong>note moyenne</div>
    </div>

    <?php if ($awards['best'] !== null): ?>
        <p><strong>Meilleur film :</strong> <?= Security::e($awards['best']['movie_title']) ?>,
           <?= Security::e((string) $awards['best']['avg_score']) ?> sur 5,
           proposé par <?= Security::e($awards['best']['proposer_name']) ?>.</p>
    <?php endif; ?>

    <table>
        <thead><tr><th>Proposeur</th><th>Films retenus</th></tr></thead>
        <tbody>
        <?php foreach ($awards['by_proposer'] as $name => $total): ?>
            <tr><td><?= Security::e((string) $name) ?></td><td><?= (int) $total ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table>
        <thead><tr><th>Tour</th><th>Séances</th></tr></thead>
        <tbody>
            <tr><td>Parents</td><td><?= (int) $awards['by_side']['adult'] ?></td></tr>
            <tr><td>Filles</td><td><?= (int) $awards['by_side']['kid'] ?></td></tr>
        </tbody>
    </table>
</body>
</html>
