<?php
use App\Utils\Avatars;
?>
<div x-data="{ choix: <?= json_encode($currentAvatar) ?> }" class="space-y-6">
    <?php foreach (Avatars::byFamily() as $family => $avatars): ?>
        <fieldset>
            <legend class="text-xs uppercase tracking-wide text-slate-400 mb-2">
                <?= htmlspecialchars(Avatars::FAMILIES[$family]) ?>
            </legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($avatars as $key => $label): ?>
                    <label class="cursor-pointer rounded-2xl p-1 ring-2 transition"
                           :class="choix === '<?= $key ?>' ? 'ring-violet-400 bg-white/10' : 'ring-transparent'"
                           title="<?= htmlspecialchars($label) ?>">
                        <input type="radio" class="sr-only" name="<?= htmlspecialchars($inputName) ?>"
                               value="<?= $key ?>" x-model="choix"
                               <?= $key === $currentAvatar ? 'checked' : '' ?>>
                        <?= Avatars::render($key, $currentColor, 56) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>
</div>
