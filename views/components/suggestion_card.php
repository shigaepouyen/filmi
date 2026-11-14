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
$poolLabels = ['adult' => 'Liste des parents', 'kid' => 'Liste des filles'];

$overview = trim((string) ($suggestion['overview'] ?? ''));
if (mb_strlen($overview) > 200) {
    $overview = mb_substr($overview, 0, 200) . '…';
}
?>
<article class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"
         x-data="{ busy: false, gone: false, error: '' }" x-show="!gone">
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
                <?php if (!empty($suggestion['trailer_url'])): ?>
                    <a href="<?= Security::e($suggestion['trailer_url']) ?>" target="_blank" rel="noopener noreferrer"
                       class="rounded-full bg-white/10 px-2 py-0.5 text-slate-200 underline-offset-2 hover:underline">bande-annonce</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <p x-show="error" x-cloak class="mt-2 rounded-lg bg-rose-500/20 px-2 py-1 text-xs text-rose-100" x-text="error"></p>

    <div class="mt-3 flex flex-wrap gap-2 text-sm">
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
            <button type="button" class="ml-auto rounded-xl px-3 py-1.5 text-slate-400 hover:bg-white/10 disabled:opacity-50" :disabled="busy"
                    @click="popcorn($data, 'reject', <?= (int) $suggestion['id'] ?>)">Pas pour nous</button>
        <?php endif; ?>
    </div>
</article>
