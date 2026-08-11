<?php
use App\Utils\Security;
?>
<h1 class="text-2xl font-semibold mb-1">Ajouter un film</h1>
<p class="mb-6 text-sm text-slate-400">Note l'idée maintenant, tu te remercieras samedi soir.</p>

<?php if ($error !== null): ?>
    <p class="mb-4 rounded-xl bg-rose-500/20 px-4 py-2 text-sm text-rose-100"><?= Security::e($error) ?></p>
<?php endif; ?>

<form method="post" class="space-y-6"
      x-data="{
        query: '',
        results: [],
        selected: null,
        manual: <?= $tmdbConfigured ? 'false' : 'true' ?>,
        duplicate: null,
        searching: false,
        pool: <?= Security::e(json_encode($old['pool'], JSON_UNESCAPED_UNICODE)) ?>,
        title: <?= Security::e(json_encode($old['title'], JSON_UNESCAPED_UNICODE)) ?>,
        year: <?= Security::e(json_encode($old['year'], JSON_UNESCAPED_UNICODE)) ?>,
        runtime: <?= Security::e(json_encode($old['runtime'], JSON_UNESCAPED_UNICODE)) ?>,
        async search() {
            if (this.query.trim().length < 2) { this.results = []; return; }
            this.searching = true;
            try {
                const response = await fetch('/api/search.php?q=' + encodeURIComponent(this.query));
                const payload = await response.json();
                this.results = payload.results || [];
            } finally {
                this.searching = false;
            }
        },
        async choose(movie) {
            this.selected = movie;
            this.results = [];
            this.query = movie.title;
            await this.checkDuplicate(movie.tmdb_id, movie.title, movie.year);
        },
        async checkDuplicate(tmdbId, title, year) {
            const params = new URLSearchParams();
            if (tmdbId) params.set('tmdb_id', tmdbId);
            if (title) params.set('title', title);
            if (year) params.set('year', year);
            const response = await fetch('/api/duplicate.php?' + params.toString());
            this.duplicate = (await response.json()).duplicate;
        },
        reset() { this.selected = null; this.duplicate = null; this.query = ''; this.results = []; }
      }">
    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
    <input type="hidden" name="tmdb_id" :value="selected ? selected.tmdb_id : ''">

    <?php if ($tmdbConfigured): ?>
        <section x-show="!manual" class="space-y-2">
            <label class="block text-sm font-medium" for="query">Chercher le film</label>
            <input id="query" type="search" x-model="query" @input.debounce.400ms="search()"
                   placeholder="Le Voyage de Chihiro" autocomplete="off"
                   class="w-full rounded-xl bg-white/10 px-3 py-2">
            <p x-show="searching" class="text-xs text-slate-400">Recherche en cours...</p>

            <ul x-show="results.length" class="divide-y divide-white/5 overflow-hidden rounded-xl bg-white/5">
                <template x-for="movie in results" :key="movie.tmdb_id">
                    <li>
                        <button type="button" @click="choose(movie)"
                                class="flex w-full items-center gap-3 p-2 text-left hover:bg-white/10">
                            <template x-if="movie.poster_url">
                                <img :src="movie.poster_url" alt="" class="h-16 w-11 rounded object-cover">
                            </template>
                            <template x-if="!movie.poster_url">
                                <span class="h-16 w-11 rounded bg-slate-800"></span>
                            </template>
                            <span>
                                <span class="block text-sm" x-text="movie.title"></span>
                                <span class="block text-xs text-slate-400" x-text="movie.year || 'année inconnue'"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>

            <div x-show="selected" class="rounded-xl bg-emerald-500/15 px-3 py-2 text-sm">
                <span x-text="selected ? selected.title : ''"></span>
                <button type="button" @click="reset()" class="ml-2 underline text-xs">changer</button>
            </div>

            <button type="button" @click="manual = true; reset()" class="text-xs text-slate-400 underline">
                Le film n'est pas dans la liste, je le saisis à la main
            </button>
        </section>
    <?php endif; ?>

    <section x-show="manual" class="space-y-2">
        <label class="block text-sm font-medium" for="title">Titre</label>
        <input id="title" name="title" x-model="title"
               @blur="checkDuplicate(null, title, year)"
               class="w-full rounded-xl bg-white/10 px-3 py-2">
        <div class="flex gap-2">
            <input name="year" x-model="year" inputmode="numeric" placeholder="Année"
                   class="w-28 rounded-xl bg-white/10 px-3 py-2">
            <input name="runtime" x-model="runtime" inputmode="numeric" placeholder="Durée en minutes"
                   class="w-48 rounded-xl bg-white/10 px-3 py-2">
        </div>
        <?php if ($tmdbConfigured): ?>
            <button type="button" @click="manual = false" class="text-xs text-slate-400 underline">
                Revenir à la recherche
            </button>
        <?php endif; ?>
    </section>

    <template x-if="duplicate">
        <p class="rounded-xl bg-amber-500/20 px-3 py-2 text-sm text-amber-100">
            <span x-text="duplicate.title"></span>
            <template x-if="duplicate.status === 'watched'">
                <span>a déjà été vu<span x-show="duplicate.watched_on"> le <span x-text="duplicate.watched_on"></span></span>.</span>
            </template>
            <template x-if="duplicate.status !== 'watched'">
                <span>est déjà dans une liste.</span>
            </template>
            Tu peux quand même l'ajouter.
        </p>
    </template>

    <?php $poolLabels = ['adult' => 'Liste des parents', 'kid' => 'Liste des filles']; ?>
    <section class="space-y-2">
        <?php if (count($manageablePools) === 1): ?>
            <?php $seulePool = $manageablePools[0]; ?>
            <input type="hidden" name="pool" value="<?= $seulePool ?>">
            <p class="text-sm text-slate-400">
                Ce film ira dans la <strong><?= Security::e($poolLabels[$seulePool]) ?></strong>.
                Seuls les parents peuvent alimenter la leur.
            </p>
        <?php else: ?>
            <span class="block text-sm font-medium">Dans quelle liste ?</span>
            <div class="flex gap-2">
                <?php foreach ($manageablePools as $poolOption): ?>
                    <label class="flex-1 cursor-pointer rounded-xl px-3 py-2 text-sm ring-1 ring-white/10"
                           :class="pool === '<?= $poolOption ?>' ? 'bg-white/15' : 'bg-white/5'">
                        <input type="radio" name="pool" value="<?= $poolOption ?>" x-model="pool" class="sr-only">
                        <?= Security::e($poolLabels[$poolOption]) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section x-show="pool === 'adult'" class="space-y-2">
        <span class="block text-sm font-medium">Quel pari ?</span>
        <div class="flex gap-2">
            <label class="flex-1 cursor-pointer rounded-xl bg-white/5 px-3 py-2 text-sm ring-1 ring-white/10">
                <input type="radio" name="bet_type" value="safe" class="mr-1.5"
                       <?= $old['bet_type'] === 'safe' ? 'checked' : '' ?>> Valeur sûre
            </label>
            <label class="flex-1 cursor-pointer rounded-xl bg-white/5 px-3 py-2 text-sm ring-1 ring-white/10">
                <input type="radio" name="bet_type" value="discovery" class="mr-1.5"
                       <?= $old['bet_type'] === 'discovery' ? 'checked' : '' ?>> Découverte
            </label>
        </div>
        <p class="text-xs text-slate-400">Le tirage sort une valeur sûre et deux découvertes.</p>
    </section>

    <section class="space-y-2">
        <label class="block text-sm font-medium" for="memo">Pourquoi tu veux le leur montrer ?</label>
        <textarea id="memo" name="memo" rows="3" maxlength="500"
                  placeholder="Trois semaines plus tard, tu auras oublié. Écris-le."
                  class="w-full rounded-xl bg-white/10 px-3 py-2"><?= Security::e($old['memo']) ?></textarea>
    </section>

    <button class="w-full rounded-xl bg-violet-500 px-4 py-3 font-medium">Ajouter à la liste</button>
</form>
