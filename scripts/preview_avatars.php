<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Utils\Avatars;

$html = '<html><head><meta charset="utf-8"><title>Avatars Filmi</title>'
    . '<style>body{font-family:sans-serif;background:#0f172a;color:#e2e8f0}'
    . 'figure{display:inline-block;width:130px;text-align:center;margin:8px}'
    . 'figcaption{font-size:11px;margin-top:4px}</style></head><body>';

foreach (Avatars::byFamily() as $family => $avatars) {
    $html .= '<h2>' . Avatars::FAMILIES[$family] . '</h2>';
    foreach ($avatars as $key => $label) {
        $html .= '<figure>' . Avatars::render($key, 'violet', 96)
            . '<figcaption>' . $label . '</figcaption></figure>';
    }
}

file_put_contents(dirname(__DIR__) . '/tmp/avatars.html', $html . '</body></html>');
echo "tmp/avatars.html généré" . PHP_EOL;
