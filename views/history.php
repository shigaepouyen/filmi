<?php
use App\Utils\FormatUtils;
use App\Utils\Security;

$statusLabels = [
    'done' => 'Vu',
    'skipped' => 'Pas de ciné',
    'planned' => 'À venir',
];
?>
<h1 class="mb-4 text-2xl font-semibold">Historique</h1>

<?php if ($vetoes !== []): ?>
    <section class="mb-5 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
        <h2 class="text-sm font-medium">Vetos posés</h2>
        <ul class="mt-2 flex flex-wrap gap-3 text-sm text-slate-300">
            <?php foreach ($vetoes as $veto): ?>
                <li><?= Security::e($veto['name']) ?> : <strong><?= (int) $veto['total'] ?></strong></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($seances === []): ?>
    <p class="rounded-2xl bg-white/5 p-8 text-center text-slate-300">
        Aucune séance enregistrée pour l'instant.
    </p>
<?php else: ?>
    <ol class="space-y-3">
        <?php foreach ($seances as $seance): ?>
            <?php
                $hasFilm = $seance['movie_title'] !== null;
                $tag = $hasFilm ? 'a' : 'div';
            ?>
            <li class="rounded-2xl bg-white/5 ring-1 ring-white/10">
            <<?= $tag ?><?= $hasFilm ? ' href="/seance.php?id=' . (int) $seance['id'] . '"' : '' ?> class="flex items-center gap-3 p-3 no-underline text-inherit">
                <?php if (!empty($seance['movie_poster'])): ?>
                    <img src="<?= Security::e($seance['movie_poster']) ?>" alt="" loading="lazy"
                         class="h-20 w-14 shrink-0 rounded-xl object-cover bg-slate-800">
                <?php else: ?>
                    <div class="h-20 w-14 shrink-0 rounded-xl bg-slate-800"></div>
                <?php endif; ?>

                <div class="min-w-0 flex-1">
                    <p class="text-xs text-slate-400"><?= Security::e(FormatUtils::frenchDate($seance['date'])) ?></p>
                    <p class="font-medium leading-tight">
                        <?= $seance['movie_title'] !== null
                            ? Security::e($seance['movie_title'])
                            : '<span class="text-slate-500">aucun film</span>' ?>
                    </p>
                    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="rounded-full bg-white/10 px-2 py-0.5">
                            <?= $statusLabels[$seance['status']] ?? Security::e($seance['status']) ?>
                        </span>
                        <span class="rounded-full bg-white/10 px-2 py-0.5">
                            choix <?= $seance['chooser_side'] === 'kid' ? 'des filles' : 'des parents' ?>
                        </span>
                        <?php if ((int) $seance['derogation'] === 1): ?>
                            <span class="rounded-full bg-amber-500/25 px-2 py-0.5 text-amber-100">dérogation</span>
                        <?php endif; ?>
                        <?php if ((int) $seance['veto_count'] > 0): ?>
                            <span class="rounded-full bg-rose-500/25 px-2 py-0.5 text-rose-100">
                                <?= (int) $seance['veto_count'] ?> veto<?= $seance['veto_count'] > 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($seance['avg_score'] !== null): ?>
                            <span class="rounded-full bg-amber-400/25 px-2 py-0.5 text-amber-100">
                                <?= Security::e((string) $seance['avg_score']) ?> sur 5
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($seance['derogation_note'])): ?>
                        <p class="mt-1 text-xs italic text-slate-400">« <?= Security::e($seance['derogation_note']) ?> »</p>
                    <?php endif; ?>
                    <?php if (!empty($seance['proposer_name'])): ?>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            proposé par <?= Security::e($seance['proposer_name']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </<?= $tag ?>>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
