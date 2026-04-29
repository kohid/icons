# workspace-icons

Self-hosted icon assets for personal and project use, served via [jsDelivr](https://www.jsdelivr.com/).

## Sets

| Set | Files | Base icons | Source | License |
|---|---:|---:|---|---|
| `fluent-emoji` | 3,180 | 1,594 | [microsoft/fluentui-emoji](https://github.com/microsoft/fluentui-emoji) | MIT |
| `material-symbols-light` | 15,147 | 3,758 | [Google Material Symbols](https://fonts.google.com/icons) | Apache 2.0 |

## CDN

Pattern:

```
https://cdn.jsdelivr.net/gh/kohid/icons@<tag>/<set>/svg/<filename>.svg
```

`<tag>` is `main` for the latest commit or a version tag (e.g. `v1.0.0`) for an immutable URL.

### Examples

```
https://cdn.jsdelivr.net/gh/kohid/icons@main/fluent-emoji/svg/grinning-face.svg
https://cdn.jsdelivr.net/gh/kohid/icons@main/material-symbols-light/svg/home-rounded.svg
https://cdn.jsdelivr.net/gh/kohid/icons@main/fluent-emoji/manifest.json
```

For production, pin to a tag:

```bash
git tag v1.0.0
git push --tags
```

then use `@v1.0.0` in the URL — files are cached forever per tag.

## Manifests

Each set ships a `manifest.json` describing what's available. Both manifests are also fetchable via the same CDN URL pattern.

### `fluent-emoji/manifest.json`

Categorised by Unicode emoji groups, with skin-tone variants grouped under each base.

```jsonc
{
  "version": "1.0.0",
  "total": 1594,
  "tab_icons": { "people": "grinning-face", ... },
  "categories": {
    "people":     [ { "name": "grinning-face" }, { "name": "thumbs-up", "tones": ["thumbs-up-light", ...] }, ... ],
    "nature":     [ ... ],
    "food":       [ ... ],
    "activities": [ ... ],
    "travel":     [ ... ],
    "objects":    [ ... ],
    "symbols":    [ ... ],
    "flags":      [ ... ]
  }
}
```

To resolve a filename: `fluent-emoji/svg/<name>.svg`. Tone variants are full filenames already.

### `material-symbols-light/manifest.json`

Every base icon listed once, with the styles available for it.

```jsonc
{
  "version": "1.0.0",
  "totalBaseIcons": 3758,
  "styles": ["regular", "rounded", "sharp", "outline", "outline-rounded", "outline-sharp"],
  "styleSuffix": {
    "regular": "",
    "rounded": "-rounded",
    "sharp": "-sharp",
    "outline": "-outline",
    "outline-rounded": "-outline-rounded",
    "outline-sharp": "-outline-sharp"
  },
  "icons": {
    "home":     ["regular", "rounded", "sharp", "outline", "outline-rounded", "outline-sharp"],
    "settings": ["regular", "rounded", "sharp", "outline", "outline-rounded", "outline-sharp"],
    ...
  }
}
```

To resolve a filename: `material-symbols-light/svg/<name><styleSuffix[style]>.svg`.

### JS usage example

```js
const BASE = 'https://cdn.jsdelivr.net/gh/kohid/icons@main/material-symbols-light';
const manifest = await fetch(`${BASE}/manifest.json`).then(r => r.json());

function iconUrl(name, style = 'regular') {
  if (!manifest.icons[name]?.includes(style)) return null;
  return `${BASE}/svg/${name}${manifest.styleSuffix[style]}.svg`;
}

iconUrl('home', 'rounded');
// → https://cdn.jsdelivr.net/gh/kohid/icons@main/material-symbols-light/svg/home-rounded.svg
```

## Repo layout

```
.
├── fluent-emoji/
│   ├── manifest.json
│   └── svg/                       (3,180 files)
├── material-symbols-light/
│   ├── manifest.json
│   └── svg/                       (15,147 files)
├── scripts/
│   ├── build-fluent-emoji-manifest.php
│   └── build-material-symbols-manifest.php
├── README.md
└── LICENSES.md
```

## Rebuilding the manifests

If you add, remove, or rename SVGs, regenerate the manifest for that set:

```bash
php scripts/build-fluent-emoji-manifest.php
php scripts/build-material-symbols-manifest.php
```

Requires PHP 8.0+.

## License

Each set is redistributed under its upstream license. The repository structure, manifests, and build scripts are MIT.

See [LICENSES.md](LICENSES.md) for full notices.
