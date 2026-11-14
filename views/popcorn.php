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

<style>
    /* Loader des etapes : style local, sans passer par la compilation de Tailwind. */
    @keyframes pc-spin { to { transform: rotate(360deg); } }
    .pc-spin { display: inline-block; animation: pc-spin .9s linear infinite; }
    /* Le petit robot qui mange du pop-corn : les grains partent du seau, arrivent a la bouche qui croque. */
    .pc-bot { overflow: visible; }
    .pc-bot * { transform-box: fill-box; }
    @keyframes pc-fly {
        0%   { transform: translate(0, 0) scale(.6); opacity: 0; }
        12%  { opacity: 1; }
        50%  { transform: translate(-19px, -11px) rotate(180deg) scale(1); }
        85%  { transform: translate(-37px, 13px) rotate(300deg) scale(1); opacity: 1; }
        100% { transform: translate(-40px, 16px) rotate(360deg) scale(.2); opacity: 0; }
    }
    @keyframes pc-chomp { 0%, 100% { transform: scaleY(.3); } 50% { transform: scaleY(1.5); } }
    @keyframes pc-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-1.5px); } }
    @keyframes pc-blink { 0%, 90%, 100% { transform: scaleY(1); } 95% { transform: scaleY(.1); } }
    @keyframes pc-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
    .pc-k { animation: pc-fly 1.35s ease-in infinite; }
    .pc-k2 { animation-delay: .45s; }
    .pc-k3 { animation-delay: .9s; }
    .pc-mouth { transform-origin: center; animation: pc-chomp .45s ease-in-out infinite; }
    .pc-head { animation: pc-bob 1.35s ease-in-out infinite; }
    .pc-eye { transform-origin: center; animation: pc-blink 3s infinite; }
    .pc-antenna { animation: pc-pulse 1s ease-in-out infinite; }
    @media (prefers-reduced-motion: reduce) {
        .pc-spin, .pc-k, .pc-mouth, .pc-head, .pc-eye, .pc-antenna { animation: none; }
        .pc-k { opacity: 0; }
    }
</style>
<section x-data="popcornLoader({ source: '<?= Security::e($public) ?>', progress: <?= Security::e(json_encode($progress, JSON_UNESCAPED_UNICODE)) ?> })"
         class="mb-6 flex flex-wrap items-center gap-3">
    <?php /* Filtre de la page ET orientation de la prochaine demande : on demande ce qu'on regarde. */ ?>
    <nav class="flex w-full flex-wrap gap-1.5 text-sm" aria-label="Pour qui">
        <?php foreach (['all' => 'Tout le monde', 'adult' => 'Parents', 'kid' => 'Filles'] as $value => $label): ?>
            <a href="/popcorn.php<?= $value === 'all' ? '' : '?public=' . $value ?>"
               <?= $public === $value ? 'aria-current="page"' : '' ?>
               class="rounded-xl px-3 py-1.5 ring-1 ring-white/10 no-underline <?= $public === $value ? 'bg-white/15 font-medium text-slate-100' : 'bg-white/5 text-slate-300' ?>">
                <?= Security::e($label) ?> <span class="text-xs text-slate-400">(<?= (int) $publicCounts[$value] ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($public !== 'all'): ?>
        <p class="w-full text-xs text-slate-400">
            Idées nées de la liste des <?= $public === 'adult' ? 'parents' : 'filles' ?> (films vus et à voir) ;
            la prochaine demande s'appuie sur cette liste.
        </p>
    <?php endif; ?>
    <button type="button" @click="demander()" :disabled="active || <?= $configured && $callsLeft > 0 ? 'false' : 'true' ?>"
            class="rounded-xl bg-violet-500 px-4 py-2 font-medium disabled:opacity-50">
        <span x-show="!active">Demander de nouvelles idées</span>
        <span x-show="active" x-cloak>Madame Pop-Corn travaille…</span>
    </button>
    <?php if ($configured): ?>
        <span class="text-xs text-slate-400">
            Encore <?= (int) $callsLeft ?> demande<?= $callsLeft > 1 ? 's' : '' ?> aujourd'hui.
            <?= $includeMemos ? 'Vos mémos sont lus (faible poids).' : 'Vos mémos ne sont pas lus.' ?>
            <a href="/settings.php#pop-corn" class="underline">Régler</a>
        </span>
    <?php endif; ?>
    <?php /* Etapes reelles de la demande, ecrites par le serveur a chaque etape et visibles de tous les telephones. */ ?>
    <div x-show="active" x-cloak class="w-full rounded-2xl bg-white/5 p-4 ring-1 ring-white/10" role="status" aria-live="polite">
        <div class="flex items-center gap-3">
            <?php /* Petit robot qui grignote du pop-corn (decoratif : aria-hidden, et fige si les animations sont reduites). */ ?>
            <svg class="pc-bot shrink-0" viewBox="0 0 96 58" width="88" height="53" aria-hidden="true" focusable="false">
                <g class="pc-head">
                    <line x1="24" y1="7" x2="24" y2="15" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <circle class="pc-antenna" cx="24" cy="5" r="3" fill="#a78bfa"/>
                    <rect x="8" y="15" width="32" height="28" rx="8" fill="#cbd5e1"/>
                    <rect x="12" y="19" width="24" height="12" rx="5" fill="#1e293b"/>
                    <circle class="pc-eye" cx="20" cy="25" r="2.6" fill="#67e8f9"/>
                    <circle class="pc-eye" cx="28" cy="25" r="2.6" fill="#67e8f9"/>
                    <rect class="pc-mouth" x="19" y="35" width="10" height="6" rx="3" fill="#475569"/>
                    <rect x="3" y="24" width="5" height="9" rx="2" fill="#94a3b8"/>
                    <rect x="40" y="24" width="5" height="9" rx="2" fill="#94a3b8"/>
                </g>
                <path d="M60 28 L88 28 L84 56 L64 56 Z" fill="#f8fafc"/>
                <path d="M66 28 L72 28 L71 56 L66.5 56 Z M77 28 L83 28 L82 56 L77.5 56 Z" fill="#ef4444"/>
                <circle cx="66" cy="26" r="4.2" fill="#fef3c7"/>
                <circle cx="73" cy="23" r="4.6" fill="#fde68a"/>
                <circle cx="80" cy="25.5" r="4.2" fill="#fef3c7"/>
                <circle cx="85" cy="27" r="3.4" fill="#fde68a"/>
                <circle class="pc-k" cx="72" cy="22" r="3.4" fill="#fef3c7"/>
                <circle class="pc-k pc-k2" cx="72" cy="22" r="3.4" fill="#fde68a"/>
                <circle class="pc-k pc-k3" cx="72" cy="22" r="3.4" fill="#fef3c7"/>
            </svg>
            <p class="text-sm font-medium">
                🍿 Madame Pop-Corn est au travail<span x-show="progress && progress.by" x-cloak> (demande de <span x-text="progress ? progress.by : ''"></span>)</span>
                <span class="ml-1 text-xs font-normal text-slate-400" x-text="elapsedText"></span>
            </p>
        </div>
        <ol class="mt-3 space-y-2 text-sm">
            <template x-for="(step, i) in steps" :key="i">
                <li class="flex items-start gap-2">
                    <span class="w-4 shrink-0 text-center" :class="step.status === 'done' ? 'text-emerald-200' : 'text-slate-400'" aria-hidden="true">
                        <span x-show="step.status === 'done'">✓</span>
                        <span x-show="step.status === 'active'" class="pc-spin">◌</span>
                        <span x-show="step.status === 'pending'">·</span>
                    </span>
                    <span :class="step.status === 'pending' ? 'text-slate-500' : (step.status === 'active' ? 'text-slate-100' : 'text-slate-300')">
                        <span x-text="step.label"></span>
                        <span x-show="step.detail" class="block text-xs text-slate-400" x-text="step.detail"></span>
                    </span>
                </li>
            </template>
        </ol>
    </div>
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
            <?= Security::e($whenText) ?><?= !empty($lastAttempt['by']) ? ' par ' . Security::e((string) $lastAttempt['by']) : '' ?><?= ['adult' => ', d\'après la liste des parents', 'kid' => ', d\'après la liste des filles'][$lastAttempt['source'] ?? 'all'] ?? '' ?>.
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

<?php /* Les compteurs suivent les cartes : une carte traitee disparait sans recharger la page,
          son compteur doit baisser avec elle (idea-gone est envoye par la carte). */ ?>
<section x-data="{ n: <?= count($fresh) ?> }" @idea-gone.window="if ($event.detail === 'new') n--">
    <h2 class="mb-2 text-sm font-medium text-slate-300">Nouvelles idées (<span x-text="n"><?= count($fresh) ?></span>)</h2>
    <p x-show="n <= 0" <?= $fresh === [] ? '' : 'x-cloak' ?>
       class="mb-6 rounded-2xl bg-white/5 p-6 text-center text-sm text-slate-400">Pas d'idée en attente.</p>
    <div class="mb-6 space-y-3">
        <?php foreach ($fresh as $s): ?><?= $card($s, 'new') ?><?php endforeach; ?>
    </div>
</section>

<?php if ($kept !== []): ?>
    <details class="mb-6" x-data="{ n: <?= count($kept) ?> }" @idea-gone.window="if ($event.detail === 'kept') n--"
             x-show="n > 0">
        <summary class="cursor-pointer text-sm font-medium text-slate-300">Gardées au chaud (<span x-text="n"><?= count($kept) ?></span>)</summary>
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
// Etapes de la demande a Madame Pop-Corn. Le serveur ecrit son avancement a chaque etape (relecture
// des films, demande au modele, verification chez TMDb, second essai) : la page l'interroge toutes
// les 0,8 s. Un autre telephone qui ouvre la page pendant ce temps voit les memes etapes, et la page
// se recharge toute seule quand le travail est fini.
function popcornLoader(init) {
    return {
        source: init.source,
        progress: init.progress,
        follower: !!init.progress,
        busy: false,
        error: '',
        timer: null,
        init() { if (this.follower) { this.poll(); } },
        get active() { return this.busy || this.follower; },
        get elapsedText() { return this.progress && this.progress.elapsed ? this.progress.elapsed + ' s' : ''; },
        get steps() {
            const p = this.progress || {};
            const pass = p.pass || 1;
            const list = [
                { key: 'taste', label: 'Je relis vos films', detail: p.taste || '' },
                { key: 'ask', label: 'Gemini cherche des idées', detail: '' },
                { key: 'verify', label: 'Je vérifie chaque idée chez TMDb', detail: '' },
            ];
            if (pass > 1) {
                list.push({ key: 'ask2', label: 'Second essai, avec un modèle plus strict', detail: '' });
                list.push({ key: 'verify2', label: 'Je vérifie les nouvelles idées', detail: '' });
            }
            const current = p.step === 'taste' ? 'taste' : (pass > 1 ? p.step + '2' : p.step);
            const at = p.state === 'done' ? list.length : Math.max(0, list.findIndex(s => s.key === current));
            return list.map((s, i) => {
                const status = i < at ? 'done' : (i === at ? 'active' : 'pending');
                let detail = s.detail;
                if (status === 'active' && (s.key === 'verify' || s.key === 'verify2') && p.total) {
                    detail = p.checked + ' sur ' + p.total + (p.detail ? ' : ' + p.detail : '');
                }
                return { label: s.label, status, detail };
            });
        },
        async demander() {
            this.busy = true; this.error = '';
            this.progress = { state: 'running', step: 'taste', pass: 1, elapsed: 0 };
            this.poll();
            try {
                await popcornCall('/api/suggest.php', { source: this.source });
                location.reload();
            } catch (e) {
                this.error = e.message; this.busy = false; this.stop();
            }
        },
        poll() {
            if (this.timer) { return; }
            const tick = async () => {
                try {
                    const r = await fetch('/api/suggest_status.php');
                    const j = await r.json();
                    if (j.progress) { this.progress = j.progress; }
                    // Un autre telephone a lance la demande : on recharge quand elle se termine.
                    if (this.follower && (!j.progress || j.progress.state !== 'running')) { location.reload(); return; }
                } catch (e) { /* reseau coupe un instant : on retente */ }
                this.timer = setTimeout(tick, 800);
            };
            this.timer = setTimeout(tick, 400);
        },
        stop() { clearTimeout(this.timer); this.timer = null; },
    };
}
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
    // Couper la video tout de suite : masquer la carte ne retire pas l'iframe,
    // et le son continuerait en arriere-plan. playing = false la retire du DOM.
    card.playing = false;
    card.busy = true; card.error = '';
    try {
        await popcornCall('/api/suggestion.php', pool ? { id, action, pool } : { id, action });
        card.gone = true;
        // Prevenir les compteurs de section. Envoye ici et non depuis un x-effect : dans un
        // effet, le compteur serait suivi et l'evenement se redeclencherait en cascade.
        window.dispatchEvent(new CustomEvent('idea-gone', { detail: card.section }));
        // Garder, ecarter ou rouvrir deplace la carte d'une section a l'autre : on recharge,
        // pour que les trois listes et leurs compteurs disent la verite. Ajouter en liste ou
        // « deja vu » la fait sortir de la page : la carte disparait, son compteur baisse.
        if (action === 'keep' || action === 'reject' || action === 'restore') { location.reload(); }
    } catch (e) { card.error = e.message; }
    finally { card.busy = false; }
}
</script>
