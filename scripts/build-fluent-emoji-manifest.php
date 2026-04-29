<?php
/**
 * Build a categorised manifest for the Fluent Emoji SVG set.
 *
 * Usage (from the repo root):
 *   php scripts/build-fluent-emoji-manifest.php
 *
 * Output: fluent-emoji/manifest.json
 */

declare(strict_types=1);

$src = realpath(__DIR__ . '/../fluent-emoji/svg');
if ($src === false || !is_dir($src)) {
    fwrite(STDERR, "Source folder not found: fluent-emoji/svg\n");
    exit(1);
}
$out = dirname($src) . DIRECTORY_SEPARATOR . 'manifest.json';

$names = [];
foreach (scandir($src) as $f) {
    if (str_ends_with(strtolower($f), '.svg')) {
        $names[] = substr($f, 0, -4);
    }
}

// ---------------------------------------------------------------------------
// Skin-tone grouping. Variants like "thumbs-up-light" collapse under base
// "thumbs-up" so the picker shows one tile + tone variants on long-press.
// ---------------------------------------------------------------------------
$tone_suffixes = ['-medium-light', '-medium-dark', '-medium', '-light', '-dark'];

$strip_tone = static function (string $name) use ($tone_suffixes): array {
    foreach ($tone_suffixes as $s) {
        if (str_ends_with($name, $s)) {
            return [substr($name, 0, -strlen($s)), substr($s, 1)];
        }
    }
    return [$name, null];
};

$bases = [];
foreach ($names as $n) {
    [$base, $tone] = $strip_tone($n);
    $bases[$base] ??= ['name' => $base, 'tones' => []];
    if ($tone !== null) {
        $bases[$base]['tones'][$tone] = $n;
    }
}

// Promote first tone variant to canonical if no toneless file exists.
$name_set = array_flip($names);
foreach ($bases as $base => &$entry) {
    if (!isset($name_set[$base])) {
        $entry['name'] = reset($entry['tones']) ?: $base;
    }
    if (empty($entry['tones'])) {
        unset($entry['tones']);
    } else {
        $entry['tones'] = array_values($entry['tones']);
    }
}
unset($entry);

// ---------------------------------------------------------------------------
// Category rules — priority order, first match wins.
// Each rule is [category, [pattern, ...]]. A pattern is either:
//   - a regex (starts with "/")
//   - a substring match
// ---------------------------------------------------------------------------
$rules = [

    ['flags', [
        '/^flag-/', '/-flag$/',
        'rainbow-flag', 'pirate-flag', 'crossed-flags', 'triangular-flag',
        'transgender-flag', 'chequered-flag',
    ]],

    ['people', [
        // Faces & emotions
        '/face/',
        // Body parts
        '/(^|-)(hand|finger|fist|palm|thumbs|wave|clap|raised|pinch|vulcan|crossed-fingers|love-you-gesture|call-me|ok-hand|writing-hand|nail|muscle|leg|foot|ear|eye|nose|mouth|tooth|tongue|brain|lung|bone|footprints|biting-lip|index-pointing)(-|$)/',
        // People types & roles
        '/(^|-)(man|woman|person|people|baby|child|boy|girl|adult|older|elder|family|couple|kiss|holding-hands|princess|prince|monarch|with-veil|with-crown|with-headscarf)(-|$)/',
        '/(scientist|teacher|judge|farmer|cook|student|artist|pilot|astronaut|firefighter|police|guard|detective|mechanic|factory-worker|technologist|singer|ninja|superhero|supervillain|mage|fairy|vampire|zombie|genie|elf|mermaid|merman|merperson|mermaid|mer-people|santa|mrs-claus|doctor|health-worker|office-worker|construction-worker|footballer|ballet)/',
        // People activities
        '/(running|walking|standing|kneeling|biking|climbing|skier|surfer|rower|golfer|juggler|wrestler|fencer|boxer|weight-lifter|snowboarder|skater|swimmer|in-bed|in-bath|taking-bath|in-steamy-room|getting-haircut|getting-massage|playing|dancer|dancing|riding|tipping-hand|gesturing|raising-hand|pouting|frowning|bowing|facepalming|shrugging|deaf|shrug|sneeze|cartwheel|lifting-weights|playing-handball|playing-water-polo|in-tuxedo|in-lotus-position|in-manual-wheelchair|in-motorized-wheelchair|with-white-cane)/',
        // Hair / appearance
        '/(blonde-hair|red-hair|curly-hair|white-hair|bald)/',
        // Misc
        '/baby-(angel|chick|bottle|symbol)/',
        'pregnant-', 'breast-feeding', 'speaking-head', 'bust-in-silhouette', 'busts-in-silhouette',
        'people-hugging', 'people-with-bunny-ears',
    ]],

    ['nature', [
        // Mammals
        '/(^|-)(dog|cat|lion|tiger|leopard|cheetah|bear|panda|koala|fox|wolf|raccoon|skunk|badger|otter|beaver|hedgehog|mouse|rat|hamster|rabbit|squirrel|chipmunk|bat|monkey|gorilla|orangutan|ape|horse|zebra|deer|bison|ox|cow|pig|ram|sheep|goat|camel|llama|alpaca|giraffe|elephant|mammoth|rhinoceros|hippopotamus|kangaroo|sloth|donkey|moose|water-buffalo|boar|poodle|guide-dog|service-dog|black-cat|paw-prints|see-no-evil-monkey|hear-no-evil-monkey|speak-no-evil-monkey)(-|$)/',
        // Birds
        '/(^|-)(bird|chicken|rooster|hatching-chick|baby-chick|front-facing-baby-chick|eagle|duck|swan|owl|peacock|parrot|flamingo|dove|turkey|penguin|wing|black-bird|feather)(-|$)/',
        // Reptiles & dinos
        '/(turtle|lizard|snake|dragon|sauropod|t-rex|crocodile)/',
        // Aquatic
        '/(^|-)(fish|blowfish|shark|dolphin|whale|octopus|jellyfish|squid|crab|lobster|shrimp|oyster|seal|coral|tropical-fish|spouting-whale)(-|$)/',
        // Bugs
        '/(^|-)(bug|ant|beetle|butterfly|caterpillar|snail|spider|spider-web|scorpion|mosquito|fly|worm|microbe|cockroach|cricket|honeybee|ladybug|lady-beetle)(-|$)/',
        // Plants
        '/(^|-)(cactus|tree|evergreen|deciduous|palm-tree|christmas-tree|seedling|leaf|herb|shamrock|four-leaf-clover|leaves|fallen-leaf|maple-leaf|tulip|rose|hibiscus|sunflower|cherry-blossom|blossom|bouquet|wilted-flower|lotus|daisy|rosette|empty-nest|nest-with-eggs)(-|$)/',
        // Weather / sky
        '/(^|-)(rainbow|sun|crescent-moon|new-moon|full-moon|first-quarter-moon|last-quarter-moon|waning|waxing|moon|star|glowing-star|shooting-star|comet|cloud|rain|snow|snowflake|snowman|thunder|lightning|tornado|fog|wind|dashing-away|droplet|water-wave|fire|sparkles|earth-globe|globe-(showing|with))(-|$)/',
        '/(^|-)(volcano|mount-fuji|mountain)(-|$)/',
        '/(^|-)mushroom(-|$)/',
    ]],

    ['food', [
        '/(^|-)(grapes|melon|watermelon|tangerine|orange|lemon|banana|pineapple|mango|red-apple|green-apple|pear|peach|cherries|strawberry|blueberries|kiwi-fruit|tomato|olive|coconut|avocado|eggplant|potato|carrot|corn|hot-pepper|bell-pepper|cucumber|leafy-green|broccoli|garlic|onion|peanuts|beans|chestnut|brown-mushroom|bread|baguette|flatbread|pretzel|bagel|pancakes|waffle|cheese|meat-on-bone|poultry-leg|cut-of-meat|bacon|hamburger|french-fries|pizza|hot-dog|sandwich|taco|burrito|tamale|stuffed-flatbread|falafel|egg|cooking|shallow-pan|pot-of-food|fondue|bowl-with-spoon|green-salad|popcorn|butter|salt|canned-food|bento|rice-cracker|rice-ball|cooked-rice|curry|steaming-bowl|spaghetti|roasted-sweet-potato|oden|sushi|fried-shrimp|fish-cake|moon-cake|dango|dumpling|fortune-cookie|takeout|soft-ice-cream|shaved-ice|ice-cream|doughnut|cookie|birthday-cake|shortcake|cupcake|pie|chocolate-bar|candy|lollipop|custard|honey-pot|baby-bottle|glass-of-milk|hot-beverage|teapot|teacup|sake|bottle-with-popping-cork|wine-glass|cocktail|tropical-drink|beer|clinking|tumbler|cup-with-straw|bubble-tea|beverage-box|mate|ice-cube|chopsticks|fork-and-knife|spoon|kitchen-knife|jar|pouring-liquid|ginger-root|pea-pod|popsicle)(-|$)/',
    ]],

    ['activities', [
        // Sports & games
        '/(^|-)(soccer-ball|baseball|softball|basketball|volleyball|football|rugby|tennis|flying-disc|bowling|cricket-game|field-hockey|ice-hockey|lacrosse|ping-pong|badminton|boxing-glove|martial-arts|goal-net|flag-in-hole|ice-skate|sled|curling-stone|fishing-pole|diving-mask|running-shirt|sports-medal|trophy|medal|reminder-ribbon|first-place|second-place|third-place|game-die|chess-pawn|jigsaw|joystick|video-game|slot-machine|pool-8-ball|nazar-amulet|hamsa|magic-wand|kite|yo-yo|teddy-bear|pinata|playing-cards|mahjong|flower-playing-cards|black-joker)(-|$)/',
        // Celebrations
        '/(^|-)(jack-o-lantern|fireworks|sparkler|firecracker|balloon|party-popper|confetti-ball|tanabata-tree|pine-decoration|japanese-dolls|carp-streamer|wind-chime|moon-viewing|red-envelope|ribbon|wrapped-gift|admission-tickets|ticket)(-|$)/',
        // Arts & music
        '/(^|-)(performing-arts|framed-picture|artist-palette|thread|sewing-needle|yarn|knot|musical-score|musical-note|musical-notes|microphone|studio-microphone|level-slider|control-knobs|headphone|saxophone|accordion|guitar|musical-keyboard|trumpet|violin|banjo|drum|long-drum|maracas|flute)(-|$)/',
    ]],

    ['travel', [
        // Vehicles
        '/(^|-)(automobile|taxi|sport-utility-vehicle|pickup-truck|bus|trolleybus|minibus|ambulance|fire-engine|police-car|oncoming|articulated-lorry|delivery-truck|tractor|racing-car|motor-scooter|kick-scooter|auto-rickshaw|bicycle|motorcycle|wheelchair|skateboard|roller-skate|train|locomotive|railway-car|high-speed|monorail|mountain-railway|tram|station|metro|aerial-tramway|mountain-cableway|suspension-railway|airplane|small-airplane|helicopter|rocket|flying-saucer|sailboat|speedboat|canoe|ferry|motor-boat|passenger-ship|ship)(-|$)/',
        // Signs / road / map
        '/(^|-)(stop-sign|construction|fuel-pump|wheel|compass|world-map|map-of-japan|barber-pole|anchor|bridge-at-night|carousel-horse|ferris-wheel|roller-coaster|sign|traffic-light|vertical-traffic-light)(-|$)/',
        // Places & buildings
        '/(^|-)(house|hut|derelict|building|cityscape|sunset|sunrise|sunrise-over-mountains|night-with-stars|snow-capped|camping|beach-with-umbrella|desert|desert-island|hot-springs|fountain|tokyo-tower|statue-of-liberty|castle|japanese-castle|stadium|classical-building|brick|rock|wood|office|department-store|factory|hospital|bank|hotel|love-hotel|convenience-store|school|wedding|church|mosque|synagogue|hindu-temple|kaaba|shinto-shrine|torii|moai|red-paper-lantern|japanese-post-office|post-office)(-|$)/',
        // Travel misc
        '/(^|-)(luggage|baggage-claim|left-luggage|passport|customs|airport)(-|$)/',
    ]],

    ['objects', [
        // Clothing
        '/(^|-)(eyeglasses|sunglasses|goggles|necktie|t-shirt|jeans|scarf|gloves|coat|socks|dress|kimono|sari|one-piece-swimsuit|briefs|shorts|bikini|womans-clothes|folding-hand-fan|purse|handbag|clutch-bag|shopping-bags|backpack|thong-sandal|mans-shoe|running-shoe|hiking-boot|flat-shoe|high-heeled-shoe|womans-sandal|womans-boot|ballet-shoes|crown|womans-hat|top-hat|graduation-cap|billed-cap|safety-helmet|rescue-workers-helmet|military-helmet|prayer-beads|lipstick|ring|gem-stone)(-|$)/',
        // Tech & tools & misc objects
        '/(^|-)(mobile-phone|telephone|pager|fax|battery|low-battery|electric-plug|laptop|computer|desktop-computer|printer|keyboard|computer-mouse|trackball|computer-disk|floppy|optical-disk|dvd|videocassette|camera|movie-camera|film-projector|film-frames|television|radio|magnifying-glass|candle|light-bulb|flashlight|lantern|diya-lamp|notebook|book|notebooks|page|scroll|spiral-notepad|spiral-calendar|calendar|tear-off-calendar|card-index|chart|bar-chart|clipboard|pushpin|round-pushpin|paperclip|linked-paperclips|straight-ruler|triangular-ruler|scissors|card-file-box|file-cabinet|wastebasket|locked|unlocked|key|old-key|hammer|axe|pick|hammer-and-pick|hammer-and-wrench|dagger|crossed-swords|pistol|water-pistol|bow-and-arrow|shield|carpentry-saw|wrench|screwdriver|nut-and-bolt|gear|clamp|balance-scale|probing-cane|link|chains|chain|hook|toolbox|magnet|alembic|test-tube|petri-dish|dna|microscope|telescope|satellite-antenna|syringe|pill|adhesive-bandage|stethoscope|crutch|x-ray|lab-coat|safety-vest|sponge|broom|basket|toilet-paper|bathtub|shower|sink|soap|toothbrush|razor|plunger|mouse-trap|bucket|coin|dollar-banknote|yen-banknote|euro-banknote|pound-banknote|money-with-wings|money-bag|credit-card|receipt|chart-increasing-with-yen|outbox|inbox|package|email|envelope|love-letter|closed-mailbox|open-mailbox|postbox|ballot-box|pencil|pen|fountain-pen|paintbrush|crayon|memo|funeral-urn|coffin|headstone|mirror|window|bed|couch-and-lamp|chair|door|elevator|smoking|bomb|placard|identification-card|umbrella|fire-extinguisher|oil-drum|abacus|antenna-bars|satellite|piggy-bank)(-|$)/',
    ]],

    ['symbols', [
        '/(^|-)(red-heart|orange-heart|yellow-heart|green-heart|blue-heart|purple-heart|brown-heart|black-heart|white-heart|grey-heart|light-blue-heart|pink-heart|heart|growing-heart|beating-heart|revolving-hearts|two-hearts|sparkling-heart|broken-heart|mending-heart|kiss-mark|hundred-points|anger-symbol|collision|dizzy|sweat-droplets|speech-balloon|left-speech-bubble|right-anger-bubble|thought-balloon|zzz|atm-sign|litter-in-bin|potable-water|wheelchair-symbol|mens-room|womens-room|restroom|baby-symbol|water-closet|warning|children-crossing|no-entry|prohibited|no-bicycles|no-smoking|no-littering|non-potable-water|no-pedestrians|no-mobile-phones|no-one-under-eighteen|radioactive|biohazard|arrow|aries|taurus|gemini|cancer|leo|virgo|libra|scorpio|sagittarius|capricorn|aquarius|pisces|ophiuchus|shuffle|repeat|fast-forward|fast-reverse|next-track|last-track|play-button|pause-button|stop-button|record-button|eject-button|cinema|dim-button|bright-button|female-sign|male-sign|transgender-symbol|multiplication-sign|plus-sign|minus-sign|division-sign|equal-sign|infinity|heavy-dollar-sign|currency-exchange|medical-symbol|recycling-symbol|fleur-de-lis|trident-emblem|name-badge|japanese-symbol-for-beginner|hollow-red-circle|check-mark|cross-mark|curly-loop|double-curly-loop|sparkle|asterisk|question|exclamation|wavy-dash|copyright|registered|trade-mark|keycap|input-latin|input-numbers|input-symbols|cl-button|cool-button|free-button|id-button|new-button|ng-button|ok-button|sos-button|up-button|vs-button|circled-m|red-circle|orange-circle|yellow-circle|green-circle|blue-circle|purple-circle|brown-circle|black-circle|white-circle|red-square|orange-square|yellow-square|green-square|blue-square|purple-square|brown-square|black-square|white-square|black-large-square|white-large-square|black-medium|white-medium|black-small|white-small|black-square-button|white-square-button|small-orange-diamond|small-blue-diamond|large-orange-diamond|large-blue-diamond|diamond-with-a-dot|radio-button|a-button|b-button|ab-button|o-button|loudspeaker|cheering-megaphone|public-address|bell|muted-speaker|speaker|mahjong-red-dragon|spades|hearts|clubs|diamonds|chequered|white-flag|black-flag|cross-mark-button|atom-symbol|om|star-of-david|wheel-of-dharma|yin-yang|latin-cross|orthodox-cross|star-and-crescent|peace-symbol|menorah|dotted-six-pointed-star|place-of-worship|ballot-box-with-check|alarm-clock|stopwatch|hourglass|mantelpiece-clock|timer-clock|watch|oclock|thirty)(-|$)/',
        // Japanese button glyphs
        '/^japanese-/',
    ]],
];

// ---------------------------------------------------------------------------
// Categorise
// ---------------------------------------------------------------------------
$buckets = [
    'people' => [], 'nature' => [], 'food' => [], 'activities' => [],
    'travel' => [], 'objects' => [], 'symbols' => [], 'flags' => [],
];

$matches = static function (string $name, array $patterns): bool {
    foreach ($patterns as $p) {
        if (str_starts_with($p, '/')) {
            if (preg_match($p, $name)) return true;
        } elseif (str_contains($name, $p)) {
            return true;
        }
    }
    return false;
};

$uncategorised = [];
foreach ($bases as $base => $entry) {
    $hit = null;
    foreach ($rules as [$cat, $patterns]) {
        if ($matches($base, $patterns)) { $hit = $cat; break; }
    }
    if ($hit !== null) {
        $buckets[$hit][] = $entry;
    } else {
        $uncategorised[] = $entry;
        $buckets['objects'][] = $entry;
    }
}

foreach ($buckets as &$arr) {
    usort($arr, fn($a, $b) => strcmp($a['name'], $b['name']));
}
unset($arr);

$manifest = [
    'version'   => '1.0.0',
    'generated' => date('c'),
    'total'     => count($bases),
    'tab_icons' => [
        'people'     => 'grinning-face',
        'nature'     => 'dog-face',
        'food'       => 'hamburger',
        'activities' => 'soccer-ball',
        'travel'     => 'automobile',
        'objects'    => 'light-bulb',
        'symbols'    => 'red-exclamation-mark',
        'flags'      => 'triangular-flag',
    ],
    'categories' => $buckets,
];

file_put_contents(
    $out,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

printf("Wrote %s\n", $out);
foreach ($buckets as $name => $arr) {
    printf("  %-12s %4d\n", $name, count($arr));
}
printf("  uncategorised (sent to objects): %d\n", count($uncategorised));
