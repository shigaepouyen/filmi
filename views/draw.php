<?php
use App\Utils\Security;
?>
<div x-data="{
        movies: [],
        shown: [],
        reset: false,
        error: null,
        missing: null,
        busy: false,
        async draw() {
            this.busy = true;
            this.error = null;
            try {
                const response = await fetch('/api/draw.php', {
                    method: 'POST',
                    body: (() => {
                        const body = new FormData();
                        body.append('csrf', document.querySelector('meta[name=\'csrf-token\']').content);
                        return body;
                    })()
                });
                const payload = await response.json();
                if (!response.ok) {
                    this.error = payload.error;
                    this.missing = payload.missing || null;
                    this.movies = [];
                    return;
                }
                this.movies = payload.movies;
                this.shown = payload.shown;
                this.reset = payload.reset;
            } finally {
                this.busy = false;
            }
        },
        endTime(runtime) {
            if (!runtime) return null;
            const [h, m] = '<?= Security::e($startTime) ?>'.split(':').map(Number);
            const total = (h * 60 + m + Number(runtime)) % 1440;
            return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
        }
     }"
     x-init="draw()">

    <h1 class="mb-1 text-2xl font-semibold">Trois films, à vous de choisir</h1>
    <p class="mb-5 text-sm text-slate-400">Une valeur sûre, deux découvertes. Tirage aléatoire.</p>

    <template x-if="error">
        <div class="rounded-2xl bg-rose-500/20 p-5 text-rose-100">
            <p x-text="error"></p>
            <a href="/add.php" class="mt-3 inline-block rounded-xl bg-violet-500 px-4 py-2 text-sm font-medium text-white">
                Ajouter un film maintenant
            </a>
        </div>
    </template>

    <template x-if="reset && movies.length">
        <p class="mb-3 rounded-xl bg-amber-500/20 px-3 py-2 text-xs text-amber-100">
            Tous les films avaient déjà été montrés ce soir, le tirage repart de la liste complète.
        </p>
    </template>

    <div class="grid gap-4 sm:grid-cols-3">
        <template x-for="movie in movies" :key="movie.id">
            <form method="post" class="flex flex-col overflow-hidden rounded-3xl bg-white/5 ring-1 ring-white/10">
                <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                <input type="hidden" name="shortlist" :value="movies.map(m => m.id).join(',')">
                <input type="hidden" name="movie_id" :value="movie.id">

                <template x-if="movie.poster_url">
                    <img :src="movie.poster_url" alt="" class="aspect-[2/3] w-full object-cover">
                </template>
                <template x-if="!movie.poster_url">
                    <div class="aspect-[2/3] w-full bg-slate-800"></div>
                </template>

                <div class="flex flex-1 flex-col gap-1.5 p-3">
                    <h2 class="font-medium leading-tight" x-text="movie.title"></h2>
                    <p class="text-xs text-slate-400">
                        <span x-text="movie.year || 'année inconnue'"></span>
                        <template x-if="movie.runtime">
                            <span> · <span x-text="movie.runtime"></span> min · fin vers <span x-text="endTime(movie.runtime)"></span></span>
                        </template>
                    </p>
                    <p class="text-[11px]">
                        <span class="rounded-full bg-violet-500/25 px-2 py-0.5"
                              x-text="movie.bet_type === 'safe' ? 'valeur sûre' : 'découverte'"></span>
                        <template x-if="movie.certification">
                            <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5" x-text="movie.certification"></span>
                        </template>
                    </p>
                    <p class="text-[11px] text-emerald-200"
                       x-text="(movie.provider_brands && movie.provider_brands.length ? movie.provider_brands.join(', ') : 'aucune plateforme connue')"></p>
                    <template x-if="movie.needs_warning">
                        <p class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] text-amber-100 w-fit">
                            hors abonnement
                        </p>
                    </template>
                    <template x-if="movie.overview">
                        <p class="line-clamp-3 text-xs text-slate-300" x-text="movie.overview"></p>
                    </template>
                    <template x-if="movie.memo">
                        <p class="text-xs italic text-slate-300">« <span x-text="movie.memo"></span> »</p>
                    </template>
                    <p class="text-[11px] text-slate-500">proposé par <span x-text="movie.proposer_name"></span></p>

                    <button class="mt-auto rounded-xl bg-violet-500 px-3 py-2 text-sm font-medium">
                        On prend celui-là
                    </button>
                </div>
            </form>
        </template>
    </div>

    <button type="button" @click="draw()" :disabled="busy"
            x-show="movies.length"
            class="mt-5 w-full rounded-xl bg-white/10 px-4 py-3 text-sm">
        Pas dans le mood, retire
    </button>
</div>
