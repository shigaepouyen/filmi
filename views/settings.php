<?php
use App\Utils\Providers;
use App\Utils\Security;

$colors = ['slate', 'rose', 'violet', 'emerald', 'amber', 'sky', 'indigo'];
?>
<h1 class="text-2xl font-semibold mb-6">Réglages</h1>

<?php if ($saved): ?>
    <p class="mb-4 rounded-xl bg-emerald-500/20 px-4 py-2 text-sm text-emerald-200">Réglages enregistrés.</p>
<?php endif; ?>

<?php if ($message !== null): ?>
    <p class="mb-4 rounded-xl bg-rose-500/20 px-4 py-2 text-sm text-rose-100"><?= Security::e($message) ?></p>
<?php endif; ?>

<form method="post" class="space-y-8">
    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">

    <section class="space-y-2">
        <label class="block text-sm font-medium" for="start_time">Heure de démarrage du film</label>
        <input id="start_time" name="start_time" type="time" value="<?= Security::e($startTime) ?>"
               class="rounded-xl bg-white/10 px-3 py-2">
        <p class="text-xs text-slate-400">Sert à calculer l'heure de fin affichée sur chaque film.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-sm font-medium">Mon profil</h2>
        <input name="name" value="<?= Security::e($profile['name']) ?>" maxlength="30"
               class="rounded-xl bg-white/10 px-3 py-2">
        <div class="flex gap-2">
            <?php foreach ($colors as $color): ?>
                <label class="cursor-pointer">
                    <input type="radio" name="color" value="<?= $color ?>" class="sr-only"
                           <?= $color === $profile['color'] ? 'checked' : '' ?>>
                    <span class="block h-8 w-8 rounded-full bg-<?= $color ?>-400 ring-2 <?= $color === $profile['color'] ? 'ring-white' : 'ring-transparent' ?>"></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        $currentAvatar = $profile['avatar'];
        $currentColor = $profile['color'];
        $inputName = 'avatar';
        require dirname(__DIR__) . '/views/components/avatar_picker.php';
        ?>
    </section>

    <section class="space-y-3">
        <h2 class="text-sm font-medium">Plateformes auxquelles vous êtes abonnés</h2>
        <p class="text-xs text-slate-400">
            Sert à signaler les films qui ne sont sur aucune des plateformes cochées.
            Sans coche, aucun avertissement n'est affiché.
        </p>
        <?php if ($providerBrands === []): ?>
            <p class="text-xs text-slate-500">Aucune plateforme connue pour l'instant.</p>
        <?php else: ?>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <?php foreach ($providerBrands as $brand): ?>
                    <?php $logo = Providers::logoUrl($brand['logo']); ?>
                    <label class="flex items-center gap-2 rounded-xl bg-white/5 px-3 py-2 text-sm ring-1 ring-white/10">
                        <input type="checkbox" name="brands[]" value="<?= Security::e($brand['brand']) ?>"
                               <?= in_array($brand['brand'], $subscribedBrands, true) ? 'checked' : '' ?>>
                        <?php if ($logo !== null): ?>
                            <img src="<?= Security::e($logo) ?>" alt="" class="h-5 w-5 rounded-full">
                        <?php endif; ?>
                        <?= Security::e($brand['brand']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="text-sm text-slate-400">
        Clé TMDb : <?= $tmdbConfigured ? 'configurée' : 'absente, recherche désactivée' ?>.
        La clé se renseigne dans <code>config/config.php</code>, jamais depuis cette page.
    </section>

    <button class="rounded-xl bg-violet-500 px-4 py-2 font-medium">Enregistrer</button>
</form>

<?php if ($canRefresh): ?>
    <section class="mt-10 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10"
             x-data="{
                works: <?= Security::e(json_encode(array_map(
                    static fn (array $w): array => ['id' => (int) $w['id'], 'title' => (string) $w['title']],
                    $refreshableWorks
                ), JSON_UNESCAPED_UNICODE)) ?>,
                faits: 0, echecs: [], encours: false, termine: false, courant: '',
                get total() { return this.works.length },
                get pourcent() { return this.total === 0 ? 0 : Math.round(this.faits / this.total * 100) },
                async lancer() {
                    this.encours = true; this.termine = false; this.faits = 0; this.echecs = [];
                    // Par lots de cinq : chaque fiche demande un appel TMDb, et une
                    // serie un appel par saison. Tout envoyer d'un coup depasserait
                    // le temps d'execution PHP.
                    for (let i = 0; i < this.works.length; i += 5) {
                        const lot = this.works.slice(i, i + 5);
                        this.courant = lot[0].title;
                        try {
                            const r = await filmiPost('/api/refresh.php', { ids: lot.map(w => w.id).join(',') });
                            this.faits += (r.faits || []).length;
                            this.echecs.push(...(r.echecs || []));
                        } catch (e) {
                            this.echecs.push({ title: lot.map(w => w.title).join(', '), raison: 'requete en echec' });
                            this.faits += lot.length;
                        }
                    }
                    this.courant = ''; this.encours = false; this.termine = true;
                }
             }">
        <h2 class="text-sm font-medium">Mettre à jour les fiches</h2>
        <p class="mt-1 text-xs text-slate-400">
            Récupère chez TMDb les plateformes, l'avis parental, la bande-annonce et la saga
            des <span x-text="total"></span> œuvres encore en liste. La progression d'une série
            en cours n'est jamais touchée.
        </p>

        <button type="button" class="mt-3 rounded-xl bg-white/10 px-4 py-2 text-sm font-medium disabled:opacity-50"
                :disabled="encours || total === 0"
                @click="lancer()">
            <span x-show="!encours">Mettre à jour maintenant</span>
            <span x-show="encours">Mise à jour en cours...</span>
        </button>

        <div x-show="encours || termine" class="mt-3" x-cloak>
            <div class="h-2 w-full overflow-hidden rounded-full bg-white/10">
                <div class="h-2 rounded-full bg-violet-500 transition-all"
                     :style="'width: ' + pourcent + '%'"></div>
            </div>
            <p class="mt-1 text-xs text-slate-400">
                <span x-text="faits"></span> / <span x-text="total"></span>
                <span x-show="courant"> · <span x-text="courant"></span></span>
            </p>
        </div>

        <p x-show="termine && echecs.length === 0" x-cloak
           class="mt-2 rounded-xl bg-emerald-500/20 px-3 py-2 text-sm text-emerald-100">
            Fiches à jour.
        </p>

        <template x-if="termine && echecs.length > 0">
            <div class="mt-2 rounded-xl bg-amber-500/20 px-3 py-2 text-sm text-amber-100">
                <p><span x-text="echecs.length"></span> fiche(s) en échec, les autres sont à jour :</p>
                <ul class="mt-1 list-disc pl-5 text-xs">
                    <template x-for="e in echecs" :key="e.title">
                        <li><span x-text="e.title"></span> <span class="text-amber-200/70" x-text="e.raison"></span></li>
                    </template>
                </ul>
            </div>
        </template>
    </section>
<?php endif; ?>
