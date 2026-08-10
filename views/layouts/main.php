<?php
use App\Utils\Avatars;
use App\Utils\Config;
use App\Utils\Security;

$navigation = [
    '/tonight.php' => 'Ce samedi',
    '/pool.php' => 'Les listes',
    '/add.php' => 'Ajouter',
    '/history.php' => 'Historique',
    '/awards.php' => 'Palmarès',
    '/settings.php' => 'Réglages',
];
$current = $_SERVER['SCRIPT_NAME'] ?? '';
$tmdbMissing = trim((string) Config::get('tmdb_api_key')) === '';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= Security::csrfToken() ?>">
    <title><?= Security::e($title) ?></title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        .filmi-lightstick { animation: filmi-glow 1.8s ease-in-out infinite; transform-origin: center; }
        @keyframes filmi-glow { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
        body { -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="min-h-full bg-slate-900 text-slate-100 antialiased">
<?php if ($tmdbMissing): ?>
    <p class="bg-amber-500/20 text-amber-200 text-sm px-4 py-2 text-center">
        Aucune clé TMDb configurée. La recherche de films est désactivée, la saisie manuelle reste disponible.
    </p>
<?php endif; ?>

<?php if ($profile !== null): ?>
    <header class="sticky top-0 z-10 bg-slate-900/90 backdrop-blur border-b border-white/10">
        <div class="mx-auto max-w-4xl px-4 py-3 flex items-center gap-3">
            <a href="/tonight.php" class="font-semibold tracking-tight text-lg">Filmi</a>
            <nav class="ml-auto flex gap-1 text-sm overflow-x-auto">
                <?php foreach ($navigation as $href => $label): ?>
                    <a href="<?= $href ?>"
                       class="px-2.5 py-1.5 rounded-lg whitespace-nowrap <?= $current === $href ? 'bg-white/15 font-medium' : 'text-slate-300 hover:bg-white/10' ?>">
                        <?= Security::e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <a href="/index.php" title="Changer de profil" class="shrink-0">
                <?= Avatars::render($profile['avatar'], $profile['color'], 36) ?>
            </a>
        </div>
    </header>
<?php endif; ?>

<main class="mx-auto max-w-4xl px-4 py-6"><?= $content ?></main>

<script>
window.filmiPost = async function (url, data) {
    const body = new FormData();
    body.append('csrf', document.querySelector('meta[name="csrf-token"]').content);
    Object.entries(data || {}).forEach(([key, value]) => body.append(key, value));
    const response = await fetch(url, { method: 'POST', body });
    if (!response.ok) {
        throw new Error('La requête a échoué');
    }
    return response.json();
};
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
</script>
</body>
</html>
