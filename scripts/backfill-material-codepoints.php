<?php
/**
 * Backfill `codepoints` map onto material-symbols-light/manifest.json by
 * pulling Google's official codepoints file for Material Symbols Outlined.
 *
 * The three Material Symbols families (Outlined / Rounded / Sharp) all share
 * the same codepoint per icon name — only the rendered glyph differs — so a
 * single map covers all six theme styles.
 */

$root         = dirname(__DIR__);
$manifestPath = $root . '/material-symbols-light/manifest.json';
$cpUrl        = 'https://raw.githubusercontent.com/google/material-design-icons/master/variablefont/MaterialSymbolsOutlined%5BFILL%2CGRAD%2Copsz%2Cwght%5D.codepoints';

if (!is_file($manifestPath)) { fwrite(STDERR, "manifest.json not found\n"); exit(1); }

echo "Fetching codepoints from Google…\n";
$content = @file_get_contents($cpUrl);
if (!$content && function_exists('curl_init')) {
    $ch = curl_init($cpUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $content = curl_exec($ch);
    curl_close($ch);
}
if (!$content) { fwrite(STDERR, "Failed to fetch codepoints file\n"); exit(1); }

// ---- Parse: each line is "icon_name e951" ---------------------------------
$map = [];
foreach (preg_split('/\r?\n/', $content) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $parts = preg_split('/\s+/', $line, 2);
    if (count($parts) !== 2) continue;
    $map[$parts[0]] = strtolower($parts[1]);
}
echo "Loaded " . count($map) . " codepoints from Google.\n";

// ---- Apply to manifest ----------------------------------------------------
$m = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

$codepoints = [];
$missing    = [];
foreach (array_keys($m['icons']) as $iconName) {
    // Manifest names use hyphens; Google's codepoints use underscores.
    $key = str_replace('-', '_', $iconName);
    if (isset($map[$key])) {
        $codepoints[$iconName] = $map[$key];
    } else {
        $missing[] = $iconName;
    }
}

$m['codepoints'] = $codepoints;
$m['version']    = '1.1.0';
$m['generated']  = date('c');

file_put_contents(
    $manifestPath,
    json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "✓ Wrote " . count($codepoints) . " codepoints to manifest.\n";
if ($missing) {
    fwrite(STDERR, "⚠ " . count($missing) . " icons had no Google codepoint match.\n");
    foreach (array_slice($missing, 0, 10) as $n) fwrite(STDERR, "  - $n\n");
    if (count($missing) > 10) fwrite(STDERR, "  ... and " . (count($missing) - 10) . " more\n");
}
