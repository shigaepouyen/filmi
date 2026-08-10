<?php
use App\Utils\Security;

$title = $awards['year'] === null ? 'depuis le début' : 'en ' . $awards['year'];
?>
<div class="mb-4 flex flex-wrap items-center gap-2">
    <h1 class="text-2xl font-semibold">Palmarès <?= Security::e($title) ?></h1>
    <select class="ml-auto rounded-xl bg-white/10 px-2 py-1.5 text-sm"
            onchange="location.href='?year=' + this.value">
        <?php foreach ($years as $item): ?>
            <option value="<?= (int) $item ?>" <?= $selected === (string) $item ? 'selected' : '' ?>>
                <?= (int) $item ?>
            </option>
        <?php endforeach; ?>
        <option value="all" <?= $selected === 'all' ? 'selected' : '' ?>>Tout l'historique</option>
    </select>
    <a href="?year=<?= Security::e($selected) ?>&print=1" target="_blank"
       class="rounded-xl bg-white/10 px-3 py-1.5 text-sm">Version imprimable</a>
</div>

<section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    <?php
    $tiles = [
        'films vus' => (string) $awards['watched'],
        'samedis sautés' => (string) $awards['skipped'],
        'dérogations' => (string) $awards['derogations'],
        'note moyenne' => $awards['avg_score'] === null ? 'aucune' : (string) $awards['avg_score'],
    ];
    foreach ($tiles as $label => $value):
    ?>
        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
            <span class="block text-2xl font-semibold"><?= Security::e($value) ?></span>
            <span class="text-xs text-slate-400"><?= Security::e($label) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<?php foreach (['best' => 'Meilleur film', 'worst' => 'Le plus mal noté'] as $key => $label): ?>
    <?php if ($awards[$key] !== null): ?>
        <section class="mt-4 flex items-center gap-3 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
            <?php if (!empty($awards[$key]['movie_poster'])): ?>
                <img src="<?= Security::e($awards[$key]['movie_poster']) ?>" alt=""
                     class="h-24 w-16 rounded-xl object-cover bg-slate-800">
            <?php endif; ?>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400"><?= Security::e($label) ?></p>
                <p class="font-medium"><?= Security::e($awards[$key]['movie_title']) ?></p>
                <p class="text-sm text-amber-200"><?= Security::e((string) $awards[$key]['avg_score']) ?> sur 5</p>
                <p class="text-xs text-slate-500">proposé par <?= Security::e($awards[$key]['proposer_name']) ?></p>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<section class="mt-4 grid gap-3 sm:grid-cols-2">
    <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
        <h2 class="text-sm font-medium">Films retenus par proposeur</h2>
        <ul class="mt-2 space-y-1 text-sm text-slate-300">
            <?php foreach ($awards['by_proposer'] as $name => $total): ?>
                <li><?= Security::e((string) $name) ?> : <strong><?= (int) $total ?></strong></li>
            <?php endforeach; ?>
            <?php if ($awards['by_proposer'] === []): ?>
                <li class="text-slate-500">aucun film retenu sur la période</li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
        <h2 class="text-sm font-medium">Répartition des tours</h2>
        <p class="mt-2 text-sm text-slate-300">
            Parents : <strong><?= (int) $awards['by_side']['adult'] ?></strong><br>
            Filles : <strong><?= (int) $awards['by_side']['kid'] ?></strong>
        </p>
        <?php if ($awards['vetoes'] !== []): ?>
            <h2 class="mt-3 text-sm font-medium">Vetos</h2>
            <ul class="mt-1 text-sm text-slate-300">
                <?php foreach ($awards['vetoes'] as $veto): ?>
                    <li><?= Security::e($veto['name']) ?> : <?= (int) $veto['total'] ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
