<?php
/**
 * Build a manifest for the Material Symbols Light icon set.
 *
 * Each base icon ships in up to six styles, encoded in the filename suffix:
 *   <name>.svg                      regular
 *   <name>-rounded.svg              rounded
 *   <name>-sharp.svg                sharp
 *   <name>-outline.svg              outline
 *   <name>-outline-rounded.svg      outline-rounded
 *   <name>-outline-sharp.svg        outline-sharp
 *
 * Usage (from the repo root):
 *   php scripts/build-material-symbols-manifest.php
 *
 * Output: material-symbols-light/manifest.json
 */

declare(strict_types=1);

$src = realpath(__DIR__ . '/../material-symbols-light/svg');
if ($src === false || !is_dir($src)) {
    fwrite(STDERR, "Source folder not found: material-symbols-light/svg\n");
    exit(1);
}
$out = dirname($src) . DIRECTORY_SEPARATOR . 'manifest.json';

// ---------------------------------------------------------------------------
// Suffix → style. Order matters: longest suffix first, otherwise
// "-outline-rounded" would partial-match against "-outline".
// ---------------------------------------------------------------------------
$style_for_suffix = [
    '-outline-rounded' => 'outline-rounded',
    '-outline-sharp'   => 'outline-sharp',
    '-outline'         => 'outline',
    '-rounded'         => 'rounded',
    '-sharp'           => 'sharp',
];

$style_order = ['regular', 'rounded', 'sharp', 'outline', 'outline-rounded', 'outline-sharp'];

$style_suffix = [
    'regular'         => '',
    'rounded'         => '-rounded',
    'sharp'           => '-sharp',
    'outline'         => '-outline',
    'outline-rounded' => '-outline-rounded',
    'outline-sharp'   => '-outline-sharp',
];

// ---------------------------------------------------------------------------
// Scan
// ---------------------------------------------------------------------------
$files = [];
foreach (scandir($src) as $f) {
    if (str_ends_with(strtolower($f), '.svg')) {
        $files[] = substr($f, 0, -4);
    }
}

$icons = []; // base-name => unique list of styles available
foreach ($files as $name) {
    $style = 'regular';
    $base  = $name;
    foreach ($style_for_suffix as $suffix => $st) {
        if (str_ends_with($name, $suffix)) {
            $style = $st;
            $base  = substr($name, 0, -strlen($suffix));
            break;
        }
    }
    $icons[$base][$style] = true;
}

// Normalise & sort
ksort($icons);
$style_index = array_flip($style_order);
foreach ($icons as $base => $set) {
    $list = array_keys($set);
    usort($list, fn($a, $b) => $style_index[$a] <=> $style_index[$b]);
    $icons[$base] = $list;
}

$style_counts = array_fill_keys($style_order, 0);
foreach ($icons as $list) {
    foreach ($list as $st) $style_counts[$st]++;
}

$manifest = [
    'version'        => '1.0.0',
    'generated'      => date('c'),
    'source'         => 'Google Material Symbols (Apache License 2.0)',
    'totalFiles'     => count($files),
    'totalBaseIcons' => count($icons),
    'styles'         => $style_order,
    'styleSuffix'    => $style_suffix,
    'styleCounts'    => $style_counts,
    'icons'          => $icons,
];

file_put_contents(
    $out,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

printf("Wrote %s\n", $out);
printf("  total files:      %d\n", count($files));
printf("  total base icons: %d\n", count($icons));
foreach ($style_counts as $s => $c) {
    printf("  %-18s %d\n", $s . ':', $c);
}
