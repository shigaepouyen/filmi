<?php
use App\Utils\Security;
?>
<div x-data="{
        movies: <?= Security::e(json_encode($currentMovies ?? [], JSON_UNESCAPED_UNICODE)) ?>,
        fiche: null,
        ouvrir(movie) { this.fiche = movie; document.body.style.overflow = 'hidden'; },
        fermer() { this.fiche = null; document.body.style.overflow = ''; },
        genres(json) {
            try { const l = JSON.parse(json || '[]'); return Array.isArray(l) ? l.join(', ') : ''; }
            catch (e) { return ''; }
        },
        youtubeId(url) {
            const m = (url || '').match(/[?&]v=([A-Za-z0-9_-]{6,})/);
            return m ? m[1] : null;
        },
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
     x-init="if (movies.length === 0) { draw() }">

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

                <button type="button" @click="ouvrir(movie)" class="block w-full text-left">
                    <template x-if="movie.poster_url">
                        <img :src="movie.poster_url" alt="" class="aspect-[2/3] w-full object-cover">
                    </template>
                    <template x-if="!movie.poster_url">
                        <div class="aspect-[2/3] w-full bg-slate-800"></div>
                    </template>
                </button>

                <div class="flex flex-1 flex-col gap-1.5 p-3">
                    <h2 class="font-medium leading-tight">
                        <button type="button" @click="ouvrir(movie)" class="text-left hover:underline"
                                x-text="movie.title"></button>
                    </h2>
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

                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <button type="button" @click="ouvrir(movie)"
                                class="rounded-xl bg-white/10 px-3 py-1.5">Voir la fiche</button>
                        <template x-if="movie.trailer_url">
                            <button type="button" @click="ouvrir(movie)"
                                    class="rounded-xl bg-white/10 px-3 py-1.5">Bande-annonce</button>
                        </template>
                    </div>

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

    <?php /* La fiche s'ouvre en popin plutot que sur une autre page : quitter le
              tirage relancerait un tirage au retour et les trois films changeraient
              sous les yeux de la famille. */ ?>
    <template x-if="fiche">
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4"
             @click.self="fermer()" @keydown.escape.window="fermer()">
            <div class="w-full max-w-2xl rounded-3xl bg-slate-900 p-4 ring-1 ring-white/15">
                <div class="flex items-start gap-3">
                    <h2 class="flex-1 text-xl font-semibold" x-text="fiche.title"></h2>
                    <button type="button" @click="fermer()"
                            class="rounded-xl bg-white/10 px-3 py-1.5 text-sm" aria-label="Fermer">Fermer</button>
                </div>

                <p class="mt-1 text-sm text-slate-400">
                    <span x-text="fiche.year || 'année inconnue'"></span>
                    <template x-if="fiche.runtime">
                        <span> · <span x-text="fiche.runtime"></span> min · fin vers
                            <span x-text="endTime(fiche.runtime)"></span></span>
                    </template>
                    <template x-if="fiche.director">
                        <span> · <span x-text="fiche.director"></span></span>
                    </template>
                </p>

                <p class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                    <span class="rounded-full bg-violet-500/25 px-2 py-0.5"
                          x-text="fiche.bet_type === 'safe' ? 'valeur sûre' : 'découverte'"></span>
                    <template x-if="fiche.certification">
                        <span class="rounded-full bg-white/10 px-2 py-0.5" x-text="fiche.certification"></span>
                    </template>
                    <template x-if="fiche.tmdb_rating">
                        <span class="rounded-full bg-white/10 px-2 py-0.5"
                              x-text="'★ ' + Number(fiche.tmdb_rating).toFixed(1) + '/10'"></span>
                    </template>
                </p>

                <template x-if="genres(fiche.genres)">
                    <p class="mt-1 text-xs text-slate-400" x-text="genres(fiche.genres)"></p>
                </template>

                <template x-if="youtubeId(fiche.trailer_url)">
                    <div class="mt-3">
                        <div class="aspect-video w-full overflow-hidden rounded-2xl bg-black">
                            <iframe class="h-full w-full" allowfullscreen
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    :src="'https://www.youtube-nocookie.com/embed/' + youtubeId(fiche.trailer_url)"
                                    title="Bande-annonce"></iframe>
                        </div>
                        <?php /* Certaines bandes-annonces interdisent l'integration : le
                                  lecteur affiche alors un carre noir. Le lien de repli evite
                                  de rester bloque sans comprendre. */ ?>
                        <a :href="fiche.trailer_url" target="_blank" rel="noopener"
                           class="mt-1 inline-block text-xs text-slate-400 underline">
                            Le lecteur ne démarre pas ? Ouvrir sur YouTube
                        </a>
                    </div>
                </template>

                <template x-if="fiche.overview">
                    <p class="mt-3 text-sm text-slate-200" x-text="fiche.overview"></p>
                </template>

                <template x-if="fiche.memo">
                    <p class="mt-2 text-sm italic text-slate-300">« <span x-text="fiche.memo"></span> »</p>
                </template>

                <p class="mt-2 text-[11px] text-emerald-200"
                   x-text="(fiche.provider_brands && fiche.provider_brands.length
                            ? fiche.provider_brands.join(', ')
                            : 'aucune plateforme connue')"></p>
                <template x-if="fiche.needs_warning">
                    <p class="mt-1 w-fit rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] text-amber-100">
                        hors abonnement
                    </p>
                </template>

                <p class="mt-2 text-[11px] text-slate-500">
                    proposé par <span x-text="fiche.proposer_name"></span>
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="post" class="flex-1">
                        <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
                        <input type="hidden" name="shortlist" :value="movies.map(m => m.id).join(',')">
                        <input type="hidden" name="movie_id" :value="fiche.id">
                        <button class="w-full rounded-xl bg-violet-500 px-3 py-2 text-sm font-medium">
                            On prend celui-là
                        </button>
                    </form>
                    <a :href="'/movie.php?id=' + fiche.id"
                       class="rounded-xl bg-white/10 px-3 py-2 text-sm">Fiche complète</a>
                </div>
            </div>
        </div>
    </template>
</div>
