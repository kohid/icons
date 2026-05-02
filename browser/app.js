/* icons — browser app
   Vanilla JS, no build step. Loads manifests from same-origin (GitHub Pages),
   serves SVG previews via jsDelivr CDN, copies via Clipboard API. */
(function () {
    'use strict';

    const CDN = 'https://cdn.jsdelivr.net/gh/kohid/icons@main';

    // Skin-tone modifier codepoints (Unicode emoji)
    const TONE_MOD = {
        'light':        '\u{1F3FB}',
        'medium-light': '\u{1F3FC}',
        'medium':       '\u{1F3FD}',
        'medium-dark':  '\u{1F3FE}',
        'dark':         '\u{1F3FF}',
    };

    // Material Symbols style → font family + FILL axis
    const MS_STYLE_MAP = {
        'regular':         { family: 'outlined', fill: 1 },
        'rounded':         { family: 'rounded',  fill: 1 },
        'sharp':           { family: 'sharp',    fill: 1 },
        'outline':         { family: 'outlined', fill: 0 },
        'outline-rounded': { family: 'rounded',  fill: 0 },
        'outline-sharp':   { family: 'sharp',    fill: 0 },
    };

    // Manifest stores tones as full filenames ("waving-hand-medium-light").
    // Find the tone modifier by matching the longest suffix.
    function getToneMod(toneFull) {
        const keys = ['medium-light', 'medium-dark', 'medium', 'light', 'dark'];
        for (const k of keys) {
            if (toneFull.endsWith('-' + k)) return TONE_MOD[k];
        }
        return '';
    }

    // Default style per icon set ('' = "All Style", show every icon)
    const DEFAULT_STYLE = {
        'material-symbols-light': '',
        'material-design': '',
    };

    // Per-set sidebar style options [value, label] — first is "All Style"
    const STYLE_OPTIONS = {
        'material-symbols-light': [
            ['', 'All Style'],
            ['regular', 'Regular'],
            ['rounded', 'Rounded'],
            ['sharp', 'Sharp'],
            ['outline', 'Outline'],
            ['outline-rounded', 'Outline Rounded'],
            ['outline-sharp', 'Outline Sharp'],
        ],
        'material-design': [
            ['', 'All Style'],
            ['regular', 'Regular'],
            ['box', 'Box'],
            ['circle', 'Circle'],
            ['outline', 'Outline'],
            ['outline-box', 'Outline Box'],
            ['outline-circle', 'Outline Circle'],
        ],
    };

    const state = {
        set: 'fluent-emoji',
        manifests: { 'fluent-emoji': null, 'material-symbols-light': null, 'material-design': null },
        category: null,
        style: 'regular',
        query: '',
        // Cross-folder search filter (active only when query AND folders.size > 0)
        filter: {
            folders: new Set(),
            cats: { 'fluent-emoji': new Set(), 'material-symbols-light': new Set(), 'material-design': new Set() },
        },
    };

    const $ = (sel) => document.querySelector(sel);
    const search = $('#search');
    const tabs = document.querySelectorAll('.tab');
    const sidebar = $('#categories');
    const grid = $('#grid');
    const empty = $('#empty');
    const stats = $('#stats');
    const drawer = $('#drawer');
    const drawerBody = $('#drawer-body');
    const themeToggle = $('#theme-toggle');

    /* Theme ----------------------------------------------------------------- */
    const root = document.documentElement;
    const savedTheme = localStorage.getItem('icons-theme') || 'light';
    root.dataset.theme = savedTheme;
    themeToggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
    themeToggle.addEventListener('click', () => {
        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        root.dataset.theme = next;
        localStorage.setItem('icons-theme', next);
        themeToggle.textContent = next === 'dark' ? '☀️' : '🌙';
    });

    /* Manifest loading ------------------------------------------------------ */
    async function loadManifest(set) {
        if (state.manifests[set]) return state.manifests[set];
        const res = await fetch(`./${set}/manifest.json`, { cache: 'no-cache' });
        if (!res.ok) throw new Error(`Failed to load ${set} manifest: ${res.status}`);
        const data = await res.json();
        state.manifests[set] = data;
        return data;
    }

    /* Tab switch ------------------------------------------------------------ */
    async function switchSet(set) {
        if (state.set === set && state.manifests[set]) return;
        state.set = set;
        state.category = null;
        state.style = DEFAULT_STYLE[set] || 'regular';
        state.query = '';
        search.value = '';
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.set === set));
        try {
            await loadManifest(set);
        } catch (err) {
            grid.innerHTML = '';
            empty.hidden = false;
            empty.textContent = `Couldn't load manifest: ${err.message}`;
            return;
        }
        renderSidebar();
        render();
    }

    /* Sidebar --------------------------------------------------------------- */
    function renderSidebar() {
        sidebar.innerHTML = '';
        const m = state.manifests[state.set];

        if (state.set === 'fluent-emoji') {
            sidebar.appendChild(catBtn('All emoji', null, 'category'));
            Object.keys(m.categories).forEach(cat => {
                sidebar.appendChild(catBtn(cat, cat, 'category'));
            });
        } else {
            appendSidebarHeader('Filter by style');
            (STYLE_OPTIONS[state.set] || []).forEach(([val, label]) => {
                sidebar.appendChild(catBtn(label, val, 'style'));
            });
        }
    }

    function appendSidebarHeader(text) {
        const h = document.createElement('div');
        h.className = 'sidebar-header';
        h.textContent = text;
        sidebar.appendChild(h);
    }

    function catBtn(label, val, key) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'cat' + (state[key] === val ? ' is-active' : '');
        b.textContent = label;
        b.addEventListener('click', () => {
            state[key] = val;
            sidebar.querySelectorAll('.cat').forEach(c => c.classList.remove('is-active'));
            b.classList.add('is-active');
            render();
        });
        return b;
    }

    /* Item collection ------------------------------------------------------- */
    function expandTones(item) {
        const base = Object.assign({}, item, { _tone: '' });
        if (!Array.isArray(item.tones) || item.tones.length === 0) return [base];
        const variants = [base];
        item.tones.forEach(toneFull => {
            const v = Object.assign({}, item, { _tone: toneFull });
            if (item.unicode) {
                const mod = getToneMod(toneFull);
                if (mod) v.unicode = item.unicode + mod;
            }
            variants.push(v);
        });
        return variants;
    }

    function getItems() {
        const m = state.manifests[state.set];
        if (state.set === 'fluent-emoji') {
            const cats = state.category ? [state.category] : Object.keys(m.categories);
            const out = [];
            cats.forEach(cat => {
                m.categories[cat].forEach(e => {
                    expandTones(e).forEach(v => out.push(Object.assign({ _cat: cat }, v)));
                });
            });
            return out;
        }
        // Material Symbols / Material Design — same shape: m.icons[name] = [styles]
        // Empty state.style = "All Style" → render every base icon once using
        // its first available variant for display.
        if (!state.style) {
            return Object.entries(m.icons).map(([name, styles]) => ({
                name,
                _style: styles[0] || 'regular',
            }));
        }
        return Object.entries(m.icons)
            .filter(([_, styles]) => styles.includes(state.style))
            .map(([name]) => ({ name }));
    }

    /* Cross-folder filtered search (active when query + filter.folders set) */
    function getFilterItems(query) {
        const out = [];
        state.filter.folders.forEach(folder => {
            const m = state.manifests[folder];
            if (!m) return; // manifest still loading
            const sel = state.filter.cats[folder] || new Set();
            const useAllCats = sel.size === 0;
            if (folder === 'fluent-emoji') {
                const cats = useAllCats ? Object.keys(m.categories) : [...sel];
                cats.forEach(cat => {
                    (m.categories[cat] || []).forEach(e => {
                        if (e.name.indexOf(query) === -1 && !(e.unicode && e.unicode.indexOf(query) !== -1)) return;
                        expandTones(e).forEach(v => out.push(Object.assign({ _set: 'fluent-emoji', _cat: cat }, v)));
                    });
                });
            } else {
                const styles = useAllCats ? m.styles : [...sel];
                Object.entries(m.icons).forEach(([name, available]) => {
                    if (name.indexOf(query) === -1) return;
                    styles.forEach(style => {
                        if (available.includes(style)) out.push({ name, _set: folder, _style: style });
                    });
                });
            }
        });
        return out;
    }

    /* Render grid (incremental) -------------------------------------------- */
    let renderToken = 0;
    const PAGE = 240;
    let renderedItems = [];

    function render() {
        renderToken++;
        const my = renderToken;
        const q = state.query.trim().toLowerCase();
        let items;
        const filterActive = q && state.filter.folders.size > 0;
        if (filterActive) {
            items = getFilterItems(q);
        } else {
            items = getItems();
            if (q) items = items.filter(e =>
                e.name.indexOf(q) !== -1 || (e.unicode && e.unicode.indexOf(q) !== -1)
            );
        }
        renderedItems = items;
        grid.innerHTML = '';
        empty.hidden = items.length > 0;
        appendChunk(0, my);
        const label = filterActive ? 'results' : (state.set === 'fluent-emoji' ? 'emoji' : 'icons');
        stats.textContent = `${items.length.toLocaleString()} ${label}`;
    }

    function appendChunk(start, token) {
        if (token !== renderToken) return;
        const end = Math.min(start + PAGE, renderedItems.length);
        const frag = document.createDocumentFragment();
        for (let i = start; i < end; i++) frag.appendChild(itemEl(renderedItems[i]));
        grid.appendChild(frag);
        if (end < renderedItems.length) {
            const sentinel = document.createElement('div');
            sentinel.className = 'sentinel';
            grid.appendChild(sentinel);
            const io = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    io.disconnect();
                    sentinel.remove();
                    appendChunk(end, token);
                }
            }, { rootMargin: '600px' });
            io.observe(sentinel);
        }
    }

    /* URL / item element --------------------------------------------------- */
    function svgUrl(item) {
        const source = item._set || state.set;
        if (source === 'fluent-emoji') {
            // _tone (when set) is already the full filename (e.g. "waving-hand-medium-light")
            const fname = item._tone || item.name;
            return `${CDN}/fluent-emoji/svg/${fname}.svg`;
        }
        const m = state.manifests[source];
        const style = item._style || state.style;
        const suffix = m.styleSuffix[style] || '';
        return `${CDN}/${source}/svg/${item.name}${suffix}.svg`;
    }

    function itemEl(item) {
        const div = document.createElement('div');
        div.className = 'icon';
        div.title = item.name;
        div.setAttribute('role', 'button');
        div.tabIndex = 0;

        const tooltip = document.createElement('span');
        tooltip.className = 'icon-name';
        tooltip.textContent = item.name;
        div.appendChild(tooltip);

        const source = item._set || state.set;
        const isFluent = source === 'fluent-emoji';
        let copyText;

        if (isFluent && item._tone) {
            // Tone variant — OS often won't compose emoji + skin-tone modifier
            // properly, so render the actual SVG we have on disk.
            const img = document.createElement('img');
            img.className = 'icon-img';
            img.src = svgUrl(item);
            img.alt = item._tone;
            img.loading = 'lazy';
            img.decoding = 'async';
            div.appendChild(img);
            // Copy the composed unicode (correct codepoint sequence) when available.
            copyText = item.unicode || item._tone;
        } else if (isFluent) {
            const glyph = document.createElement('span');
            glyph.className = 'icon-glyph';
            const g = item.unicode || item.name;
            glyph.textContent = g;
            div.appendChild(glyph);
            copyText = g;
        } else {
            // Material Symbols — render the SVG image. Copy = the exact <img>
            // HTML so it can be pasted directly into markup.
            const url = svgUrl(item);
            const img = document.createElement('img');
            img.className = 'icon-img icon-img--material';
            img.src = url;
            img.alt = item.name;
            img.loading = 'lazy';
            img.decoding = 'async';
            div.appendChild(img);
            copyText = `<img class="icon-img icon-img--material" src="${url}" alt="${item.name}" loading="lazy" decoding="async">`;
        }

        const cpBtn = document.createElement('button');
        cpBtn.type = 'button';
        cpBtn.className = 'quick-copy-glyph';
        cpBtn.title = 'Click to copy';
        cpBtn.innerHTML = '<span class="quick-copy-label"><img src="' + CDN + '/material-symbols-light/svg/content-copy-outline-rounded.svg" alt="copy" loading="lazy"></span>';
        cpBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            copyQuick(copyText, cpBtn);
        });
        div.appendChild(cpBtn);

        div.addEventListener('click', () => openDrawer(item));
        div.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDrawer(item);
            }
        });
        return div;
    }

    /* Drawer (copy options) ------------------------------------------------ */
    function openDrawer(item) {
        const url = svgUrl(item);
        const filename = url.split('/').pop();
        const isFluent = state.set === 'fluent-emoji';
        const altText = item.name.replace(/-/g, ' ');
        const imgSnippet = `<img src="${url}" alt="${altText}" loading="lazy">`;
        const cssSnippet = `background: url('${url}') no-repeat center/contain;`;

        drawerBody.innerHTML = '';

        const head = document.createElement('div');
        head.className = 'd-head';
        const big = document.createElement('img');
        big.className = 'd-big';
        big.src = url;
        big.alt = item.name;
        head.appendChild(big);

        const meta = document.createElement('div');
        meta.className = 'd-meta';

        const h3 = document.createElement('h3');
        h3.textContent = item.name;
        meta.appendChild(h3);

        if (isFluent && item.codepoint) {
            const cp = document.createElement('div');
            cp.className = 'd-cp';
            cp.textContent = `U+${item.codepoint.toUpperCase()}`;
            meta.appendChild(cp);
        }
        head.appendChild(meta);
        drawerBody.appendChild(head);

        const rows = [];
        if (isFluent && item.unicode) rows.push(['Unicode', item.unicode]);
        rows.push(['Filename', filename]);
        if (isFluent && item.codepoint) rows.push(['Codepoint file', item.codepoint + '.svg']);
        rows.push(['CDN URL', url]);
        rows.push(['<img> tag', imgSnippet]);
        rows.push(['CSS background', cssSnippet]);

        rows.forEach(([label, value]) => drawerBody.appendChild(copyRow(label, value)));

        // Inline SVG (fetched on demand)
        const svgRow = document.createElement('div');
        svgRow.className = 'd-row';
        svgRow.innerHTML =
            `<div class="d-label">Inline SVG</div>` +
            `<code class="d-val d-svgnote">Click Copy to fetch the &lt;svg&gt; markup</code>`;
        const svgBtn = document.createElement('button');
        svgBtn.type = 'button';
        svgBtn.className = 'd-copy';
        svgBtn.textContent = 'Copy';
        svgBtn.addEventListener('click', async () => {
            svgBtn.textContent = '…';
            try {
                const r = await fetch(url);
                const text = await r.text();
                await navigator.clipboard.writeText(text);
                svgBtn.textContent = '✓';
            } catch (_) {
                svgBtn.textContent = '✗';
            }
            setTimeout(() => svgBtn.textContent = 'Copy', 1200);
        });
        svgRow.appendChild(svgBtn);
        drawerBody.appendChild(svgRow);

        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
    }

    function copyRow(label, value) {
        const row = document.createElement('div');
        row.className = 'd-row';
        row.innerHTML = `<div class="d-label">${label}</div><code class="d-val">${esc(value)}</code>`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'd-copy';
        btn.textContent = 'Copy';
        btn.addEventListener('click', () => copy(value, btn));
        row.appendChild(btn);
        return row;
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
    }
    $('#drawer-close').addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });

    /* Clipboard ------------------------------------------------------------ */
    async function copy(text, btn) {
        const old = btn.textContent;
        try {
            await navigator.clipboard.writeText(text);
            btn.textContent = '✓';
        } catch (_) {
            btn.textContent = '✗';
        }
        setTimeout(() => btn.textContent = old, 1000);
    }

    async function copyQuick(text, btn) {
        const label = btn.querySelector('.quick-copy-label');
        if (!label) return copy(text, btn);
        const oldHtml = label.innerHTML;
        try {
            await navigator.clipboard.writeText(text);
            label.textContent = '✓';
            btn.classList.add('is-copied');
        } catch (_) {
            label.textContent = '✗';
        }
        setTimeout(() => {
            label.innerHTML = oldHtml;
            btn.classList.remove('is-copied');
        }, 1000);
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));
    }

    /* Search (debounced) --------------------------------------------------- */
    let searchTimer;
    search.addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.query = e.target.value;
            render();
        }, 120);
    });

    /* Tabs ----------------------------------------------------------------- */
    tabs.forEach(t => t.addEventListener('click', () => switchSet(t.dataset.set)));

    /* Search filter (folder + category checkboxes) -------------------------- */
    const filterBtn   = $('#search-filter-btn');
    const filterPanel = $('#search-filter-panel');
    const filterAll   = $('#filter-all');
    const folderCheckboxes = filterPanel.querySelectorAll('input[type=checkbox][data-folder]');

    filterBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = filterPanel.hidden;
        filterPanel.hidden = !willOpen;
        filterBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    document.addEventListener('mousedown', (e) => {
        if (filterPanel.hidden) return;
        if (filterPanel.contains(e.target) || filterBtn.contains(e.target)) return;
        filterPanel.hidden = true;
        filterBtn.setAttribute('aria-expanded', 'false');
    });

    folderCheckboxes.forEach(cb => {
        cb.addEventListener('change', async () => {
            const folder = cb.dataset.folder;
            const subWrap = filterPanel.querySelector(`.filter-folder[data-folder="${folder}"] .filter-cats`);
            if (cb.checked) {
                state.filter.folders.add(folder);
                try { await loadManifest(folder); } catch (_) { return; }
                renderFolderCats(folder, subWrap, true); // auto-check all categories
                syncAllFoldersCheckbox();
            } else {
                state.filter.folders.delete(folder);
                if (state.filter.cats[folder]) state.filter.cats[folder].clear();
                subWrap.innerHTML = '';
                subWrap.hidden = true;
                filterAll.checked = false;
            }
            if (state.query) render();
        });
    });

    filterAll.addEventListener('change', async () => {
        if (!filterAll.checked) {
            // Block uncheck — at least one mode must be active.
            filterAll.checked = true;
            return;
        }
        // Cascade: check all folders + all their categories.
        for (const cb of folderCheckboxes) {
            const folder = cb.dataset.folder;
            cb.checked = true;
            state.filter.folders.add(folder);
            try { await loadManifest(folder); } catch (_) { continue; }
            const subWrap = filterPanel.querySelector(`.filter-folder[data-folder="${folder}"] .filter-cats`);
            renderFolderCats(folder, subWrap, true);
        }
        if (state.query) render();
    });

    function renderFolderCats(folder, wrap, checkAll = false) {
        wrap.innerHTML = '';
        const m = state.manifests[folder];
        const cats = folder === 'fluent-emoji' ? Object.keys(m.categories) : (m.styles || []);
        cats.forEach(cat => {
            const label = document.createElement('label');
            label.className = 'filter-row filter-row--cat';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.value = cat;
            if (checkAll) {
                input.checked = true;
                state.filter.cats[folder].add(cat);
            }
            const span = document.createElement('span');
            span.textContent = cat;
            label.appendChild(input);
            label.appendChild(span);
            input.addEventListener('change', () => {
                if (input.checked) state.filter.cats[folder].add(cat);
                else {
                    state.filter.cats[folder].delete(cat);
                    filterAll.checked = false;
                }
                if (state.query) render();
            });
            wrap.appendChild(label);
        });
        wrap.hidden = false;
    }

    function syncAllFoldersCheckbox() {
        const everyFolderChecked = [...folderCheckboxes].every(c => c.checked);
        const everyCatChecked = [...folderCheckboxes].every(c => {
            if (!c.checked) return false;
            const folder = c.dataset.folder;
            const m = state.manifests[folder];
            if (!m) return false;
            const allCats = folder === 'fluent-emoji' ? Object.keys(m.categories) : (m.styles || []);
            return allCats.length > 0 && allCats.every(cat => state.filter.cats[folder].has(cat));
        });
        filterAll.checked = everyFolderChecked && everyCatChecked;
    }

    /* Boot ----------------------------------------------------------------- */
    switchSet('fluent-emoji').then(async () => {
        // "All Folders" is checked by default → cascade check everything so
        // a user typing in the search bar gets cross-folder results from the start.
        for (const cb of folderCheckboxes) {
            const folder = cb.dataset.folder;
            cb.checked = true;
            state.filter.folders.add(folder);
            try { await loadManifest(folder); } catch (_) { continue; }
            const subWrap = filterPanel.querySelector(`.filter-folder[data-folder="${folder}"] .filter-cats`);
            renderFolderCats(folder, subWrap, true);
        }
    });
})();
