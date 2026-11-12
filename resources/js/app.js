import { init as initMusic, cue } from './music.js';
initMusic();

// Game map: in-page tile selection, pan & zoom, minimap. Falls back to the
// plain links if anything goes wrong.
const map = document.getElementById('map');

if (map) {
    const panelUrl = map.dataset.panelUrl;
    const svg = map.querySelector('svg');
    const storeKey = `miniciv.view.${map.dataset.game}`;
    let inflight = null;

    // ---- viewBox state -----------------------------------------------------
    const full = svg.viewBox.baseVal;
    const W = full.width, H = full.height;
    let vb = { x: 0, y: 0, w: W, h: H };
    let stored = false;
    try { const s = JSON.parse(sessionStorage.getItem(storeKey)); if (s && s.w > 0) { vb = s; stored = true; } } catch {}
    if (!stored && map.dataset.known) {
        // First look at a fogged map: frame the explored area with some margin.
        const [kx, ky, kw, kh] = map.dataset.known.split(',').map(Number);
        const w = Math.max(kw * 1.6, kh * 1.6 * W / H, W / 6);
        vb = { x: kx + kw / 2 - w / 2, y: ky + kh / 2 - (w * H / W) / 2, w, h: w * H / W };
    }

    const apply = () => {
        vb.w = Math.min(W, Math.max(W / 6, vb.w));
        vb.h = vb.w * H / W;
        vb.x = Math.min(W - vb.w, Math.max(0, vb.x));
        vb.y = Math.min(H - vb.h, Math.max(0, vb.y));
        svg.setAttribute('viewBox', `${vb.x} ${vb.y} ${vb.w} ${vb.h}`);
        const mv = document.getElementById('minimap-view');
        if (mv) {
            const mm = document.getElementById('minimap-svg').viewBox.baseVal;
            mv.setAttribute('x', vb.x / W * mm.width); mv.setAttribute('y', vb.y / H * mm.height);
            mv.setAttribute('width', vb.w / W * mm.width); mv.setAttribute('height', vb.h / H * mm.height);
        }
        try { sessionStorage.setItem(storeKey, JSON.stringify(vb)); } catch {}
    };
    apply();

    const toSvg = (clientX, clientY) => {
        const r = svg.getBoundingClientRect();
        return { x: vb.x + (clientX - r.left) / r.width * vb.w, y: vb.y + (clientY - r.top) / r.height * vb.h };
    };
    const zoomAt = (factor, cx, cy) => {
        const p = cx !== undefined ? toSvg(cx, cy) : { x: vb.x + vb.w / 2, y: vb.y + vb.h / 2 };
        const nw = Math.min(W, Math.max(W / 6, vb.w * factor));
        const k = nw / vb.w;
        vb = { x: p.x - (p.x - vb.x) * k, y: p.y - (p.y - vb.y) * k, w: nw, h: nw * H / W };
        apply();
    };
    const centerOn = (x, y, w) => { if (w) vb.w = w; vb.x = x - vb.w / 2; vb.y = y - (vb.w * H / W) / 2; apply(); };

    // Big map: the map takes the full width and more height; panels flow below it.
    const grid = document.getElementById('game-grid');
    const aside = grid?.querySelector('aside');
    let wide = false;
    const setWide = (on) => {
        wide = on;
        if (grid) grid.style.gridTemplateColumns = on ? '1fr' : '';
        map.style.maxHeight = on ? '86vh' : '78vh';
        if (aside) Object.assign(aside.style, on
            ? { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(20rem, 1fr))', alignItems: 'start', gap: '1rem' }
            : { display: '', gridTemplateColumns: '', alignItems: '', gap: '' });
        document.querySelector('[data-zoom="wide"]')?.classList.toggle('ring-1', on);
        try { localStorage.setItem('miniciv.wide', on ? '1' : '0'); } catch {}
    };
    try { if (localStorage.getItem('miniciv.wide') === '1') setWide(true); } catch {}

    // Wheel scrolls the map like a page; hold ⌘/Ctrl (or pinch on a trackpad) to zoom.
    map.addEventListener('wheel', (e) => {
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) { zoomAt(e.deltaY > 0 ? 1.15 : 1 / 1.15, e.clientX, e.clientY); return; }
        const r = svg.getBoundingClientRect();
        const k = vb.w / r.width;
        const dx = (e.shiftKey && !e.deltaX ? e.deltaY : e.deltaX) * k, dy = (e.shiftKey && !e.deltaX ? 0 : e.deltaY) * k;
        vb.x += dx; vb.y += dy; apply();
    }, { passive: false });
    document.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
        const z = b.dataset.zoom;
        if (z === 'in' || z === 'out') {
            // Zoom toward the selected tile, or the capital, when it is in view;
            // otherwise around the middle. Keeps the focus on screen near edges.
            const capital = document.querySelector('[data-zoom="home"]')?.dataset.tile;
            const focus = map.querySelector('.map-tile.is-selected rect') || (capital && map.querySelector(`.map-tile[data-tile="${capital}"] rect`));
            const factor = z === 'in' ? 1 / 1.3 : 1.3;
            if (focus) {
                const r = focus.getBoundingClientRect(), m = svg.getBoundingClientRect();
                const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
                if (cx >= m.left && cx <= m.right && cy >= m.top && cy <= m.bottom) return zoomAt(factor, cx, cy);
            }
            return zoomAt(factor);
        }
        else if (z === 'reset') { vb = { x: 0, y: 0, w: W, h: H }; apply(); }
        else if (z === 'wide') setWide(!wide);
        else if (z === 'home' && b.dataset.tile) {
            const r = map.querySelector(`.map-tile[data-tile="${b.dataset.tile}"] rect`);
            if (r) centerOn(+r.getAttribute('x') + +r.getAttribute('width') / 2, +r.getAttribute('y') + +r.getAttribute('width') / 2, Math.min(W, 16 * +r.getAttribute('width')));
        }
    }));

    // Drag to pan; a click (little movement) selects. Two pointers pinch-zoom.
    let drag = null;
    const pointers = new Map();
    let pinch = null;
    map.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 && e.pointerType === 'mouse') return;
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        // Capture so a drag keeps panning outside the map. Capture retargets
        // later events to the map itself, so remember which tile was pressed.
        map.setPointerCapture(e.pointerId);
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            pinch = { d: Math.hypot(a.x - b.x, a.y - b.y), w: vb.w, cx: (a.x + b.x) / 2, cy: (a.y + b.y) / 2 };
            drag = null;
            return;
        }
        drag = { x: e.clientX, y: e.clientY, vx: vb.x, vy: vb.y, moved: false, tile: e.target.closest('a.map-tile')?.dataset.tile ?? null };
    });
    map.addEventListener('pointermove', (e) => {
        if (pointers.has(e.pointerId)) pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (pinch && pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            const d = Math.hypot(a.x - b.x, a.y - b.y);
            if (d > 0) {
                const p = toSvg(pinch.cx, pinch.cy);
                const nw = Math.min(W, Math.max(W / 6, pinch.w * pinch.d / d));
                const k = nw / vb.w;
                vb = { x: p.x - (p.x - vb.x) * k, y: p.y - (p.y - vb.y) * k, w: nw, h: nw * H / W };
                apply();
            }
            return;
        }
        if (!drag) return;
        const r = svg.getBoundingClientRect();
        const dx = (e.clientX - drag.x) / r.width * vb.w, dy = (e.clientY - drag.y) / r.height * vb.h;
        if (Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) > 4) { drag.moved = true; map.classList.add('cursor-grabbing'); }
        if (drag.moved) { vb.x = drag.vx - dx; vb.y = drag.vy - dy; apply(); }
    });
    const endDrag = (e) => {
        pointers.delete(e.pointerId);
        if (pointers.size < 2) pinch = null;
        map.classList.remove('cursor-grabbing');
        // A press-and-release without movement is a click on that tile.
        if (drag && !drag.moved && drag.tile && e.type === 'pointerup' && !(e.metaKey || e.ctrlKey || e.shiftKey)) {
            load(drag.tile, true);
        }
        drag = null;
    };
    map.addEventListener('pointerup', endDrag);
    map.addEventListener('pointercancel', endDrag);

    const minimap = document.getElementById('minimap');
    minimap?.addEventListener('click', (e) => {
        const r = minimap.getBoundingClientRect();
        centerOn((e.clientX - r.left) / r.width * W, (e.clientY - r.top) / r.height * H, Math.min(vb.w, W / 2));
    });

    // ---- selection ---------------------------------------------------------
    const select = (id) => {
        map.querySelectorAll('.map-tile.is-selected').forEach((el) => el.classList.remove('is-selected'));
        const tile = map.querySelector(`.map-tile[data-tile="${id}"]`);
        if (tile) {
            tile.classList.add('is-selected');
            const rect = tile.querySelector('rect');
            let ring = svg.querySelector('#selection');
            if (rect) {
                if (!ring) {
                    ring = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    ring.id = 'selection';
                    ring.setAttribute('fill', 'none'); ring.setAttribute('stroke', '#fff'); ring.setAttribute('stroke-width', '3'); ring.setAttribute('rx', '3');
                    ring.style.pointerEvents = 'none';
                    svg.appendChild(ring);
                }
                const s = parseFloat(rect.getAttribute('width'));
                ring.setAttribute('x', parseFloat(rect.getAttribute('x')) + 1.5); ring.setAttribute('y', parseFloat(rect.getAttribute('y')) + 1.5);
                ring.setAttribute('width', s - 3); ring.setAttribute('height', s - 3);
            }
        }
        document.querySelectorAll('.selected-tile-input').forEach((el) => { el.value = id; });
    };

    const load = async (id, push) => {
        if (inflight) inflight.abort();
        inflight = new AbortController();
        const panel = document.getElementById('tile-panel');
        if (panel) panel.classList.add('opacity-60');
        try {
            const res = await fetch(`${panelUrl}?tile=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: inflight.signal });
            if (!res.ok) throw new Error(res.status);
            const fresh = document.createElement('div');
            fresh.innerHTML = await res.text();
            const next = fresh.querySelector('#tile-panel');
            if (panel && next) panel.replaceWith(next);
            select(id);
            if (push) { const url = new URL(window.location); url.searchParams.set('tile', id); history.pushState({ tile: id }, '', url); }
        } catch (e) {
            if (e.name !== 'AbortError') window.location = `${window.location.pathname}?tile=${id}`;
        }
    };

    map.addEventListener('click', (e) => {
        const a = e.target.closest('a.map-tile');
        if (!a) return;
        // Modifier clicks keep the browser's open-in-new-tab behaviour.
        if (e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
    });

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a.map-tile-link');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        load(a.dataset.tile, true);
        const r = map.querySelector(`.map-tile[data-tile="${a.dataset.tile}"] rect`);
        if (r) centerOn(+r.getAttribute('x') + +r.getAttribute('width') / 2, +r.getAttribute('y') + +r.getAttribute('width') / 2);
    });

    window.addEventListener('popstate', (e) => {
        const id = e.state?.tile ?? new URL(window.location).searchParams.get('tile');
        if (id) load(id, false);
    });

    // Toasts fade out on their own; hovering keeps them.
    const toasts = document.getElementById('toasts');
    if (toasts) {
        let hover = false;
        toasts.addEventListener('mouseenter', () => { hover = true; });
        toasts.addEventListener('mouseleave', () => { hover = false; });
        setTimeout(function fade() {
            if (hover) return setTimeout(fade, 2000);
            toasts.style.transition = 'opacity 600ms';
            toasts.style.opacity = '0';
            setTimeout(() => toasts.remove(), 650);
        }, 9000);
    }

    document.getElementById('end-turn-form')?.addEventListener('submit', (e) => {
        const warn = e.target.dataset.warn;
        if (warn && !confirm(`End turn anyway? Right now ${warn}.`)) return e.preventDefault();
        cue('turn');
        const btn = e.target.querySelector('button');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ The world turns…'; btn.classList.add('opacity-80'); }
    });

    // Advisor tips: remember dismissals per browser.
    const tipKey = 'miniciv.tips';
    let dismissed = [];
    try { dismissed = JSON.parse(localStorage.getItem(tipKey)) || []; } catch {}
    document.querySelectorAll('.advisor-tip').forEach((el) => {
        if (dismissed.includes(el.dataset.tip)) el.remove();
        el.querySelector('.dismiss-tip')?.addEventListener('click', () => {
            dismissed.push(el.dataset.tip);
            try { localStorage.setItem(tipKey, JSON.stringify(dismissed.slice(-50))); } catch {}
            el.remove();
        });
    });

    document.addEventListener('keydown', (e) => {
        if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement?.tagName)) return;
        if (e.defaultPrevented) return;
        if (e.key === 'Enter') {
            // Only from the page itself: a focused link, summary or widget keeps its own Enter.
            const ae = document.activeElement;
            if (ae && ae !== document.body && ae.closest('a, summary, button, input, select, textarea, [tabindex], [contenteditable]')) return;
            const f = document.getElementById('end-turn-form');
            if (e.repeat || !f || f.querySelector('button')?.disabled) return;
            e.preventDefault();
            f.requestSubmit();
            return;
        }
        if (e.key === '?') { const d = document.getElementById('keys-dialog'); d?.open ? d.close() : d?.showModal(); e.preventDefault(); return; }
        // Arrow keys move the selection one tile.
        if (e.key?.toLowerCase() === 'b' && !e.metaKey && !e.ctrlKey && !e.altKey) { setWide(!wide); e.preventDefault(); return; }
        const pans = { w: [0, -1], a: [-1, 0], s: [0, 1], d: [1, 0] };
        if (pans[e.key?.toLowerCase()] && !e.metaKey && !e.ctrlKey && !e.altKey) {
            const [px, py] = pans[e.key.toLowerCase()];
            vb.x += px * vb.w / 8; vb.y += py * vb.w / 8; apply(); e.preventDefault(); return;
        }
        const dirs = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] };
        if (dirs[e.key]) {
            const cur = map.querySelector('.map-tile.is-selected') || map.querySelector(`.map-tile[data-tile="${new URL(window.location).searchParams.get('tile')}"]`);
            if (!cur) return;
            e.preventDefault();
            const [dx, dy] = dirs[e.key];
            const next = map.querySelector(`.map-tile[data-x="${+cur.dataset.x + dx}"][data-y="${+cur.dataset.y + dy}"]`);
            if (next) {
                load(next.dataset.tile, true);
                const r = next.querySelector('rect');
                const x = +r.getAttribute('x'), y = +r.getAttribute('y'), s = +r.getAttribute('width');
                if (x < vb.x || x + s > vb.x + vb.w || y < vb.y || y + s > vb.y + vb.h) centerOn(x + s / 2, y + s / 2);
            }
        }
        if (e.key === '+' || e.key === '=') zoomAt(1 / 1.3);
        if (e.key === '-') zoomAt(1.3);
        if (e.key === '0') { vb = { x: 0, y: 0, w: W, h: H }; apply(); }
    });
}

// Tech tree diagram: clicking an available node submits the research form.
const researchForm = document.getElementById('research-pick');
if (researchForm) {
    const go = (node) => { researchForm.querySelector('input[name=tech]').value = node.dataset.research; researchForm.submit(); };
    document.querySelectorAll('[data-research]').forEach((node) => {
        node.addEventListener('click', () => go(node));
        node.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); go(node); } });
    });
}

// End-of-game replay: territory frames drawn on a canvas, playable and scrubbable.
const replayData = document.getElementById('replay-data');
if (replayData) {
    const R = JSON.parse(replayData.textContent);
    const canvas = document.getElementById('replay-canvas');
    const slider = document.getElementById('replay-slider');
    const label = document.getElementById('replay-turn');
    const play = document.getElementById('replay-play');
    const px = Math.max(4, Math.floor(480 / R.w));
    canvas.width = R.w * px; canvas.height = R.h * px;
    const ctx = canvas.getContext('2d');
    const base = { o: '#0f2f5c', l: '#3f5f2a', m: '#57534e' };
    const draw = (i) => {
        const [turn, row] = R.frames[i];
        for (let k = 0; k < R.w * R.h; k++) {
            const x = (k % R.w) * px, y = Math.floor(k / R.w) * px, ch = row[k];
            ctx.fillStyle = base[R.land[k]] || '#000';
            ctx.fillRect(x, y, px, px);
            if (ch !== '.') {
                const slot = ch.toLowerCase().charCodeAt(0) - 97;
                ctx.globalAlpha = 0.8; ctx.fillStyle = R.colors[slot] || '#fff'; ctx.fillRect(x, y, px, px); ctx.globalAlpha = 1;
                if (ch !== ch.toLowerCase()) { ctx.fillStyle = '#fff'; ctx.fillRect(x + px / 4, y + px / 4, px / 2, px / 2); }
            }
        }
        label.textContent = `turn ${turn}`;
    };
    let timer = null;
    const stop = () => { clearInterval(timer); timer = null; play.textContent = '▶ Play'; };
    play.addEventListener('click', () => {
        if (timer) return stop();
        if (+slider.value >= R.frames.length - 1) slider.value = 0;
        play.textContent = '❚❚ Pause';
        timer = setInterval(() => {
            if (+slider.value >= R.frames.length - 1) return stop();
            slider.value = +slider.value + 1; draw(+slider.value);
        }, Math.max(40, 4000 / R.frames.length));
    });
    slider.addEventListener('input', () => { stop(); draw(+slider.value); });
    draw(R.frames.length - 1);
}

document.getElementById('keys-open')?.addEventListener('click', () => document.getElementById('keys-dialog')?.showModal());

// Build cards: Shift-click queues instead of replacing. Delegated, because the
// city panel is swapped in by fetch.
document.addEventListener('click', (e) => {
    const card = e.target.closest('.build-cards button[name="build"]');
    if (!card) return;
    card.form.querySelector('.queue-flag').value = e.shiftKey ? '1' : '0';
    if (e.shiftKey) {
        // A Shift-modified submit opens a new window in Chromium: resubmit outside the click.
        e.preventDefault();
        setTimeout(() => card.form.requestSubmit(card), 0);
    }
});

// Coming back via the back/forward cache: undo the "world turns" state.
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    const btn = document.querySelector('#end-turn-form button');
    if (btn) { btn.disabled = false; btn.textContent = 'End turn ▶'; btn.classList.remove('opacity-80'); }
});

// End screen: share the result (native share sheet where available, else copy).
document.querySelector('.share-result')?.addEventListener('click', async (e) => {
    const b = e.currentTarget, text = b.dataset.text, url = b.dataset.url;
    try {
        if (navigator.share) { await navigator.share({ text, url }); return; }
        await navigator.clipboard.writeText(`${text} ${url}`);
        b.textContent = '✓ Copied';
    } catch (err) { if (err?.name === 'AbortError') return; b.textContent = '✕ Could not share'; }
    setTimeout(() => { b.textContent = '📣 Share'; }, 2500);
});

// Collapsible sidebar panels: click a panel's heading to fold it away; the
// choice is remembered per browser. The city panel and checklist keep their own behaviour.
(() => {
    const aside = document.querySelector('#game-grid aside');
    if (!aside) return;
    const KEY = 'miniciv.collapsed';
    let folded = [];
    try { folded = JSON.parse(localStorage.getItem(KEY)) || []; } catch {}
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(folded)); } catch {} };
    aside.querySelectorAll(':scope > section').forEach((section) => {
        if (section.id === 'tile-panel' || section.classList.contains('advisor-tip')) return;
        const head = section.firstElementChild;
        const h2 = head?.matches('h2') ? head : head?.querySelector('h2');
        if (!h2) return;
        // Headings carry live numbers ("Army · strength 10"); key on the words only.
        const key = h2.textContent.split('·')[0].replace(/[0-9/()%+−-]/g, '').trim();
        if (!key) return;
        section.classList.add('foldable');
        h2.classList.add('cursor-pointer', 'select-none');
        h2.title = 'Click to fold or unfold';
        const set = (on) => section.classList.toggle('folded', on);
        set(folded.includes(key));
        h2.addEventListener('click', () => {
            const on = !section.classList.contains('folded');
            set(on);
            folded = on ? [...new Set([...folded, key])] : folded.filter((k) => k !== key);
            save();
        });
    });
})();
