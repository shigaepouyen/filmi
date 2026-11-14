<?php
use App\Utils\Security;
use App\Utils\ViewRenderer;

$card = static fn (array $s, string $mode): string => ViewRenderer::component('suggestion_card', [
    'suggestion' => $s,
    'mode' => $mode,
    'startTime' => $startTime,
    'subscribedBrands' => $subscribedBrands,
    'manageablePools' => $manageablePools,
]);
?>
<div class="mb-5">
    <h1 class="text-2xl font-semibold">Madame Pop-Corn <span aria-hidden="true">🍿</span></h1>
    <p class="mt-1 text-sm text-slate-400">
        Elle lit ce que vous avez vu et noté, puis vos deux listes, et propose ce qui pourrait plaire à toute la famille.
        Ses idées restent ici, à part : rien n'entre dans vos listes ni dans le tirage sans que vous le décidiez.
    </p>
</div>

<?php if (!$configured): ?>
    <div class="rounded-2xl bg-amber-500/15 p-4 text-sm text-amber-100">
        Madame Pop-Corn n'est pas encore réveillée : il lui faut une clé Gemini gratuite
        (<code>./scripts/set_gemini_key.sh</code>) en plus de la clé TMDb.
    </div>
<?php endif; ?>

<section x-data="{ busy: false, error: '', async demander() {
        this.busy = true; this.error = '';
        try { await popcornCall('/api/suggest.php', {}); location.reload(); }
        catch (e) { this.error = e.message; this.busy = false; }
    } }"
         class="mb-6 flex flex-wrap items-center gap-3">
    <button type="button" @click="demander()" :disabled="busy || <?= $configured && $callsLeft > 0 ? 'false' : 'true' ?>"
            class="rounded-xl bg-violet-500 px-4 py-2 font-medium disabled:opacity-50">
        <span x-show="!busy">Demander de nouvelles idées</span>
        <span x-show="busy" x-cloak>Madame Pop-Corn réfléchit...</span>
    </button>
    <?php if ($configured): ?>
        <span class="text-xs text-slate-400">
            Encore <?= (int) $callsLeft ?> demande<?= $callsLeft > 1 ? 's' : '' ?> aujourd'hui.
            <?= $includeMemos ? 'Vos mémos sont lus (faible poids).' : 'Vos mémos ne sont pas lus.' ?>
            <a href="/settings.php#pop-corn" class="underline">Régler</a>
        </span>
    <?php endif; ?>
    <p x-show="error" x-cloak class="w-full rounded-xl bg-rose-500/20 px-3 py-2 text-sm text-rose-100" x-text="error"></p>
</section>

<?php if (!empty($lastAttempt)):
    $when = (string) ($lastAttempt['at'] ?? '');
    $whenText = preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', $when, $m)
        ? \App\Utils\FormatUtils::frenchDate($m[1]) . ' à ' . $m[2]
        : $when;
    $dropped = is_array($lastAttempt['dropped'] ?? null) ? $lastAttempt['dropped'] : [];
    ?>
    <section class="mb-6 rounded-2xl bg-white/5 p-3 text-xs text-slate-300 ring-1 ring-white/10">
        <p>
            <span class="font-medium text-slate-200">Dernière demande</span>,
            <?= Security::e($whenText) ?><?= !empty($lastAttempt['by']) ? ' par ' . Security::e((string) $lastAttempt['by']) : '' ?>.
            <?php if (!empty($lastAttempt['error'])): ?>
                <span class="text-rose-200">Échec : <?= Security::e((string) $lastAttempt['error']) ?></span>
            <?php else: ?>
                <?= (int) ($lastAttempt['added'] ?? 0) ?> idée<?= ($lastAttempt['added'] ?? 0) > 1 ? 's' : '' ?> gardée<?= ($lastAttempt['added'] ?? 0) > 1 ? 's' : '' ?>,
                <?= count($dropped) ?> écartée<?= count($dropped) > 1 ? 's' : '' ?><?= ($lastAttempt['passes'] ?? 1) > 1 ? ' (en deux passages)' : '' ?>.
            <?php endif; ?>
        </p>
        <?php if ($dropped !== []): ?>
            <details class="mt-1">
                <summary class="cursor-pointer text-slate-400">Pourquoi écartées ?</summary>
                <ul class="mt-1 list-disc pl-5 text-slate-400">
                    <?php foreach ($dropped as $d): ?>
                        <li><?= Security::e((string) ($d['title'] ?? '')) ?><?= !empty($d['year']) ? ' (' . (int) $d['year'] . ')' : '' ?> : <?= Security::e((string) ($d['reason'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>
<?php endif; ?>

<h2 class="mb-2 text-sm font-medium text-slate-300">Nouvelles idées (<?= count($fresh) ?>)</h2>
<?php if ($fresh === []): ?>
    <p class="mb-6 rounded-2xl bg-white/5 p-6 text-center text-sm text-slate-400">Pas d'idée en attente.</p>
<?php else: ?>
    <div class="mb-6 space-y-3">
        <?php foreach ($fresh as $s): ?><?= $card($s, 'new') ?><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($kept !== []): ?>
    <details class="mb-6">
        <summary class="cursor-pointer text-sm font-medium text-slate-300">Gardées au chaud (<?= count($kept) ?>)</summary>
        <div class="mt-2 space-y-3">
            <?php foreach ($kept as $s): ?><?= $card($s, 'kept') ?><?php endforeach; ?>
        </div>
    </details>
<?php endif; ?>

<?php if ($rejected !== []): ?>
    <details class="mb-6">
        <summary class="cursor-pointer text-sm text-slate-400">Écartées (<?= count($rejected) ?>)</summary>
        <p class="mt-1 text-xs text-slate-500">Madame Pop-Corn ne les reproposera pas.</p>
        <div class="mt-2 space-y-3">
            <?php foreach ($rejected as $s): ?><?= $card($s, 'rejected') ?><?php endforeach; ?>
        </div>
    </details>
<?php endif; ?>

<script>
// Appel JSON qui remonte le message d'erreur du serveur : filmiPost() ne garde
// qu'un message generique, or ici « quota atteint » ou « deja dans une liste »
// doivent s'afficher tels quels.
async function popcornCall(url, data) {
    const body = new FormData();
    body.append('csrf', document.querySelector('meta[name="csrf-token"]').content);
    Object.entries(data || {}).forEach(([k, v]) => body.append(k, v));
    let response;
    try { response = await fetch(url, { method: 'POST', body }); }
    catch (e) { throw new Error('Réseau indisponible.'); }
    let payload = {};
    try { payload = await response.json(); } catch (e) {}
    if (!response.ok) { throw new Error(payload.error || 'La requête a échoué.'); }
    return payload;
}
async function popcorn(card, action, id, pool) {
    card.busy = true; card.error = '';
    try {
        await popcornCall('/api/suggestion.php', pool ? { id, action, pool } : { id, action });
        card.gone = true;
        // Garder ou rouvrir deplace la carte d'une section a l'autre : on recharge.
        if (action === 'keep' || action === 'restore') { location.reload(); }
    } catch (e) { card.error = e.message; }
    finally { card.busy = false; }
}
</script>
