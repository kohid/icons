<?php
/**
 * Reorder fluent-emoji/manifest.json to match the canonical Unicode CLDR
 * order (which is what Emojipedia follows), and reorder skin-tone variants
 * within each entry to: light, medium, medium-light, medium-dark, dark.
 *
 * The "people" category preserves the first $people_keep_count entries
 * (the user's hand-curated prefix). The remainder is appended in canonical
 * order. All other categories are fully reordered.
 *
 * Usage (from the repo root):
 *   php scripts/reorder-manifest.php
 */
declare(strict_types=1);

$root          = realpath(__DIR__ . '/..');
$emoji_test    = $root . '/scripts/emoji-test.txt';
$manifest_path = $root . '/fluent-emoji/manifest.json';

if (!file_exists($emoji_test)) {
    fwrite(STDERR, "Missing scripts/emoji-test.txt\n");
    exit(1);
}
if (!file_exists($manifest_path)) {
    fwrite(STDERR, "Missing fluent-emoji/manifest.json\n");
    exit(1);
}

/* -------------------------------------------------------------------------
 * Slugify a Unicode emoji name to match Microsoft Fluent Emoji filenames.
 * "Woman's hat"               -> "womans-hat"
 * "Kiss: woman, woman"        -> "kiss-woman-woman"
 * "Flag: Saint Kitts & Nevis" -> "flag-saint-kitts-nevis"
 * ----------------------------------------------------------------------- */
function slugify(string $name): string {
    // Strip apostrophes (straight and curly) before splitting on non-alphanum.
    $s = strtolower($name);
    $s = preg_replace('/[\'\x{2019}\x{2018}]/u', '', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? $s;
    return trim($s, '-');
}

/* -------------------------------------------------------------------------
 * Parse emoji-test.txt → ordered slug list per Unicode group.
 * Only fully-qualified entries are used.
 * ----------------------------------------------------------------------- */
$lines = file($emoji_test, FILE_IGNORE_NEW_LINES);
$current_group   = null;
$ordered_by_group = [];

foreach ($lines as $line) {
    if (preg_match('/^#\s*group:\s*(.+?)\s*$/u', $line, $m)) {
        $current_group = $m[1];
        $ordered_by_group[$current_group] ??= [];
        continue;
    }
    if ($current_group === null) continue;
    if ($line === '' || $line[0] === '#') continue;

    // Format: "<codepoints> ; fully-qualified # <emoji> E<ver> <name>"
    if (!preg_match('/;\s*fully-qualified\s*#\s*\S+\s+E[\d.]+\s+(.+?)\s*$/u', $line, $m)) {
        continue;
    }
    $slug = slugify($m[1]);
    if ($slug !== '') {
        $ordered_by_group[$current_group][] = $slug;
    }
}

echo "Unicode groups parsed:\n";
foreach ($ordered_by_group as $g => $list) {
    printf("  %-25s %5d\n", $g, count($list));
}

/* -------------------------------------------------------------------------
 * Build (slug → global-index) map keyed by category.
 * Multiple Unicode groups can map to one of our categories (people uses
 * Smileys & Emotion + People & Body).
 * ----------------------------------------------------------------------- */
$cat_to_groups = [
    'people'     => ['Smileys & Emotion', 'People & Body'],
    'nature'     => ['Animals & Nature'],
    'food'       => ['Food & Drink'],
    'activities' => ['Activities'],
    'travel'     => ['Travel & Places'],
    'objects'    => ['Objects'],
    'symbols'    => ['Symbols'],
    'flags'      => ['Flags'],
];

// Build global slug → canonical position map (concatenating all groups in
// Unicode order). Used for sorting entries that have been cross-categorised
// (e.g. "cloud" lives in our nature bucket but is in Travel & Places per CLDR).
$global_index = [];
$pos = 0;
foreach ($ordered_by_group as $g => $list) {
    foreach ($list as $slug) {
        if (!isset($global_index[$slug])) {
            $global_index[$slug] = $pos++;
        }
    }
}

// Per-category index for the "fill from disk" pass — only fills with entries
// that genuinely belong to the category's Unicode group(s).
$index_for_category = [];
foreach ($cat_to_groups as $cat => $groups) {
    $idx = [];
    $offset = 0;
    foreach ($groups as $g) {
        $list = $ordered_by_group[$g] ?? [];
        foreach ($list as $i => $slug) {
            if (!isset($idx[$slug])) {
                $idx[$slug] = $offset + $i;
            }
        }
        $offset += count($list);
    }
    $index_for_category[$cat] = $idx;
}

/* -------------------------------------------------------------------------
 * Load manifest.
 * ----------------------------------------------------------------------- */
$m = json_decode(file_get_contents($manifest_path), true);
if (!is_array($m) || !isset($m['categories'])) {
    fwrite(STDERR, "manifest.json is not in the expected shape\n");
    exit(1);
}

/* -------------------------------------------------------------------------
 * Reorder skin-tone variants per the user-specified order.
 * ----------------------------------------------------------------------- */
$tone_order = ['-light', '-medium', '-medium-light', '-medium-dark', '-dark'];
$tone_rank = function (string $variant) use ($tone_order): int {
    // Longest-suffix match wins (so "-medium-light" beats "-light").
    $best_rank = PHP_INT_MAX;
    $best_len  = -1;
    foreach ($tone_order as $i => $suffix) {
        if (str_ends_with($variant, $suffix) && strlen($suffix) > $best_len) {
            $best_rank = $i;
            $best_len  = strlen($suffix);
        }
    }
    return $best_rank;
};

foreach ($m['categories'] as $cat => &$entries) {
    foreach ($entries as &$entry) {
        if (!empty($entry['tones'])) {
            usort($entry['tones'], fn($a, $b) => $tone_rank($a) <=> $tone_rank($b));
        }
    }
    unset($entry);
}
unset($entries);

/* -------------------------------------------------------------------------
 * Reorder categories.
 *
 * People is treated as the user's authoritative hand-curated list and
 * never reordered — we only ever ADD missing entries to it (in the
 * "fill from disk" pass below). All other categories are sorted by
 * canonical Unicode order; entries whose slug isn't in the canonical
 * list are kept at the end (preserves their relative current order).
 * ----------------------------------------------------------------------- */
$summary = [];

foreach ($m['categories'] as $cat => &$entries) {
    if (!isset($cat_to_groups[$cat])) {
        $summary[$cat] = ['total' => count($entries), 'matched' => 0, 'kept_head' => 0, 'unmatched' => 0];
        continue;
    }

    if ($cat === 'people') {
        // Preserve user's order verbatim; don't sort.
        $summary[$cat] = [
            'total'     => count($entries),
            'matched'   => 0,
            'kept_head' => count($entries),
            'unmatched' => 0,
        ];
        continue;
    }

    $matched   = [];
    $unmatched = [];
    foreach ($entries as $e) {
        if (isset($global_index[$e['name']])) {
            $matched[] = [$global_index[$e['name']], $e];
        } else {
            $unmatched[] = $e;
        }
    }
    usort($matched, fn($a, $b) => $a[0] <=> $b[0]);
    $sorted = array_map(fn($p) => $p[1], $matched);

    $entries = array_merge($sorted, $unmatched);

    $summary[$cat] = [
        'total'     => count($entries),
        'matched'   => count($matched),
        'kept_head' => 0,
        'unmatched' => count($unmatched),
    ];
}
unset($entries);

/* -------------------------------------------------------------------------
 * Fill missing entries from the SVG folder.
 *
 * The user trimmed the manifest while reordering; some emojis that exist
 * as SVG files are no longer in any category. For each Unicode group we
 * scan the SVG folder, find base names that have files but are absent
 * from the manifest, and append them to the corresponding category in
 * canonical Unicode order (with their tone variants attached).
 * ----------------------------------------------------------------------- */
$svg_dir = $root . '/fluent-emoji/svg';
$tone_suffixes = ['-medium-light', '-medium-dark', '-medium', '-light', '-dark'];

$strip_tone = static function (string $name) use ($tone_suffixes): array {
    foreach ($tone_suffixes as $s) {
        if (str_ends_with($name, $s)) {
            return [substr($name, 0, -strlen($s)), substr($s, 1)];
        }
    }
    return [$name, null];
};

// Build base→tones map from disk
$disk_bases = [];
if (is_dir($svg_dir)) {
    foreach (scandir($svg_dir) as $f) {
        if (!str_ends_with(strtolower($f), '.svg')) continue;
        $name = substr($f, 0, -4);
        [$base, $tone] = $strip_tone($name);
        $disk_bases[$base] ??= ['name' => $base, 'tones' => []];
        if ($tone !== null) $disk_bases[$base]['tones'][$tone] = $name;
    }
    foreach ($disk_bases as $b => &$entry) {
        if (empty($entry['tones'])) {
            unset($entry['tones']);
        } else {
            // Apply user-specified tone order
            $entry['tones'] = array_values($entry['tones']);
            usort($entry['tones'], fn($a, $b) => $tone_rank($a) <=> $tone_rank($b));
        }
    }
    unset($entry);
}

// Build set of slugs already present anywhere in the manifest
$present = [];
foreach ($m['categories'] as $cat => $entries) {
    foreach ($entries as $e) $present[$e['name']] = true;
}

// For each category, append missing entries in canonical order
$filled = array_fill_keys(array_keys($cat_to_groups), 0);
foreach ($cat_to_groups as $cat => $groups) {
    $idx = $index_for_category[$cat];
    $additions = [];
    foreach ($idx as $slug => $rank) {
        if (isset($present[$slug])) continue;
        if (!isset($disk_bases[$slug])) continue; // no SVG file
        $additions[] = [$rank, $disk_bases[$slug]];
        $present[$slug] = true; // claim it for this category
    }
    usort($additions, fn($a, $b) => $a[0] <=> $b[0]);
    $to_add = array_map(fn($p) => $p[1], $additions);
    if ($to_add) {
        $m['categories'][$cat] = array_merge($m['categories'][$cat], $to_add);
        $filled[$cat] = count($to_add);
    }
}

/* -------------------------------------------------------------------------
 * Bump version & write.
 * ----------------------------------------------------------------------- */
$m['version']   = '1.1.0';
$m['generated'] = date('c');
$m['total']     = array_sum(array_map('count', $m['categories']));

file_put_contents(
    $manifest_path,
    json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "\nReordered manifest written to:\n  $manifest_path\n\n";
printf("%-12s %-7s %-9s %-12s %-9s %s\n", 'category', 'total', 'matched', 'kept_head', 'unmatched', 'filled');
foreach ($summary as $cat => $s) {
    $f = $filled[$cat] ?? 0;
    printf("%-12s %-7d %-9d %-12d %-9d %d\n",
        $cat,
        count($m['categories'][$cat]),
        $s['matched'], $s['kept_head'], $s['unmatched'], $f
    );
}
