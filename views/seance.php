<?php
use App\Utils\FormatUtils;
use App\Utils\Security;

$isSeries = ($movie['kind'] ?? 'film') === 'series';
$endTime = $isSeries ? null : FormatUtils::endTime($startTime, $movie['runtime'] === null ? null : (int) $movie['runtime']);
$scoresByName = array_column($ratings, 'score', 'name');
?>
<p class="text-xs uppercase tracking-wide text-slate-400">
    <?= Security::e(FormatUtils::frenchDate($seance['date'])) ?>,
    au tour des <?= $seance['chooser_side'] === 'kid' ? 'filles' : 'parents' ?>
</p>
<h1 class="mt-1 text-2xl font-semibold">
    <a href="/movie.php?id=<?= (int) $movie['id'] ?>" class="hover:underline">
        <?= Security::e($movie['title']) ?>
    </a>
</h1>
<a href="/movie.php?id=<?= (int) $movie['id'] ?>"
   class="inline-block text-xs text-slate-400 underline">
    Voir la fiche, modifier ou archiver
</a>
<?php if ($isSeries): ?>
    <p class="text-sm text-slate-400">
        <?php if (!empty($seance['episodes_label'])): ?>
            <?= Security::e($seance['episodes_label']) ?> · début <?= Security::e($startTime) ?>
        <?php endif; ?>
        <?= $movie['status'] === 'watched' ? ' · série terminée' : ' · la série continue' ?>
    </p>
<?php else: ?>
    <p class="text-sm text-slate-400">
        <?= $movie['year'] !== null ? (int) $movie['year'] . ' · ' : '' ?>
        <?= Security::e(FormatUtils::humanRuntime($movie['runtime'] === null ? null : (int) $movie['runtime'])) ?>
        <?= $endTime !== null ? ' · début ' . Security::e($startTime) . ', fin vers ' . Security::e($endTime) : '' ?>
    </p>
<?php endif; ?>

<div class="mt-4 flex gap-4">
    <?php if (!empty($movie['poster_url'])): ?>
        <img src="<?= Security::e($movie['poster_url']) ?>" alt=""
             class="h-56 w-36 shrink-0 rounded-2xl object-cover bg-slate-800">
    <?php endif; ?>
    <div class="min-w-0 space-y-2 text-sm">
        <?php if (!empty($movie['director'])): ?>
            <p class="text-slate-400">de <?= Security::e($movie['director']) ?></p>
        <?php endif; ?>
        <?php if (!empty($movie['overview'])): ?>
            <p class="text-slate-300"><?= Security::e($movie['overview']) ?></p>
        <?php endif; ?>
        <?php if (!empty($movie['memo'])): ?>
            <p class="italic text-slate-400">« <?= Security::e($movie['memo']) ?> »</p>
        <?php endif; ?>
    </div>
</div>

<section class="mt-6 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
    <?php if ($ratingAllowed && $ratingSkipped): ?>
        <h2 class="text-sm font-medium">Tu ne notes pas cette séance</h2>
        <form method="post" class="mt-2">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
            <input type="hidden" name="action" value="reopen_rating">
            <button class="rounded-xl bg-white/10 px-3 py-2 text-sm">Tu veux quand même noter ?</button>
        </form>
    <?php elseif ($ratingAllowed): ?>
        <h2 class="text-sm font-medium">Ta note, après le film</h2>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <form method="post" class="flex gap-2">
                <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="action" value="rate">
                <?php for ($score = 1; $score <= 5; $score++): ?>
                    <button name="score" value="<?= $score ?>"
                            class="h-11 w-11 rounded-xl text-lg <?= $myScore === $score ? 'bg-amber-400/30 ring-2 ring-amber-300' : 'bg-white/10' ?>">
                        <?= $score ?>
                    </button>
                <?php endfor; ?>
            </form>
            <?php if ($myScore === 0): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                    <input type="hidden" name="action" value="skip_rating">
                    <button title="Je ne note pas cette séance" aria-label="Je ne note pas cette séance"
                            class="h-11 w-11 rounded-xl bg-white/5 text-lg text-slate-400">&times;</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($ratings !== []): ?>
            <ul class="mt-3 space-y-1 text-sm text-slate-300">
                <?php foreach ($scoresByName as $name => $score): ?>
                    <li><?= Security::e((string) $name) ?> : <?= (int) $score ?> sur 5</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($ratingSkips !== []): ?>
            <ul class="mt-1 space-y-1 text-sm text-slate-500">
                <?php foreach ($ratingSkips as $passe): ?>
                    <li><?= Security::e((string) $passe['name']) ?> : ne note pas</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <h2 class="text-sm font-medium">La série continue</h2>
        <p class="mt-1 text-sm text-slate-300">
            La note sera demandée à la fin de la série, sur l'œuvre entière.
        </p>
    <?php endif; ?>
</section>

<?php if ($canVeto): ?>
    <section class="mt-4 rounded-2xl bg-rose-500/10 p-4 ring-1 ring-rose-400/20">
        <h2 class="text-sm font-medium text-rose-100">Droit de veto</h2>
        <p class="mt-1 text-xs text-rose-200/80">
            Le film retourne dans la liste, les filles en choisissent un autre. Chaque veto est enregistré.
        </p>
        <form method="post" class="mt-2 flex flex-wrap gap-2">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
            <input type="hidden" name="action" value="veto">
            <input name="reason" maxlength="120" placeholder="Motif, facultatif"
                   class="min-w-0 flex-1 rounded-xl bg-white/10 px-3 py-2 text-sm">
            <button class="rounded-xl bg-rose-500/80 px-3 py-2 text-sm font-medium">Poser un veto</button>
        </form>
    </section>
<?php endif; ?>
