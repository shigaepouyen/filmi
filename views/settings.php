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
