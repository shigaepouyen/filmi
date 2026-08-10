<?php
use App\Utils\Avatars;
use App\Utils\FormatUtils;
use App\Utils\Security;

$endTime = FormatUtils::endTime($startTime, $movie['runtime'] === null ? null : (int) $movie['runtime']);
$providers = json_decode((string) ($movie['providers'] ?? '[]'), true);
$providers = is_array($providers) ? $providers : [];
$hasVoted = in_array((int) $movie['id'], array_map('intval', $myVotes), true);
$betLabels = ['safe' => 'valeur sûre', 'discovery' => 'découverte'];
?>
<article class="flex gap-3 rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"
         x-data="{ voted: <?= $hasVoted ? 'true' : 'false' ?>, count: <?= (int) $movie['vote_count'] ?>, busy: false }">
    <?php if (!empty($movie['poster_url'])): ?>
        <img src="<?= Security::e($movie['poster_url']) ?>" alt="" loading="lazy"
             class="h-32 w-[86px] shrink-0 rounded-xl object-cover bg-slate-800">
    <?php else: ?>
        <div class="h-32 w-[86px] shrink-0 rounded-xl bg-slate-800"></div>
    <?php endif; ?>

    <div class="min-w-0 flex-1">
        <h3 class="font-medium leading-tight"><?= Security::e($movie['title']) ?></h3>
        <p class="text-xs text-slate-400">
            <?= $movie['year'] !== null ? (int) $movie['year'] . ' · ' : '' ?>
            <?= Security::e(FormatUtils::humanRuntime($movie['runtime'] === null ? null : (int) $movie['runtime'])) ?>
            <?= $endTime !== null ? ' · fin vers ' . Security::e($endTime) : '' ?>
        </p>

        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
            <?php if (isset($betLabels[$movie['bet_type'] ?? ''])): ?>
                <span class="rounded-full bg-violet-500/25 px-2 py-0.5 text-violet-100">
                    <?= Security::e($betLabels[$movie['bet_type']]) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($movie['certification'])): ?>
                <span class="rounded-full bg-white/10 px-2 py-0.5"><?= Security::e($movie['certification']) ?></span>
            <?php endif; ?>
            <?php if ($providers === []): ?>
                <span class="text-slate-500">aucune plateforme connue</span>
            <?php else: ?>
                <?php foreach ($providers as $name): ?>
                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-emerald-100">
                        <?= Security::e((string) $name) ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($movie['memo'])): ?>
            <p class="mt-1.5 text-xs italic text-slate-300">« <?= Security::e($movie['memo']) ?> »</p>
        <?php endif; ?>

        <div class="mt-2 flex items-center gap-2">
            <span title="Proposé par <?= Security::e($movie['proposer_name']) ?>">
                <?= Avatars::render($movie['proposer_avatar'], $movie['proposer_color'], 22) ?>
            </span>
            <span class="text-xs text-slate-400"><?= Security::e($movie['proposer_name']) ?></span>

            <button type="button" class="ml-auto flex items-center gap-1 rounded-full px-2.5 py-1 text-sm ring-1 transition"
                    :class="voted ? 'bg-amber-400/25 text-amber-100 ring-amber-300/40' : 'bg-white/5 text-slate-300 ring-white/10'"
                    :disabled="busy"
                    @click="busy = true;
                            filmiPost('/api/vote.php', { movie_id: <?= (int) $movie['id'] ?> })
                                .then(r => { voted = r.voted; count = r.count; })
                                .finally(() => busy = false)"
                    title="<?= $movie['voter_names'] === '' ? Security::e('Personne n a encore voté') : 'Ont voté : ' . Security::e($movie['voter_names']) ?>">
                <span aria-hidden="true">▲</span>
                <span x-text="count"><?= (int) $movie['vote_count'] ?></span>
            </button>
        </div>
    </div>
</article>
