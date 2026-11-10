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
    try { const s = JSON.parse(sessionStorage.getItem(storeKey)); if (s && s.w > 0) vb = s; } catch {}

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

    map.addEventListener('wheel', (e) => { e.preventDefault(); zoomAt(e.deltaY > 0 ? 1.15 : 1 / 1.15, e.clientX, e.clientY); }, { passive: false });
    document.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
        const z = b.dataset.zoom;
        if (z === 'in') zoomAt(1 / 1.3);
        else if (z === 'out') zoomAt(1.3);
        else if (z === 'reset') { vb = { x: 0, y: 0, w: W, h: H }; apply(); }
        else if (z === 'home' && b.dataset.tile) {
            const r = map.querySelector(`.map-tile[data-tile="${b.dataset.tile}"] rect`);
            if (r) centerOn(+r.getAttribute('x') + +r.getAttribute('width') / 2, +r.getAttribute('y') + +r.getAttribute('width') / 2, W / 2.5);
        }
    }));

    // Drag to pan; a click (little movement) selects.
    let drag = null;
    map.addEventListener('pointerdown', (e) => { if (e.button !== 0) return; drag = { x: e.clientX, y: e.clientY, vx: vb.x, vy: vb.y, moved: false }; map.setPointerCapture(e.pointerId); });
    map.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const r = svg.getBoundingClientRect();
        const dx = (e.clientX - drag.x) / r.width * vb.w, dy = (e.clientY - drag.y) / r.height * vb.h;
        if (Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) > 4) { drag.moved = true; map.classList.add('cursor-grabbing'); }
        if (drag.moved) { vb.x = drag.vx - dx; vb.y = drag.vy - dy; apply(); }
    });
    const endDrag = () => { map.classList.remove('cursor-grabbing'); setTimeout(() => { drag = null; }, 0); };
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
        e.preventDefault();
        if (drag?.moved || e.metaKey || e.ctrlKey || e.shiftKey) return;
        load(a.dataset.tile, true);
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
        if (warn && !confirm(`End turn anyway? Right now ${warn}.`)) e.preventDefault();
    });

    document.addEventListener('keydown', (e) => {
        if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement?.tagName)) return;
        if (e.key === 'Enter') document.getElementById('end-turn-form')?.requestSubmit();
        if (e.key === '+' || e.key === '=') zoomAt(1 / 1.3);
        if (e.key === '-') zoomAt(1.3);
        if (e.key === '0') { vb = { x: 0, y: 0, w: W, h: H }; apply(); }
    });
}
