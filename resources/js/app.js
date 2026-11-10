// In-page tile selection for the game map. Clicking a tile fetches just the
// side panel for it instead of reloading the whole page. Falls back to the
// plain link if anything goes wrong.
const map = document.getElementById('map');

if (map) {
    const panelUrl = map.dataset.panelUrl;
    let inflight = null;

    const select = (id) => {
        map.querySelectorAll('.map-tile.is-selected').forEach((el) => el.classList.remove('is-selected'));
        const tile = map.querySelector(`.map-tile[data-tile="${id}"]`);
        if (tile) {
            tile.classList.add('is-selected');
            // Move the selection ring (SVG) to the clicked tile.
            const rect = tile.querySelector('rect');
            const svg = map.querySelector('svg');
            let ring = svg?.querySelector('#selection');
            if (svg && rect) {
                if (!ring) {
                    ring = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    ring.id = 'selection';
                    ring.setAttribute('fill', 'none');
                    ring.setAttribute('stroke', '#fff');
                    ring.setAttribute('stroke-width', '3');
                    ring.setAttribute('rx', '3');
                    ring.style.pointerEvents = 'none';
                    svg.appendChild(ring);
                }
                const s = parseFloat(rect.getAttribute('width'));
                ring.setAttribute('x', parseFloat(rect.getAttribute('x')) + 1.5);
                ring.setAttribute('y', parseFloat(rect.getAttribute('y')) + 1.5);
                ring.setAttribute('width', s - 3);
                ring.setAttribute('height', s - 3);
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
            const res = await fetch(`${panelUrl}?tile=${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: inflight.signal,
            });
            if (!res.ok) throw new Error(res.status);
            const html = await res.text();
            const fresh = document.createElement('div');
            fresh.innerHTML = html;
            const next = fresh.querySelector('#tile-panel');
            if (panel && next) panel.replaceWith(next);
            select(id);
            if (push) {
                const url = new URL(window.location);
                url.searchParams.set('tile', id);
                history.pushState({ tile: id }, '', url);
            }
        } catch (e) {
            if (e.name !== 'AbortError') window.location = `${window.location.pathname}?tile=${id}`;
        }
    };

    map.addEventListener('click', (e) => {
        const a = e.target.closest('a.map-tile');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        load(a.dataset.tile, true);
    });

    // Enter ends the turn when nothing is focused.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement?.tagName)) {
            document.getElementById('end-turn-form')?.requestSubmit();
        }
    });

    window.addEventListener('popstate', (e) => {
        const id = e.state?.tile ?? new URL(window.location).searchParams.get('tile');
        if (id) load(id, false);
    });
}
