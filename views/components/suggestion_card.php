<?php
use App\Utils\FormatUtils;
use App\Utils\Providers;
use App\Utils\Security;

/** @var array $suggestion */
/** @var string $startTime */
/** @var array $subscribedBrands */
/** @var list<string> $manageablePools */
/** @var string $mode new|kept|rejected */

$isSeries = $suggestion['kind'] === 'series';
$providersRaw = json_decode((string) ($suggestion['providers'] ?? '[]'), true);
$brands = Providers::brands(is_array($providersRaw) ? $providersRaw : []);
$display = Providers::displayBrands($brands, $subscribedBrands);
$endTime = $isSeries ? null : FormatUtils::endTime($startTime, $suggestion['runtime'] === null ? null : (int) $suggestion['runtime']);

$audience = ['kid' => 'plutôt pour les filles', 'adult' => 'plutôt pour les parents'][$suggestion['audience']] ?? null;
$bet = ['safe' => 'valeur sûre', 'discovery' => 'découverte'][$suggestion['bet_type'] ?? ''] ?? null;
// « Deja vu » : la liste ne sert qu'a dire qui l'avait choisi. Les filles n'ont que la leur.
$seenPool = ($suggestion['audience'] === 'kid' && in_array('kid', $manageablePools, true))
    ? 'kid'
    : ($manageablePools[0] ?? 'kid');
$poolLabels = ['adult' => 'Liste des parents', 'kid' => 'Liste des filles'];

// Id YouTube de la bande-annonce, pour le lecteur integre (rien n'est charge avant le clic).
$trailerId = null;
if (!empty($suggestion['trailer_url'])) {
    parse_str((string) parse_url((string) $suggestion['trailer_url'], PHP_URL_QUERY), $trailerQuery);
    $candidate = (string) ($trailerQuery['v'] ?? '');
    $trailerId = preg_match('/^[A-Za-z0-9_-]{6,20}$/', $candidate) === 1 ? $candidate : null;
}

$overview = trim((string) ($suggestion['overview'] ?? ''));
if (mb_strlen($overview) > 200) {
    $overview = mb_substr($overview, 0, 200) . '…';
}
?>
<article class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"
         x-data="{ busy: false, gone: false, error: '', playing: false }" x-show="!gone">
    <div class="flex gap-3">
        <?php if (!empty($suggestion['poster_url'])): ?>
            <img src="<?= Security::e($suggestion['poster_url']) ?>" alt="" loading="lazy"
                 class="h-32 w-[86px] shrink-0 rounded-xl object-cover bg-slate-800">
        <?php else: ?>
            <div class="h-32 w-[86px] shrink-0 rounded-xl bg-slate-800"></div>
        <?php endif; ?>

        <div class="min-w-0 flex-1">
            <h3 class="font-medium leading-tight"><?= Security::e($suggestion['title']) ?></h3>
            <p class="text-xs text-slate-400">
                <?php if ($isSeries): ?>
                    Série<?= $suggestion['season_count'] !== null ? ' · ' . (int) $suggestion['season_count'] . ' saison' . ((int) $suggestion['season_count'] > 1 ? 's' : '') : '' ?>
                    <?= $suggestion['year'] !== null ? ' · ' . (int) $suggestion['year'] : '' ?>
                <?php else: ?>
                    <?= $suggestion['year'] !== null ? (int) $suggestion['year'] . ' · ' : '' ?>
                    <?= Security::e(FormatUtils::humanRuntime($suggestion['runtime'] === null ? null : (int) $suggestion['runtime'])) ?>
                    <?= $endTime !== null ? ' · fin vers ' . Security::e($endTime) : '' ?>
                <?php endif; ?>
            </p>

            <?php if (!empty($suggestion['why'])): ?>
                <p class="mt-1.5 rounded-lg bg-amber-400/10 px-2 py-1 text-xs text-amber-100">
                    🍿 <?= Security::e($suggestion['why']) ?>
                </p>
            <?php endif; ?>

            <?php if ($overview !== ''): ?>
                <p class="mt-1.5 text-xs text-slate-300"><?= Security::e($overview) ?></p>
            <?php endif; ?>

            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                <?php if ($bet !== null): ?>
                    <span class="rounded-full bg-violet-500/25 px-2 py-0.5 text-violet-100"><?= Security::e($bet) ?></span>
                <?php endif; ?>
                <?php if ($audience !== null): ?>
                    <span class="rounded-full bg-sky-500/20 px-2 py-0.5 text-sky-100"><?= Security::e($audience) ?></span>
                <?php endif; ?>
                <?php if (!empty($suggestion['certification'])): ?>
                    <span class="rounded-full bg-white/10 px-2 py-0.5"><?= Security::e($suggestion['certification']) ?></span>
                <?php endif; ?>
                <?php if ($brands === []): ?>
                    <span class="text-slate-500">aucune plateforme connue</span>
                <?php elseif ($display['warning']): ?>
                    <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-100">hors abonnement</span>
                <?php else: ?>
                    <?php foreach ($display['shown'] as $brand): ?>
                        <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-emerald-100"><?= Security::e($brand['brand']) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($trailerId !== null): ?>
        <div x-show="playing" x-cloak class="mt-3">
            <template x-if="playing">
                <div class="aspect-video w-full overflow-hidden rounded-xl bg-black">
                    <iframe src="https://www.youtube-nocookie.com/embed/<?= Security::e($trailerId) ?>?rel=0"
                            title="Bande-annonce de <?= Security::e($suggestion['title']) ?>"
                            class="h-full w-full" loading="lazy" allowfullscreen
                            allow="encrypted-media; picture-in-picture; fullscreen"
                            referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            </template>
            <a href="<?= Security::e($suggestion['trailer_url']) ?>" target="_blank" rel="noopener noreferrer"
               class="mt-1 inline-block text-xs text-slate-400 underline">Le lecteur ne démarre pas ? Ouvrir sur YouTube</a>
        </div>
    <?php endif; ?>

    <p x-show="error" x-cloak class="mt-2 rounded-lg bg-rose-500/20 px-2 py-1 text-xs text-rose-100" x-text="error"></p>

    <div class="mt-3 flex flex-wrap gap-2 text-sm">
        <?php if ($trailerId !== null): ?>
            <button type="button" class="rounded-xl bg-white/15 px-3 py-1.5 font-medium"
                    @click="playing = !playing" :aria-expanded="playing">
                <span x-show="!playing">▶ Bande-annonce</span>
                <span x-show="playing" x-cloak>■ Fermer la bande-annonce</span>
            </button>
        <?php endif; ?>
        <?php if ($mode === 'rejected'): ?>
            <button type="button" class="rounded-xl bg-white/10 px-3 py-1.5 disabled:opacity-50" :disabled="busy"
                    @click="popcorn($data, 'restore', <?= (int) $suggestion['id'] ?>)">Rouvrir cette idée</button>
        <?php else: ?>
            <?php foreach ($manageablePools as $pool): ?>
                <button type="button" class="rounded-xl bg-violet-500 px-3 py-1.5 font-medium disabled:opacity-50" :disabled="busy"
                        @click="popcorn($data, 'transfer', <?= (int) $suggestion['id'] ?>, '<?= $pool ?>')">
                    → <?= Security::e($poolLabels[$pool]) ?>
                </button>
            <?php endforeach; ?>
            <?php if ($mode === 'new'): ?>
                <button type="button" class="rounded-xl bg-white/10 px-3 py-1.5 disabled:opacity-50" :disabled="busy"
                        @click="popcorn($data, 'keep', <?= (int) $suggestion['id'] ?>)">Garder au chaud</button>
            <?php endif; ?>
            <button type="button" class="ml-auto rounded-xl bg-white/10 px-3 py-1.5 disabled:opacity-50" :disabled="busy"
                    title="Déjà regardé : va tout en bas de l'historique, sans date"
                    @click="popcorn($data, 'seen', <?= (int) $suggestion['id'] ?>, '<?= $seenPool ?>')">Déjà vu</button>
            <button type="button" class="rounded-xl px-3 py-1.5 text-slate-400 hover:bg-white/10 disabled:opacity-50" :disabled="busy"
                    @click="popcorn($data, 'reject', <?= (int) $suggestion['id'] ?>)">Pas pour nous</button>
        <?php endif; ?>
    </div>
</article>
