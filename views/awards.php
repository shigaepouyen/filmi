<?php
use App\Utils\Avatars;
use App\Utils\FormatUtils;
use App\Utils\Security;

$periode = $awards['year'] === null ? 'depuis le début' : 'en ' . $awards['year'];
$hasAnything = $awards['watched'] > 0;
$podium = $awards['podium'];
// Ordre d'affichage du podium : 2e à gauche, 1er au centre (plus grand), 3e à
// droite, comme sur une vraie estrade. Absent quand il y a moins de 3 œuvres.
$podiumSlots = [1 => $podium[1] ?? null, 0 => $podium[0] ?? null, 2 => $podium[2] ?? null];
$totalSide = max(1, $awards['by_side']['adult'] + $awards['by_side']['kid']);
?>
<div class="mb-5 flex flex-wrap items-center gap-2">
    <h1 class="font-serif text-2xl font-semibold tracking-tight">Palmarès <?= Security::e($periode) ?></h1>
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

<?php /* Bandeau façon pellicule : perforations en haut et en bas, chiffres clés au centre. */ ?>
<section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-950/60 via-slate-900 to-slate-900 p-5 ring-1 ring-white/10">
    <div class="absolute inset-x-0 top-0 flex justify-between px-3 py-1.5 opacity-40" aria-hidden="true">
        <?php for ($i = 0; $i < 12; $i++): ?><span class="h-2 w-3 rounded-[2px] bg-slate-950"></span><?php endfor; ?>
    </div>
    <div class="absolute inset-x-0 bottom-0 flex justify-between px-3 py-1.5 opacity-40" aria-hidden="true">
        <?php for ($i = 0; $i < 12; $i++): ?><span class="h-2 w-3 rounded-[2px] bg-slate-950"></span><?php endfor; ?>
    </div>

    <p class="font-serif text-xl italic text-slate-200">« Voilà notre année »</p>
    <p class="mt-0.5 text-xs text-slate-400">le samedi soir de la famille, <?= Security::e($periode) ?></p>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <?php
        $tiles = [
            'œuvres vues' => (string) $awards['watched'],
            'samedis sautés' => (string) $awards['skipped'],
            'sur le canapé' => $awards['total_minutes'] > 0 ? FormatUtils::humanRuntime($awards['total_minutes']) : '0 min',
            'note moyenne' => $awards['avg_score'] === null ? 'aucune' : $awards['avg_score'] . ' / 5',
        ];
        foreach ($tiles as $label => $value):
        ?>
            <div class="rounded-xl bg-white/5 p-3 text-center ring-1 ring-white/10">
                <span class="block text-2xl font-semibold"><?= Security::e($value) ?></span>
                <span class="text-[11px] text-slate-400"><?= Security::e($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!$hasAnything): ?>
    <section class="mt-4 rounded-2xl bg-white/5 p-8 text-center ring-1 ring-white/10">
        <p class="font-serif text-lg text-slate-200">Rien à raconter pour l'instant.</p>
        <p class="mt-1 text-sm text-slate-400">
            <?= $awards['skipped'] > 0
                ? "Un samedi sauté, mais aucun film encore retenu sur la période."
                : "Le premier samedi de la période écrira le début de cette histoire." ?>
        </p>
    </section>
<?php else: ?>

    <?php if ($awards['poster_wall'] !== []): ?>
        <section class="mt-6">
            <h2 class="font-serif text-lg font-medium text-slate-200">Toutes les affiches</h2>
            <div class="mt-3 grid grid-cols-3 gap-2.5 sm:grid-cols-4 md:grid-cols-6">
                <?php foreach ($awards['poster_wall'] as $i => $movie): ?>
                    <a href="/movie.php?id=<?= (int) $movie['movie_id'] ?>"
                       class="group block <?= $i % 3 === 0 ? '-rotate-2' : ($i % 3 === 1 ? 'rotate-1' : '-rotate-1') ?> transition duration-200 hover:rotate-0 hover:scale-[1.04]">
                        <div class="overflow-hidden rounded-lg bg-slate-800 shadow-lg shadow-black/40 ring-1 ring-white/10">
                            <?php if (!empty($movie['movie_poster'])): ?>
                                <img src="<?= Security::e($movie['movie_poster']) ?>" alt="" loading="lazy"
                                     class="aspect-[2/3] w-full object-cover">
                            <?php else: ?>
                                <div class="flex aspect-[2/3] w-full items-center justify-center p-1.5">
                                    <span class="text-center text-[10px] leading-tight text-slate-400">
                                        <?= Security::e((string) $movie['movie_title']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 truncate text-center text-[10px] text-slate-500 group-hover:text-slate-300">
                            <?= Security::e((string) $movie['movie_title']) ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($podium !== []): ?>
        <section class="mt-8">
            <h2 class="font-serif text-lg font-medium text-slate-200">Le podium</h2>
            <div class="mt-4 flex items-end justify-center gap-2.5 sm:gap-4">
                <?php foreach ($podiumSlots as $rank => $entry): ?>
                    <?php if ($entry === null) continue; ?>
                    <?php
                    $isFirst = $rank === 0;
                    $height = $isFirst ? 'h-40 sm:h-52' : 'h-28 sm:h-36';
                    $width = $isFirst ? 'w-24 sm:w-32' : 'w-20 sm:w-24';
                    $medal = [1 => 'argent', 0 => 'or', 2 => 'bronze'][$rank];
                    $medalColor = [1 => 'bg-slate-300 text-slate-900', 0 => 'bg-amber-400 text-amber-950', 2 => 'bg-orange-700/80 text-orange-50'][$rank];
                    ?>
                    <div class="flex flex-col items-center">
                        <a href="/movie.php?id=<?= (int) $entry['movie_id'] ?>"
                           class="relative block <?= $width ?> <?= $isFirst ? 'order-first' : '' ?>">
                            <span class="absolute -top-2.5 -left-2.5 z-10 flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold ring-2 ring-slate-900 <?= $medalColor ?>">
                                <?= $rank + 1 ?>
                            </span>
                            <div class="overflow-hidden rounded-xl bg-slate-800 shadow-xl shadow-black/50 ring-1 ring-white/10">
                                <?php if (!empty($entry['movie_poster'])): ?>
                                    <img src="<?= Security::e($entry['movie_poster']) ?>" alt=""
                                         class="aspect-[2/3] w-full object-cover">
                                <?php else: ?>
                                    <div class="flex aspect-[2/3] w-full items-center justify-center p-1.5">
                                        <span class="text-center text-[10px] text-slate-400"><?= Security::e((string) $entry['movie_title']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <p class="mt-1.5 max-w-[7rem] truncate text-center text-xs font-medium"><?= Security::e((string) $entry['movie_title']) ?></p>
                        <p class="text-[11px] text-amber-200"><?= Security::e((string) $entry['avg_score']) ?> / 5</p>
                        <div class="mt-1 <?= $height ?> w-full rounded-t-lg bg-white/5 ring-1 ring-white/10"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($awards['worst'] !== null && $awards['worst']['movie_id'] !== $podium[0]['movie_id']): ?>
                <p class="mt-4 text-center text-xs text-slate-500">
                    Et tout en bas du classement,
                    <a href="/movie.php?id=<?= (int) $awards['worst']['movie_id'] ?>" class="underline decoration-dotted">
                        <?= Security::e((string) $awards['worst']['movie_title']) ?>
                    </a>
                    n'a récolté que <?= Security::e((string) $awards['worst']['avg_score']) ?> / 5.
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="mt-8">
        <h2 class="font-serif text-lg font-medium text-slate-200">Portraits de la saison</h2>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <?php foreach ($awards['by_person'] as $person): ?>
                <?php
                $badges = [];
                if ($awards['most_generous'] !== null && $awards['most_generous']['id'] === $person['id'] && $person['ratings_count'] > 0) {
                    $badges[] = 'note généreusement';
                }
                if ($awards['toughest'] !== null && $awards['toughest']['id'] === $person['id']
                    && $person['ratings_count'] > 0
                    && $awards['toughest']['id'] !== ($awards['most_generous']['id'] ?? null)) {
                    $badges[] = 'plus sévère';
                }
                ?>
                <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10">
                    <div class="flex items-center gap-2">
                        <?= Avatars::render((string) ($person['avatar'] ?? Avatars::FALLBACK), (string) ($person['color'] ?? 'indigo'), 34) ?>
                        <span class="font-medium leading-tight"><?= Security::e($person['name']) ?></span>
                    </div>
                    <dl class="mt-2 space-y-1 text-xs text-slate-300">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">a proposé</dt>
                            <dd><?= (int) $person['proposed'] ?> retenu<?= (int) $person['proposed'] > 1 ? 's' : '' ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">note en moyenne</dt>
                            <dd><?= $person['avg_given'] === null ? 'n\'a pas encore noté' : Security::e((string) $person['avg_given']) . ' / 5' ?></dd>
                        </div>
                        <?php if ((int) $person['veto_count'] > 0): ?>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">a mis son veto</dt>
                                <dd><?= (int) $person['veto_count'] ?> fois</dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($badges !== []): ?>
                        <p class="mt-2 flex flex-wrap gap-1">
                            <?php foreach ($badges as $badge): ?>
                                <span class="rounded-full bg-violet-500/20 px-2 py-0.5 text-[10px] text-violet-100"><?= Security::e($badge) ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mt-8 grid gap-3 sm:grid-cols-2">
        <?php if ($awards['longest'] !== null || $awards['shortest'] !== null || $awards['oldest'] !== null || $awards['newest'] !== null || $awards['top_decade'] !== null): ?>
            <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                <h2 class="font-serif text-sm font-medium text-slate-200">Anecdotes</h2>
                <ul class="mt-2 space-y-1.5 text-sm text-slate-300">
                    <?php if ($awards['longest'] !== null): ?>
                        <li>Le plus long : <strong><?= Security::e((string) $awards['longest']['movie_title']) ?></strong>
                            (<?= Security::e(FormatUtils::humanRuntime((int) $awards['longest']['duration_minutes'])) ?>)</li>
                    <?php endif; ?>
                    <?php if ($awards['shortest'] !== null && $awards['shortest']['movie_id'] !== ($awards['longest']['movie_id'] ?? null)): ?>
                        <li>Le plus court : <strong><?= Security::e((string) $awards['shortest']['movie_title']) ?></strong>
                            (<?= Security::e(FormatUtils::humanRuntime((int) $awards['shortest']['duration_minutes'])) ?>)</li>
                    <?php endif; ?>
                    <?php if ($awards['oldest'] !== null): ?>
                        <li>Le plus ancien : <strong><?= Security::e((string) $awards['oldest']['movie_title']) ?></strong>
                            (<?= (int) $awards['oldest']['movie_year'] ?>)</li>
                    <?php endif; ?>
                    <?php if ($awards['newest'] !== null && $awards['newest']['movie_id'] !== ($awards['oldest']['movie_id'] ?? null)): ?>
                        <li>Le plus récent : <strong><?= Security::e((string) $awards['newest']['movie_title']) ?></strong>
                            (<?= (int) $awards['newest']['movie_year'] ?>)</li>
                    <?php endif; ?>
                    <?php if ($awards['top_decade'] !== null): ?>
                        <li>La décennie fétiche : <strong>les années <?= (int) $awards['top_decade']['decade'] ?></strong>
                            (<?= (int) $awards['top_decade']['count'] ?> œuvre<?= (int) $awards['top_decade']['count'] > 1 ? 's' : '' ?>)</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
            <h2 class="font-serif text-sm font-medium text-slate-200">Qui a choisi</h2>
            <div class="mt-3 space-y-2 text-xs">
                <?php foreach (['adult' => 'Parents', 'kid' => 'Filles'] as $side => $label): ?>
                    <div>
                        <div class="flex justify-between text-slate-400">
                            <span><?= $label ?></span>
                            <span><?= (int) $awards['by_side'][$side] ?></span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-white/5">
                            <div class="h-2 rounded-full <?= $side === 'adult' ? 'bg-sky-400/70' : 'bg-violet-400/70' ?>"
                                 style="width: <?= round($awards['by_side'][$side] / $totalSide * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ((int) $awards['derogations'] > 0): ?>
                <p class="mt-3 text-xs text-slate-500">
                    dont <?= (int) $awards['derogations'] ?> dérogation<?= (int) $awards['derogations'] > 1 ? 's' : '' ?> au tour normal
                </p>
            <?php endif; ?>
        </div>
    </section>

<?php endif; ?>
