<?php
/**
 * Build manifest.json for material-design (MDI / Pictogrammers).
 *
 * Naming convention: {base}[-box|circle][-outline].svg
 *   account.svg                → base "account",  variant "regular"
 *   account-outline.svg        → base "account",  variant "outline"
 *   account-box.svg            → base "account",  variant "box"
 *   account-box-outline.svg    → base "account",  variant "outline-box"
 *   account-circle.svg         → base "account",  variant "circle"
 *   account-circle-outline.svg → base "account",  variant "outline-circle"
 */

$root    = dirname(__DIR__);
$svgDir  = $root . '/material-design/svg';
$manPath = $root . '/material-design/manifest.json';

if (!is_dir($svgDir)) { fwrite(STDERR, "missing dir: $svgDir\n"); exit(1); }

$styles = [
    'regular',
    'outline',
    'box',
    'outline-box',
    'circle',
    'outline-circle',
];

function parseVariant(string $name): array {
    // strip -outline first
    $hasOutline = false;
    if (substr($name, -8) === '-outline') {
        $hasOutline = true;
        $name = substr($name, 0, -8);
    }
    // strip -box / -circle if present
    $shape = '';
    if (substr($name, -4) === '-box') {
        $shape = 'box';
        $name  = substr($name, 0, -4);
    } elseif (substr($name, -7) === '-circle') {
        $shape = 'circle';
        $name  = substr($name, 0, -7);
    }
    if ($shape === '' && !$hasOutline)  $variant = 'regular';
    elseif ($shape === '' && $hasOutline) $variant = 'outline';
    elseif ($shape === 'box' && !$hasOutline) $variant = 'box';
    elseif ($shape === 'box' && $hasOutline) $variant = 'outline-box';
    elseif ($shape === 'circle' && !$hasOutline) $variant = 'circle';
    else $variant = 'outline-circle';
    return [$name, $variant];
}

$icons = [];   // base => [variant1, variant2, ...]
$count = 0;
$files = glob($svgDir . '/*.svg');
foreach ($files as $f) {
    $base = basename($f, '.svg');
    [$iconName, $variant] = parseVariant($base);
    if (!isset($icons[$iconName])) $icons[$iconName] = [];
    if (!in_array($variant, $icons[$iconName], true)) $icons[$iconName][] = $variant;
    $count++;
}
ksort($icons);

// Per-style counts
$styleCounts = array_fill_keys($styles, 0);
foreach ($icons as $variants) {
    foreach ($variants as $v) {
        if (isset($styleCounts[$v])) $styleCounts[$v]++;
    }
}

$manifest = [
    'version'         => '1.0.0',
    'generated'       => date('c'),
    'source'          => 'Material Design Icons (Pictogrammers, Apache 2.0)',
    'totalFiles'      => $count,
    'totalBaseIcons'  => count($icons),
    'styles'          => $styles,
    'styleSuffix'     => [
        'regular'        => '',
        'outline'        => '-outline',
        'box'            => '-box',
        'outline-box'    => '-box-outline',
        'circle'         => '-circle',
        'outline-circle' => '-circle-outline',
    ],
    'styleCounts'     => $styleCounts,
    'icons'           => $icons,
];

file_put_contents(
    $manPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "✓ Wrote $count files / " . count($icons) . " base icons.\n";
foreach ($styleCounts as $s => $c) echo "  $s: $c\n";
