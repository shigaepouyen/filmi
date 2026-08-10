<?php
use App\Utils\Avatars;
use App\Utils\Security;
?>
<h1 class="text-2xl font-semibold mb-1">Qui regarde ce soir ?</h1>
<p class="text-slate-400 mb-6 text-sm">Choisis ton profil, Filmi s'en souviendra sur cet appareil.</p>

<form method="post" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>">
    <?php foreach ($profiles as $item): ?>
        <button type="submit" name="profile_id" value="<?= (int) $item['id'] ?>"
                class="flex flex-col items-center gap-2 rounded-3xl bg-white/5 p-4 ring-1 ring-white/10 transition hover:bg-white/10 active:scale-95">
            <?= Avatars::render($item['avatar'], $item['color'], 96) ?>
            <span class="font-medium"><?= Security::e($item['name']) ?></span>
            <span class="text-xs text-slate-400">
                <?= $item['side'] === 'adult' ? 'Parent' : 'Enfant' ?>
            </span>
        </button>
    <?php endforeach; ?>
</form>
