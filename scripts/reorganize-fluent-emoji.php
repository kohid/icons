<?php
/**
 * Reorganize fluent-emoji/manifest.json:
 *   - Move/insert specific entries into target categories at anchored positions
 *   - Remove entries that don't belong
 *
 * Idempotent. Safe to re-run.
 */

$root         = dirname(__DIR__);
$manifestPath = $root . '/fluent-emoji/manifest.json';
$m = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

function takeOut(array &$m, string $name): ?array {
    foreach ($m['categories'] as $cat => &$entries) {
        foreach ($entries as $i => $e) {
            if (($e['name'] ?? null) === $name) {
                $taken = $e;
                array_splice($entries, $i, 1);
                return $taken;
            }
        }
    }
    return null;
}

function insertAt(array &$m, string $cat, array $entry, ?string $anchor, string $pos): void {
    if (!isset($m['categories'][$cat])) $m['categories'][$cat] = [];
    $arr = &$m['categories'][$cat];
    if ($anchor === null) { $arr[] = $entry; return; }
    foreach ($arr as $i => $e) {
        if (($e['name'] ?? null) === $anchor) {
            $idx = $pos === 'before' ? $i : $i + 1;
            array_splice($arr, $idx, 0, [$entry]);
            return;
        }
    }
    fwrite(STDERR, "  ⚠ Anchor '$anchor' not found in '$cat', appending to end\n");
    $arr[] = $entry;
}

function removeOnly(array &$m, string $cat, string $name): bool {
    if (!isset($m['categories'][$cat])) return false;
    $arr = &$m['categories'][$cat];
    foreach ($arr as $i => $e) {
        if (($e['name'] ?? null) === $name) {
            array_splice($arr, $i, 1);
            return true;
        }
    }
    return false;
}

// ---- Outright removals ----------------------------------------------------
$removals = [
    ['people', 'face-with-bags-under-eyes'],
    ['people', 'dizzy-face'],
    ['nature', 'star-struck'],
];
foreach ($removals as [$cat, $name]) {
    echo (removeOnly($m, $cat, $name) ? "✓ removed" : "  - not present") . " $cat/$name\n";
}

// ---- Move/insert groups (each anchor group preserves user's listed order)
$groups = [
    ['cat' => 'people',  'anchor' => 'broken-heart',       'pos' => 'after',  'items' => ['heart-on-fire', 'heart-with-ribbon']],
    ['cat' => 'people',  'anchor' => 'pink-heart',         'pos' => 'after',  'items' => ['orange-heart']],
    ['cat' => 'food',    'anchor' => 'sushi',              'pos' => 'after',  'items' => ['hot-dog', 'fried-shrimp', 'fish-cake-with-swirl', 'moon-cake']],
    ['cat' => 'travel',  'anchor' => 'world-map',          'pos' => 'before', 'items' => ['globe-showing-europe-africa', 'globe-showing-americas', 'globe-showing-asia-australia', 'globe-with-meridians']],
    ['cat' => 'travel',  'anchor' => 'new-moon-face',      'pos' => 'before', 'items' => ['new-moon', 'waxing-crescent-moon', 'first-quarter-moon', 'waxing-gibbous-moon', 'full-moon', 'waning-gibbous-moon', 'last-quarter-moon', 'waning-crescent-moon', 'crescent-moon']],
    ['cat' => 'objects', 'anchor' => 'blue-book',          'pos' => 'after',  'items' => ['orange-book']],
    ['cat' => 'symbols', 'anchor' => 'red-circle',         'pos' => 'after',  'items' => ['orange-circle']],
    ['cat' => 'symbols', 'anchor' => 'red-square',         'pos' => 'after',  'items' => ['orange-square']],
    ['cat' => 'symbols', 'anchor' => 'white-small-square', 'pos' => 'after',  'items' => ['large-orange-diamond', 'small-orange-diamond']],
];

foreach ($groups as $g) {
    // For 'after' anchor, insert in REVERSE so the final order matches the user's list.
    // For 'before', insert in original order.
    $items = $g['pos'] === 'after' ? array_reverse($g['items']) : $g['items'];
    foreach ($items as $name) {
        $entry = takeOut($m, $name) ?? ['name' => $name];
        insertAt($m, $g['cat'], $entry, $g['anchor'], $g['pos']);
    }
    echo "✓ {$g['cat']} {$g['pos']} {$g['anchor']}: " . implode(', ', $g['items']) . "\n";
}

// ---- Refresh metadata -----------------------------------------------------
$m['total']     = array_sum(array_map('count', $m['categories']));
$m['version']   = '1.3.0';
$m['generated'] = date('c');

file_put_contents(
    $manifestPath,
    json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "\n✓ Done. Total entries: {$m['total']}\n";
