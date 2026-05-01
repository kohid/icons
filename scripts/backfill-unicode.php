<?php
/**
 * Backfill `unicode` (glyph) and `codepoint` (lowercase hex) fields onto
 * every entry in fluent-emoji/manifest.json by looking up each entry's
 * `name` against scripts/emoji-test.txt (the official Unicode CLDR
 * emoji data file).
 *
 * Idempotent — safe to re-run. Entries with no match are left untouched
 * and the names are listed on stderr.
 *
 * Run from anywhere:
 *   php scripts/backfill-unicode.php
 */

$root          = dirname(__DIR__);
$manifestPath  = $root . '/fluent-emoji/manifest.json';
$emojiTestPath = __DIR__ . '/emoji-test.txt';

if (!is_file($manifestPath))  { fwrite(STDERR, "manifest.json not found: $manifestPath\n");   exit(1); }
if (!is_file($emojiTestPath)) { fwrite(STDERR, "emoji-test.txt not found: $emojiTestPath\n"); exit(1); }

// ---------------------------------------------------------------------------
// 1) Build slug -> ['unicode' => glyph, 'codepoint' => 'hex'] map
// ---------------------------------------------------------------------------
// emoji-test.txt format (one row per emoji):
//   1F600                         ; fully-qualified     # 😀 E1.0 grinning face
$map = [];
$lines = file($emojiTestPath);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;

    // Only keep "fully-qualified" rows (the canonical encoding).
    if (!preg_match('/^([0-9A-F ]+)\s*;\s*fully-qualified\s*#\s*(\S+)\s+E\S+\s+(.+)$/u', $line, $m)) {
        continue;
    }

    $codepoint = strtolower(str_replace(' ', '-', trim($m[1])));
    $glyph     = $m[2];
    $name      = trim($m[3]);

    // Slugify the CLDR name to match the theme's filename convention.
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    if (!isset($map[$slug])) {
        $map[$slug] = ['unicode' => $glyph, 'codepoint' => $codepoint];
    }
}

// ---------------------------------------------------------------------------
// 2) Walk the manifest, merge fields, preserve existing structure.
// ---------------------------------------------------------------------------
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

$updated = 0;
$skipped = 0;
$missing = [];

foreach ($manifest['categories'] as $cat => &$entries) {
    foreach ($entries as &$entry) {
        $name = $entry['name'] ?? null;
        if (!$name) { $skipped++; continue; }
        if (isset($map[$name])) {
            $entry['unicode']   = $map[$name]['unicode'];
            $entry['codepoint'] = $map[$name]['codepoint'];
            $updated++;
        } else {
            $missing[] = "$cat/$name";
        }
    }
    unset($entry);
}
unset($entries);

// ---------------------------------------------------------------------------
// 3) Manual overrides for entries whose Microsoft slug differs from CLDR.
//    Apostrophes ("woman's"), spelling ("blond" vs "blonde"), word order
//    ("deaf person" vs "person-deaf"), and renamed entries ("phoenix") all
//    cause the auto-match to miss. These are filled in by hand.
// ---------------------------------------------------------------------------
$overrides = [
    'hugging-face'          => ['🤗',    '1f917'],
    'pouting-face'          => ['😡',    '1f621'],
    'person-blonde-hair'    => ['👱',    '1f471'],
    'woman-blonde-hair'     => ['👱‍♀️',  '1f471-200d-2640-fe0f'],
    'man-blonde-hair'       => ['👱‍♂️',  '1f471-200d-2642-fe0f'],
    'person-deaf'           => ['🧏',    '1f9cf'],
    'man-deaf'              => ['🧏‍♂️',  '1f9cf-200d-2642-fe0f'],
    'woman-deaf'            => ['🧏‍♀️',  '1f9cf-200d-2640-fe0f'],
    'phoenix-bird'          => ['🐦‍🔥', '1f426-200d-1f525'],
    'twelve-oclock'         => ['🕛',    '1f55b'],
    'one-oclock'            => ['🕐',    '1f550'],
    'two-oclock'            => ['🕑',    '1f551'],
    'three-oclock'          => ['🕒',    '1f552'],
    'four-oclock'           => ['🕓',    '1f553'],
    'five-oclock'           => ['🕔',    '1f554'],
    'six-oclock'            => ['🕕',    '1f555'],
    'seven-oclock'          => ['🕖',    '1f556'],
    'eight-oclock'          => ['🕗',    '1f557'],
    'nine-oclock'           => ['🕘',    '1f558'],
    'ten-oclock'            => ['🕙',    '1f559'],
    'eleven-oclock'         => ['🕚',    '1f55a'],
    'womans-clothes'        => ['👚',    '1f45a'],
    'mans-shoe'             => ['👞',    '1f45e'],
    'womans-sandal'         => ['👡',    '1f461'],
    'womans-boot'           => ['👢',    '1f462'],
    'womans-hat'            => ['👒',    '1f452'],
    'rescue-workers-helmet' => ['⛑️',   '26d1-fe0f'],
    'mens-room'             => ['🚹',    '1f6b9'],
    'womens-room'           => ['🚺',    '1f6ba'],
];

$overrideCount = 0;
foreach ($manifest['categories'] as &$entries) {
    foreach ($entries as &$entry) {
        $name = $entry['name'] ?? null;
        if ($name && isset($overrides[$name])) {
            $entry['unicode']   = $overrides[$name][0];
            $entry['codepoint'] = $overrides[$name][1];
            $overrideCount++;
        }
    }
    unset($entry);
}
unset($entries);

echo "✓ Applied $overrideCount manual overrides.\n";

$manifest['version']   = '1.2.0';
$manifest['generated'] = date('c');

file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "✓ Updated $updated entries.\n";
if ($skipped) echo "  ($skipped entries had no name field, skipped)\n";

if ($missing) {
    fwrite(STDERR, "\n⚠ No codepoint match for " . count($missing) . " entries:\n");
    foreach (array_slice($missing, 0, 25) as $m) fwrite(STDERR, "  - $m\n");
    if (count($missing) > 25) fwrite(STDERR, "  ... and " . (count($missing) - 25) . " more\n");
}
