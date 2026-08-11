<?php
use App\Utils\Avatars;
use App\Utils\FormatUtils;
use App\Utils\Providers;
use App\Utils\Security;

$genres = json_decode((string) ($movie['genres'] ?? '[]'), true);
$genres = is_array($genres) ? $genres : [];

$providersRaw = json_decode((string) ($movie['providers'] ?? '[]'), true);
$providersRaw = is_array($providersRaw) ? $providersRaw : [];
$providers = Providers::normalise($providersRaw);
$brands = Providers::brands($providersRaw);
$needsWarning = Providers::needsWarning($providers, $subscribedBrands);

$endTime = FormatUtils::endTime($startTime, $movie['runtime'] === null ? null : (int) $movie['runtime']);
$betLabels = ['safe' => 'valeur sûre', 'discovery' => 'découverte'];
?>
<div class="mx-auto max-w-3xl">
    <a href="/pool.php?pool=<?= Security::e($movie['pool']) ?>" class="mb-4 inline-block text-sm text-slate-400 underline">
        &larr; Retour à la liste
    </a>

    <?php if ($error !== null): ?>
        <p class="mb-4 rounded-xl bg-rose-500/20 px-4 py-2 text-sm text-rose-100"><?= Security::e($error) ?></p>
    <?php endif; ?>

    <div class="flex flex-col gap-6 sm:flex-row">
        <?php if (!empty($movie['poster_url'])): ?>
            <img src="<?= Security::e($movie['poster_url']) ?>" alt=""
                 class="w-full max-w-[260px] rounded-2xl bg-slate-800 object-cover sm:w-60">
        <?php else: ?>
            <div class="aspect-[2/3] w-full max-w-[260px] rounded-2xl bg-slate-800 sm:w-60"></div>
        <?php endif; ?>

        <div class="min-w-0 flex-1 space-y-3">
            <h1 class="text-2xl font-semibold"><?= Security::e($movie['title']) ?></h1>
            <?php if (!empty($movie['original_title']) && $movie['original_title'] !== $movie['title']): ?>
                <p class="text-sm text-slate-400"><?= Security::e($movie['original_title']) ?></p>
            <?php endif; ?>

            <p class="text-sm text-slate-300">
                <?= $movie['year'] !== null ? (int) $movie['year'] . ' · ' : '' ?>
                <?= Security::e(FormatUtils::humanRuntime($movie['runtime'] === null ? null : (int) $movie['runtime'])) ?>
                <?= $endTime !== null ? ' · fin vers ' . Security::e($endTime) : '' ?>
            </p>

            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <?php if (isset($betLabels[$movie['bet_type'] ?? ''])): ?>
                    <span class="rounded-full bg-violet-500/25 px-2 py-0.5 text-violet-100">
                        <?= Security::e($betLabels[$movie['bet_type']]) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($movie['certification'])): ?>
                    <span class="rounded-full bg-white/10 px-2 py-0.5"><?= Security::e($movie['certification']) ?></span>
                <?php endif; ?>
                <?php if ($movie['tmdb_rating'] !== null): ?>
                    <span class="rounded-full bg-white/10 px-2 py-0.5">
                        ★ <?= Security::e(number_format((float) $movie['tmdb_rating'], 1)) ?>/10
                    </span>
                <?php endif; ?>
                <?php if ($movie['status'] === 'archived'): ?>
                    <span class="rounded-full bg-slate-500/30 px-2 py-0.5">archivé</span>
                <?php endif; ?>
            </div>

            <?php if ($genres !== []): ?>
                <p class="text-xs text-slate-400"><?= Security::e(implode(', ', array_map('strval', $genres))) ?></p>
            <?php endif; ?>

            <?php if (!empty($movie['director'])): ?>
                <p class="text-sm text-slate-300">Réalisé par <?= Security::e($movie['director']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <?php if ($brands === []): ?>
                    <span class="text-slate-500">aucune plateforme connue</span>
                <?php else: ?>
                    <?php foreach ($brands as $brand): ?>
                        <?php $logo = Providers::logoUrl($brand['logo']); ?>
                        <span class="flex items-center gap-1 rounded-full bg-emerald-500/20 px-2 py-0.5 text-emerald-100">
                            <?php if ($logo !== null): ?>
                                <img src="<?= Security::e($logo) ?>" alt="" class="h-4 w-4 rounded-full">
                            <?php endif; ?>
                            <?= Security::e($brand['brand']) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($needsWarning): ?>
                    <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-100">hors de votre abonnement</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($movie['trailer_url'])): ?>
                <a href="<?= Security::e($movie['trailer_url']) ?>" target="_blank" rel="noopener"
                   class="inline-block rounded-xl bg-white/10 px-3 py-1.5 text-sm">
                    Voir la bande-annonce
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($movie['overview'])): ?>
        <section class="mt-6">
            <h2 class="mb-1 text-sm font-medium text-slate-300">Synopsis</h2>
            <p class="text-sm text-slate-200"><?= Security::e($movie['overview']) ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($movie['memo'])): ?>
        <section class="mt-4">
            <h2 class="mb-1 text-sm font-medium text-slate-300">Pourquoi ce film</h2>
            <p class="text-sm italic text-slate-200">« <?= Security::e($movie['memo']) ?> »</p>
        </section>
    <?php endif; ?>

    <section class="mt-4 flex items-center gap-2 text-sm text-slate-300">
        <?= Avatars::render((string) ($proposer['avatar'] ?? Avatars::FALLBACK), (string) ($proposer['color'] ?? 'indigo'), 24) ?>
        Proposé par <?= Security::e((string) ($proposer['name'] ?? '?')) ?>
    </section>

    <?php if ($voters !== []): ?>
        <section class="mt-3">
            <h2 class="mb-1 text-sm font-medium text-slate-300">A voté</h2>
            <div class="flex flex-wrap items-center gap-3">
                <?php foreach ($voters as $voter): ?>
                    <span class="flex items-center gap-1 text-xs text-slate-300">
                        <?= Avatars::render($voter['avatar'], $voter['color'], 20) ?>
                        <?= Security::e($voter['name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($watchedOn !== null): ?>
        <section class="mt-3 text-sm text-slate-300">
            Vu le <?= Security::e(FormatUtils::frenchDate($watchedOn)) ?>
        </section>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <section class="mt-8 space-y-4 rounded-2xl bg-white/5 p-4">
            <h2 class="text-sm font-medium text-slate-300">Modifier la classification</h2>
            <form method="post" class="space-y-2" x-data="{ pool: <?= Security::e(json_encode($movie['pool'], JSON_UNESCAPED_UNICODE)) ?> }">
                <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int) $movie['id'] ?>">
                <input type="hidden" name="action" value="reclassify">
                <div class="flex gap-2">
                    <label class="flex-1 cursor-pointer rounded-xl px-3 py-2 text-sm ring-1 ring-white/10"
                           :class="pool === 'adult' ? 'bg-white/15' : 'bg-white/5'">
                        <input type="radio" name="pool" value="adult" x-model="pool" class="sr-only">
                        Liste des parents
                    </label>
                    <label class="flex-1 cursor-pointer rounded-xl px-3 py-2 text-sm ring-1 ring-white/10"
                           :class="pool === 'kid' ? 'bg-white/15' : 'bg-white/5'">
                        <input type="radio" name="pool" value="kid" x-model="pool" class="sr-only">
                        Liste des filles
                    </label>
                </div>
                <div x-show="pool === 'adult'" class="flex gap-2">
                    <label class="flex-1 cursor-pointer rounded-xl bg-white/5 px-3 py-2 text-sm ring-1 ring-white/10">
                        <input type="radio" name="bet_type" value="safe" class="mr-1.5"
                               <?= ($movie['bet_type'] ?? null) === 'safe' ? 'checked' : '' ?>>
                        Valeur sûre
                    </label>
                    <label class="flex-1 cursor-pointer rounded-xl bg-white/5 px-3 py-2 text-sm ring-1 ring-white/10">
                        <input type="radio" name="bet_type" value="discovery" class="mr-1.5"
                               <?= ($movie['bet_type'] ?? null) === 'discovery' ? 'checked' : '' ?>>
                        Découverte
                    </label>
                </div>
                <button class="rounded-xl bg-violet-500 px-4 py-2 text-sm font-medium">Enregistrer</button>
            </form>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int) $movie['id'] ?>">
                <?php if ($movie['status'] === 'archived'): ?>
                    <input type="hidden" name="action" value="unarchive">
                    <button class="rounded-xl bg-white/10 px-4 py-2 text-sm">Désarchiver</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="archive">
                    <button class="rounded-xl bg-rose-500/80 px-4 py-2 text-sm font-medium">
                        Archiver ce film
                    </button>
                <?php endif; ?>
            </form>
        </section>
    <?php endif; ?>
</div>
