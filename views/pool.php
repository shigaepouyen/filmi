<?php
use App\Utils\Security;
use App\Utils\ViewRenderer;

$tabs = ['adult' => 'Proposé par les parents', 'kid' => 'Proposé par les filles'];
$sorts = ['votes' => 'Les plus votés', 'recent' => 'Les plus récents', 'runtime' => 'Les plus courts'];
?>
<div class="mb-4 flex flex-wrap items-center gap-2">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="?pool=<?= $key ?>&sort=<?= Security::e($sort) ?>"
           class="rounded-xl px-3 py-1.5 text-sm <?= $pool === $key ? 'bg-white/15 font-medium' : 'bg-white/5 text-slate-300' ?>">
            <?= Security::e($label) ?> <span class="text-slate-400">(<?= (int) $counts[$key] ?>)</span>
        </a>
    <?php endforeach; ?>

    <select class="ml-auto rounded-xl bg-white/10 px-2 py-1.5 text-sm"
            onchange="location.href='?pool=<?= $pool ?>&sort=' + this.value">
        <?php foreach ($sorts as $key => $label): ?>
            <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= Security::e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php if ($movies === []): ?>
    <div class="rounded-2xl bg-white/5 p-8 text-center">
        <p class="text-slate-300">Cette liste est vide.</p>
        <a href="/add.php" class="mt-3 inline-block rounded-xl bg-violet-500 px-4 py-2 text-sm font-medium">
            Ajouter un film
        </a>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($movies as $movie): ?>
            <?= ViewRenderer::component('movie_card', [
                'movie' => $movie,
                'startTime' => $startTime,
                'myVotes' => $myVotes,
                'profile' => $profile,
            ]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
