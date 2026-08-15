<?php
use App\Utils\Avatars;
use App\Utils\FormatUtils;
use App\Utils\Providers;
use App\Utils\Security;

$choosable = $choosable ?? false;
$blockedByFilm = $blockedByFilm ?? null;
$subscribedBrands = $subscribedBrands ?? [];

$isSeries = ($movie['kind'] ?? 'film') === 'series';
$endTime = $isSeries ? null : FormatUtils::endTime($startTime, $movie['runtime'] === null ? null : (int) $movie['runtime']);
$providersRaw = json_decode((string) ($movie['providers'] ?? '[]'), true);
$providersRaw = is_array($providersRaw) ? $providersRaw : [];
$brands = Providers::brands($providersRaw);
// TMDb renvoie jusqu'a une vingtaine de plateformes, boutiques de location
// melees aux abonnements. Le perimetre d'abonnement de la famille est le
// filtre : une fois coche, il ne reste que les plateformes accessibles. La
// fiche du film garde la liste complete.
$display = Providers::displayBrands($brands, $subscribedBrands);
$hasVoted = in_array((int) $movie['id'], array_map('intval', $myVotes), true);
$betLabels = ['safe' => 'valeur sûre', 'discovery' => 'découverte'];

$overview = trim((string) ($movie['overview'] ?? ''));
$truncatedOverview = $overview !== '' && mb_strlen($overview, 'UTF-8') > 160
    ? mb_substr($overview, 0, 160, 'UTF-8') . '…'
    : $overview;
?>
<article class="relative flex gap-3 rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"
         x-data="{ voted: <?= $hasVoted ? 'true' : 'false' ?>, count: <?= (int) $movie['vote_count'] ?>, busy: false }">
    <?php /*
       Le lien couvre toute la carte et doit passer AU-DESSUS du contenu : un
       element positionne en z-index auto est peint apres un z-index 0 dans
       l'ordre du DOM, donc un lien en z-0 se retrouve enterre sous le titre et
       l'affiche, et taper sur la carte ne fait rien. Les commandes reellement
       interactives (vote, choix du soir) remontent en z-20 pour rester au-dessus
       du lien.
    */ ?>
    <a href="/movie.php?id=<?= (int) $movie['id'] ?>" class="absolute inset-0 z-10 rounded-2xl"
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
            <?php if ($isSeries): ?>
                Série · <?= (int) $movie['episodes_watched'] ?> épisode<?= (int) $movie['episodes_watched'] > 1 ? 's' : '' ?>
                sur <?= (int) $movie['episode_count'] ?>
            <?php else: ?>
                <?= $movie['year'] !== null ? (int) $movie['year'] . ' · ' : '' ?>
                <?= Security::e(FormatUtils::humanRuntime($movie['runtime'] === null ? null : (int) $movie['runtime'])) ?>
                <?= $endTime !== null ? ' · fin vers ' . Security::e($endTime) : '' ?>
            <?php endif; ?>
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
            <?php if ($blockedByFilm !== null): ?>
                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-100">
                    à voir après <?= Security::e($blockedByFilm['title']) ?>
                </span>
            <?php endif; ?>
            <?php if ($brands === []): ?>
                <span class="text-slate-500">aucune plateforme connue</span>
            <?php elseif ($display['warning']): ?>
                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-100">
                    hors abonnement
                </span>
            <?php else: ?>
                <?php foreach ($display['shown'] as $brand): ?>
                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-emerald-100">
                        <?= Security::e($brand['brand']) ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($movie['memo'])): ?>
            <p class="mt-1.5 text-xs italic text-slate-300">« <?= Security::e($movie['memo']) ?> »</p>
        <?php endif; ?>

        <div class="relative z-20 mt-2 flex items-center gap-2">
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

        <?php /* Une série ne se choisit pas comme un film au tirage : sa soirée
                  passe par la fiche (progression, réglage des épisodes), pas par
                  ce raccourci qui n'enregistrerait pas de plage d'épisodes. */ ?>
        <?php if ($choosable && !$isSeries && $blockedByFilm === null): ?>
            <form method="post" class="relative z-20 mt-2">
                <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="action" value="choose">
                <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                <button class="w-full rounded-xl bg-violet-500 px-3 py-2 text-sm font-medium">
                    C'est celui-là ce soir
                </button>
            </form>
        <?php elseif ($choosable && $isSeries): ?>
            <a href="/movie.php?id=<?= (int) $movie['id'] ?>"
               class="relative z-20 mt-2 block w-full rounded-xl bg-white/10 px-3 py-2 text-center text-sm font-medium">
                Voir la fiche pour lancer la soirée
            </a>
        <?php endif; ?>
    </div>
</article>
