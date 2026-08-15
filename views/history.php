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
                $seanceId = (int) $seance['id'];
                $maNote = $myRatings[$seanceId] ?? null;
                $notable = $hasFilm && $seance['status'] === 'done';
            ?>
            <li class="rounded-2xl bg-white/5 ring-1 ring-white/10"
                <?php if ($notable): ?>x-data="filmiRating(<?= $seanceId ?>, <?= $maNote === null ? 'null' : (int) $maNote ?>, <?= $seance['avg_score'] === null ? 'null' : (float) $seance['avg_score'] ?>)"<?php endif; ?>>
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
                        <?php if (!empty($seance['episodes_label'])): ?>
                            <span class="rounded-full bg-indigo-500/25 px-2 py-0.5 text-indigo-100">
                                <?= Security::e($seance['episodes_label']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ((int) $seance['derogation'] === 1): ?>
                            <span class="rounded-full bg-amber-500/25 px-2 py-0.5 text-amber-100">dérogation</span>
                        <?php endif; ?>
                        <?php if ((int) $seance['veto_count'] > 0): ?>
                            <span class="rounded-full bg-rose-500/25 px-2 py-0.5 text-rose-100">
                                <?= (int) $seance['veto_count'] ?> veto<?= $seance['veto_count'] > 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($notable): ?>
                            <span class="rounded-full bg-amber-400/25 px-2 py-0.5 text-amber-100"
                                  x-show="average !== null" x-cloak>
                                <span x-text="average"></span> sur 5
                            </span>
                        <?php elseif ($seance['avg_score'] !== null): ?>
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
            <?php if ($notable): ?>
                <div class="flex flex-wrap items-center gap-2 border-t border-white/10 px-3 py-2">
                    <span class="text-[11px] text-slate-400"
                          x-text="score === null ? 'ta note' : 'ta note : ' + score + '/5'"></span>
                    <div class="flex gap-1">
                        <?php for ($etoile = 1; $etoile <= 5; $etoile++): ?>
                            <button type="button" @click="pick(<?= $etoile ?>)" :disabled="busy"
                                    class="h-8 w-8 rounded-lg text-sm disabled:opacity-40"
                                    :class="score === <?= $etoile ?> ? 'bg-amber-400/30 ring-2 ring-amber-300' : 'bg-white/10'">
                                <?= $etoile ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <template x-if="pending !== null">
                        <span class="flex items-center gap-2 text-[11px] text-amber-200">
                            <span>Remplacer ta note par <span x-text="pending"></span>/5 ?</span>
                            <button type="button" @click="confirmReplace()" :disabled="busy"
                                    class="rounded-lg bg-amber-400/30 px-2 py-1">Oui</button>
                            <button type="button" @click="pending = null"
                                    class="rounded-lg bg-white/10 px-2 py-1">Non</button>
                        </span>
                    </template>
                    <span x-show="error" x-text="error" class="text-[11px] text-rose-300"></span>
                </div>
            <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <script>
    // Note sur 5 posee depuis l'historique. Premiere note = direct ; note deja
    // donnee = on demande confirmation avant de l'ecraser (le serveur applique
    // la meme regle, ce bloc n'est que le confort).
    window.filmiRating = function (seanceId, myScore, average) {
        return {
            score: myScore,
            average: average,
            pending: null,
            busy: false,
            error: '',
            pick(value) {
                this.error = '';
                if (this.busy || this.score === value) {
                    return;
                }
                if (this.score !== null) {
                    this.pending = value;
                    return;
                }
                this.send(value, false);
            },
            confirmReplace() {
                this.send(this.pending, true);
            },
            async send(value, replace) {
                this.busy = true;
                const body = new FormData();
                body.append('csrf', document.querySelector('meta[name="csrf-token"]').content);
                body.append('seance_id', seanceId);
                body.append('score', value);
                if (replace) {
                    body.append('intent', 'replace');
                }
                try {
                    const response = await fetch('/api/rate.php', { method: 'POST', body });
                    const data = await response.json();
                    if (response.ok) {
                        this.score = data.score;
                        this.average = data.average;
                        this.pending = null;
                    } else if (data.already_rated) {
                        // Une autre page a note entre-temps : on repasse par la confirmation.
                        this.score = data.score;
                        this.average = data.average;
                        this.pending = value;
                    } else {
                        this.error = data.error || 'La note n\'a pas été enregistrée.';
                        this.pending = null;
                    }
                } catch (e) {
                    this.error = 'La note n\'a pas été enregistrée.';
                } finally {
                    this.busy = false;
                }
            },
        };
    };
    </script>
<?php endif; ?>
