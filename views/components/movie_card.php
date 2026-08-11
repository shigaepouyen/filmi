<?php
use App\Utils\Avatars;
use App\Utils\FormatUtils;
use App\Utils\Providers;
use App\Utils\Security;

$choosable = $choosable ?? false;
$subscribedBrands = $subscribedBrands ?? [];

$endTime = FormatUtils::endTime($startTime, $movie['runtime'] === null ? null : (int) $movie['runtime']);
$providersRaw = json_decode((string) ($movie['providers'] ?? '[]'), true);
$providersRaw = is_array($providersRaw) ? $providersRaw : [];
$providers = Providers::normalise($providersRaw);
$brands = Providers::brands($providersRaw);
$needsWarning = Providers::needsWarning($providers, $subscribedBrands);
$hasVoted = in_array((int) $movie['id'], array_map('intval', $myVotes), true);
$betLabels = ['safe' => 'valeur sûre', 'discovery' => 'découverte'];

$overview = trim((string) ($movie['overview'] ?? ''));
$truncatedOverview = $overview !== '' && mb_strlen($overview, 'UTF-8') > 160
    ? mb_substr($overview, 0, 160, 'UTF-8') . '…'
    : $overview;
?>
<article class="relative flex gap-3 rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"
         x-data="{ voted: <?= $hasVoted ? 'true' : 'false' ?>, count: <?= (int) $movie['vote_count'] ?>, busy: false }">
    <a href="/movie.php?id=<?= (int) $movie['id'] ?>" class="absolute inset-0 z-0 rounded-2xl"
       aria-label="Voir la fiche de <?= Security::e($movie['title']) ?>"></a>

    <?php if (!empty($movie['poster_url'])): ?>
        <img src="<?= Security::e($movie['poster_url']) ?>" alt="" loading="lazy"
             class="relative h-32 w-[86px] shrink-0 rounded-xl object-cover bg-slate-800">
    <?php else: ?>
        <div class="relative h-32 w-[86px] shrink-0 rounded-xl bg-slate-800"></div>
    <?php endif; ?>

    <div class="relative min-w-0 flex-1">
        <h3 class="font-medium leading-tight"><?= Security::e($movie['title']) ?></h3>
        <p class="text-xs text-slate-400">
            <?= $movie['year'] !== null ? (int) $movie['year'] . ' · ' : '' ?>
            <?= Security::e(FormatUtils::humanRuntime($movie['runtime'] === null ? null : (int) $movie['runtime'])) ?>
            <?= $endTime !== null ? ' · fin vers ' . Security::e($endTime) : '' ?>
        </p>

        <?php if ($truncatedOverview !== ''): ?>
            <p class="mt-1 text-xs text-slate-300"><?= Security::e($truncatedOverview) ?></p>
        <?php endif; ?>

        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
            <?php if (isset($betLabels[$movie['bet_type'] ?? ''])): ?>
                <span class="rounded-full bg-violet-500/25 px-2 py-0.5 text-violet-100">
                    <?= Security::e($betLabels[$movie['bet_type']]) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($movie['certification'])): ?>
                <span class="rounded-full bg-white/10 px-2 py-0.5"><?= Security::e($movie['certification']) ?></span>
            <?php endif; ?>
            <?php if ($brands === []): ?>
                <span class="text-slate-500">aucune plateforme connue</span>
            <?php else: ?>
                <?php foreach ($brands as $brand): ?>
                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-emerald-100">
                        <?= Security::e($brand['brand']) ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($needsWarning): ?>
                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-100">
                    hors abonnement
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($movie['memo'])): ?>
            <p class="mt-1.5 text-xs italic text-slate-300">« <?= Security::e($movie['memo']) ?> »</p>
        <?php endif; ?>

        <div class="relative z-10 mt-2 flex items-center gap-2">
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
                    title="<?= $movie['voter_names'] === '' ? Security::e("Personne n'a encore voté") : 'Ont voté : ' . Security::e($movie['voter_names']) ?>">
                <span aria-hidden="true">▲</span>
                <span x-text="count"><?= (int) $movie['vote_count'] ?></span>
            </button>
        </div>

        <?php if ($choosable): ?>
            <form method="post" class="relative z-10 mt-2">
                <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="action" value="choose">
                <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                <button class="w-full rounded-xl bg-violet-500 px-3 py-2 text-sm font-medium">
                    C'est celui-là ce soir
                </button>
            </form>
        <?php endif; ?>
    </div>
</article>
