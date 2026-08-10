<?php
use App\Utils\FormatUtils;
use App\Utils\Security;

$isKidWeek = $seance['chooser_side'] === 'kid';
$isSkipped = $seance['status'] === 'skipped';
$isDone = $seance['status'] === 'done';
?>
<section class="rounded-3xl bg-white/5 p-5 ring-1 ring-white/10">
    <p class="text-xs uppercase tracking-wide text-slate-400">Prochaine séance</p>
    <h1 class="mt-1 text-2xl font-semibold"><?= Security::e(FormatUtils::frenchDate($seance['date'])) ?></h1>

    <?php if ($isSkipped): ?>
        <p class="mt-3 rounded-xl bg-slate-700/50 px-3 py-2 text-sm">
            Pas de ciné ce samedi. Le tour des <?= $isKidWeek ? 'filles' : 'parents' ?> est reporté à la semaine prochaine.
        </p>
        <form method="post" class="mt-3">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
            <button name="action" value="unskip" class="text-sm underline text-slate-300">
                Finalement si, on regarde quelque chose
            </button>
        </form>
    <?php elseif ($isDone): ?>
        <p class="mt-3 rounded-xl bg-emerald-500/20 px-3 py-2 text-sm text-emerald-100">
            Le film est choisi. <a href="/seance.php" class="underline">Voir la séance</a>
        </p>
    <?php else: ?>
        <p class="mt-2 text-lg">
            C'est au tour des <strong><?= $isKidWeek ? 'filles' : 'parents' ?></strong>
            <?php if ((int) $seance['derogation'] === 1): ?>
                <span class="ml-1 rounded-full bg-amber-500/25 px-2 py-0.5 text-xs text-amber-100">dérogation</span>
            <?php endif; ?>
        </p>
        <?php if (!empty($seance['derogation_note'])): ?>
            <p class="text-xs italic text-slate-400">« <?= Security::e($seance['derogation_note']) ?> »</p>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-2">
            <?php if ($isKidWeek): ?>
                <a href="/pool.php?pool=kid" class="rounded-xl bg-violet-500 px-4 py-2.5 font-medium">
                    Choisir dans la liste des filles
                </a>
            <?php else: ?>
                <a href="/draw.php" class="rounded-xl bg-violet-500 px-4 py-2.5 font-medium">
                    Tirer trois films
                </a>
            <?php endif; ?>
        </div>

        <form method="post" class="mt-4 flex flex-wrap items-center gap-2 border-t border-white/10 pt-4"
              x-data="{ note: '' }">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
            <input name="note" x-model="note" maxlength="120" placeholder="Motif de la dérogation, facultatif"
                   class="min-w-0 flex-1 rounded-xl bg-white/10 px-3 py-2 text-sm">
            <button name="action" value="flip" class="rounded-xl bg-white/10 px-3 py-2 text-sm">
                Inverser le tour
            </button>
            <button name="action" value="skip" class="rounded-xl bg-white/10 px-3 py-2 text-sm text-slate-300">
                Pas de ciné ce samedi
            </button>
        </form>
    <?php endif; ?>
</section>

<?php if ($lowPool): ?>
    <section class="mt-4 rounded-2xl bg-amber-500/20 p-4 text-sm text-amber-100">
        <p class="font-medium">La liste des parents s'épuise.</p>
        <p class="mt-1">
            Il reste <?= (int) $adultTotal ?> film<?= $adultTotal > 1 ? 's' : '' ?>
            (<?= (int) $counts['safe'] ?> valeur<?= $counts['safe'] > 1 ? 's' : '' ?> sûre<?= $counts['safe'] > 1 ? 's' : '' ?>,
            <?= (int) $counts['discovery'] ?> découverte<?= $counts['discovery'] > 1 ? 's' : '' ?>),
            sous le seuil de <?= (int) $threshold ?>.
            <a href="/add.php" class="underline">Ajouter une idée maintenant</a>, pas samedi soir.
        </p>
    </section>
<?php endif; ?>

<section class="mt-4 grid grid-cols-2 gap-3 text-sm">
    <a href="/pool.php?pool=adult" class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
        <span class="block text-2xl font-semibold"><?= (int) $adultTotal ?></span>
        <span class="text-slate-400">dans la liste des parents</span>
    </a>
    <a href="/pool.php?pool=kid" class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
        <span class="block text-2xl font-semibold"><?= (int) $kidTotal ?></span>
        <span class="text-slate-400">dans la liste des filles</span>
    </a>
</section>
