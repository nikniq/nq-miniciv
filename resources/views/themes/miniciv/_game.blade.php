<section class="card">
    <style>
        :root { --mc-bg: #f4efe1; --mc-panel: #e6dcc3; --mc-accent: #b07a2b; --mc-action: #6b8a2f; --mc-ink: #1b1b1b; }
        .miniciv-wrapper { display:flex; flex-direction:column; gap:1rem; align-items:stretch; }
        .miniciv-panel { background: var(--mc-panel); padding:0.6rem; border-radius:10px; border:1px solid rgba(0,0,0,0.06); width:100%; color:var(--mc-ink); }
        .miniciv-controls { display:flex;flex-direction:column;gap:0.75rem; }
        .miniciv-resources { display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.25rem; }
        .miniciv-res-badge { background:rgba(255,255,255,0.55); padding:0.28rem 0.45rem; border-radius:8px; font-weight:700; display:inline-flex; align-items:center; gap:0.35rem; }
        .miniciv-res-badge span { white-space:nowrap; font-size:0.95rem; }
        .miniciv-res-badge strong { margin-left:0.32rem; color:var(--mc-accent); }
        .miniciv-actions { display:flex;flex-direction:column;gap:0.4rem;margin-top:0.35rem; }
        .miniciv-actions .play-btn { padding:0.45rem 0.6rem; border-radius:8px; font-weight:800; width:100%; text-align:left; border:1px solid rgba(0,0,0,0.15); cursor:pointer; background:#fff8ea; color:#021122; box-shadow:0 3px 8px rgba(0,0,0,0.15); transition:transform .1s ease, opacity .1s ease; font-size:0.95rem; }
        .miniciv-actions .play-btn:hover:not(:disabled) { transform:translateY(-1px); }
        .miniciv-actions .play-btn:active:not(:disabled) { transform:translateY(0); }
        .miniciv-actions .play-btn:disabled { opacity:0.45; cursor:not-allowed; box-shadow:none; }
        .miniciv-actions .play-btn:focus-visible { outline:3px solid rgba(176,122,43,0.5); outline-offset:1px; }
        .miniciv-hotkey { opacity:0.65; margin-left:8px; font-weight:700; font-size:0.85em; }
        .miniciv-footer { margin-top:0.75rem; color:rgba(27,27,27,0.75); font-size:0.9rem; }
        .miniciv-toast { margin-top:0.5rem; padding:0.45rem 0.6rem; border-radius:8px; font-weight:700; font-size:0.92rem; display:none; }
        .miniciv-toast.show { display:block; }
        .miniciv-toast.ok { background:#dcecc8; color:#2b4a10; border:1px solid #b6cf92; }
        .miniciv-toast.err { background:#f6d6d0; color:#7a1f12; border:1px solid #e0a89e; }
        .mc-icon { width:16px; height:16px; vertical-align:middle; }
    </style>

    <!-- Inline SVG icons for dashboard -->
    <svg style="display:none;" aria-hidden="true">
        <symbol id="icon-house" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12 3l9 7v11a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10l9-7z" />
        </symbol>
        <symbol id="icon-farm" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12 2c1.1 0 2 .9 2 2 0 .55-.22 1.05-.59 1.41L13 7h-2l1.59-1.59C12.78 5.05 12.5 4.55 12.5 4 12.5 2.9 13.4 2 14.5 2zM4 13c0 4 4 7 8 7s8-3 8-7c0-2.2-1-4.17-2.5-5.5L12 3 6.5 7.5C5 8.83 4 10.8 4 13z" />
        </symbol>
        <symbol id="icon-wall" viewBox="0 0 24 24">
            <path fill="currentColor" d="M3 7h18v3H3V7zm0 5h18v3H3v-3zm0 5h18v2H3v-2z" />
        </symbol>
    </svg>

    <div class="miniciv-wrapper">
        <div class="miniciv-panel">
            <div class="miniciv-controls">
                <div class="miniciv-resources" id="resources" aria-live="polite"></div>

                <div class="miniciv-actions">
                    <button id="collect-food" class="play-btn" style="background:#ffd6a6;">🍎 Collect Food <span class="miniciv-hotkey">(F)</span></button>
                    <button id="collect-wood" class="play-btn" style="background:#ffd1ff;">🪵 Collect Wood <span class="miniciv-hotkey">(W)</span></button>
                    <button id="collect-stone" class="play-btn" style="background:#e6e6e6;">🪨 Collect Stone <span class="miniciv-hotkey">(S)</span></button>
                    <button id="select-house" class="play-btn">🏠 Build House — 5 wood <span class="miniciv-hotkey">(1)</span></button>
                    <button id="select-farm" class="play-btn">🌾 Build Farm — 8 wood <span class="miniciv-hotkey">(2)</span></button>
                    <button id="select-wall" class="play-btn">🧱 Build Wall — 6 stone <span class="miniciv-hotkey">(3)</span></button>
                    <button id="build-sawmill" class="play-btn" style="display:none">🪚 Build Sawmill — 10 wood</button>
                    <button id="build-barrack" class="play-btn" style="display:none">🏰 Build Barracks — 15 wood, 10 stone</button>
                    @auth
                    <button id="end-turn" class="play-btn" style="background:var(--mc-action);color:#fff;">💾 Save Progress <span class="miniciv-hotkey">(Space)</span></button>
                    @else
                    <a href="{{ route('login') }}" class="play-btn" style="background:var(--mc-action);color:#fff;display:block;text-decoration:none;">🔐 Log in to save your civilisation</a>
                    @endauth
                </div>

                <div id="miniciv-toast" class="miniciv-toast" role="status" aria-live="polite"></div>
            </div>

            <div class="miniciv-footer">
                Collect resources, then spend them on buildings. Farms boost food collection, sawmills boost wood.
                Houses raise your population cap — grow past 20 to unlock the barracks.
                Hotkeys: <strong>F/W/S</strong> collect, <strong>1/2/3</strong> build.
            </div>
        </div>
    </div>
</section>

    <script>
(() => {
    const STORAGE_KEY = 'miniciv_state_v1';
    const defaults = {
        turn: 1,
        population: 1,
        food: 10,
        wood: 20,
        stone: 10,
        houses: 0,
        farms: 0,
        walls: 0,
        sawmills: 0,
        barracks: 0
    };

    // State saved on the server for the logged-in user (null for guests / no save yet)
    const serverState = {!! json_encode($minicivSavedState ?? null) !!};

    function load() {
        if (serverState && typeof serverState === 'object') return Object.assign({}, defaults, serverState);
        try { return Object.assign({}, defaults, JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}); }
        catch(e){ return Object.assign({}, defaults); }
    }
    function save(s){ localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); }
    let state = load();

    const resEl = document.getElementById('resources');
    const toastEl = document.getElementById('miniciv-toast');
    let toastTimer = null;

    function toast(msg, type){
        toastEl.textContent = msg;
        toastEl.className = 'miniciv-toast show ' + (type || 'ok');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toastEl.className = 'miniciv-toast'; }, 2500);
    }

    function fmtBadge(label, value, iconId){
        const icon = iconId ? `<svg class="mc-icon" aria-hidden="true"><use href="#icon-${iconId}" /></svg>` : '';
        return `<div class="miniciv-res-badge">${icon}<span>${label}: <strong>${value}</strong></span></div>`;
    }

    function renderResources(){
        resEl.innerHTML = `
            ${fmtBadge('Turn', state.turn)}
            ${fmtBadge('Pop', state.population + '/' + (state.houses*2 + 1))}
            ${fmtBadge('Houses', state.houses, 'house')}
            ${fmtBadge('Farms', state.farms, 'farm')}
            ${fmtBadge('Walls', state.walls, 'wall')}
            ${fmtBadge('Sawmills', state.sawmills)}
            ${fmtBadge('Barracks', state.barracks)}
            ${fmtBadge('Food', state.food)}
            ${fmtBadge('Wood', state.wood)}
            ${fmtBadge('Stone', state.stone)}
        `;
    }

    // Disable a build button when its cost can't be met, with a tooltip saying why
    function setAffordable(id, affordable, needMsg){
        const el = document.getElementById(id);
        if (!el) return;
        el.disabled = !affordable;
        el.title = affordable ? '' : needMsg;
    }

    function update(){
        renderResources();
        setAffordable('select-house', state.wood >= 5, 'Needs 5 wood');
        setAffordable('select-farm', state.wood >= 8, 'Needs 8 wood');
        setAffordable('select-wall', state.stone >= 6, 'Needs 6 stone');
        // show sawmill button only when at least one farm exists
        const bs = document.getElementById('build-sawmill');
        if (bs) bs.style.display = (state.farms > 0) ? 'block' : 'none';
        setAffordable('build-sawmill', state.wood >= 10, 'Needs 10 wood');
        // show barracks button when population is greater than 20
        const bb = document.getElementById('build-barrack');
        if (bb) bb.style.display = (state.population > 20) ? 'block' : 'none';
        setAffordable('build-barrack', state.wood >= 15 && state.stone >= 10, 'Needs 15 wood and 10 stone');
        save(state);
    }

    function buildHouse(){ if(state.wood < 5) return toast('Not enough wood — a house costs 5 wood', 'err'); state.wood -= 5; state.houses += 1; state.population = Math.min(state.population + 1, state.houses*2 + 1); toast('House built — population cap is now ' + (state.houses*2 + 1)); update(); }
    function buildFarm(){ if(state.wood < 8) return toast('Not enough wood — a farm costs 8 wood', 'err'); state.wood -= 8; state.farms += 1; toast('Farm built — food collection improved'); update(); }
    function buildWall(){ if(state.stone < 6) return toast('Not enough stone — a wall costs 6 stone', 'err'); state.stone -= 6; state.walls += 1; toast('Wall built'); update(); }
    function buildSawmill(){ if(state.wood < 10) return toast('Not enough wood — a sawmill costs 10 wood', 'err'); state.wood -= 10; state.sawmills += 1; toast('Sawmill built — wood collection improved'); update(); }
    function buildBarrack(){ if(state.wood < 15 || state.stone < 10) return toast('Barracks needs 15 wood and 10 stone', 'err'); state.wood -= 15; state.stone -= 10; state.barracks += 1; toast('Barracks built'); update(); }

    function collectFood(){ state.food += 3 + (state.farms || 0); state.turn += 1; update(); }
    function collectWood(){ state.wood += 5 + ((state.sawmills || 0) * 3); state.turn += 1; update(); }
    function collectStone(){ state.stone += 4; state.turn += 1; update(); }

    document.getElementById('select-house').addEventListener('click', buildHouse);
    document.getElementById('select-farm').addEventListener('click', buildFarm);
    document.getElementById('select-wall').addEventListener('click', buildWall);

    document.getElementById('collect-food').addEventListener('click', collectFood);
    document.getElementById('collect-wood').addEventListener('click', collectWood);
    document.getElementById('collect-stone').addEventListener('click', collectStone);

    const sawBtn = document.getElementById('build-sawmill');
    if (sawBtn) sawBtn.addEventListener('click', buildSawmill);
    const barrackBtn = document.getElementById('build-barrack');
    if (barrackBtn) barrackBtn.addEventListener('click', buildBarrack);

    // Save button: persist current state to server (only rendered for authenticated users)
    const saveBtn = document.getElementById('end-turn');
    if (saveBtn) saveBtn.addEventListener('click', async () => {
        const SAVE_URL = '{{ route('miniciv.save') }}';
        const CSRF = '{{ csrf_token() }}';
        saveBtn.disabled = true;
        try {
            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ state })
            });

            if (res.status === 401 || res.status === 419) {
                toast('Your session expired — log in again to save', 'err');
                return;
            }

            const payload = await res.json().catch(() => ({}));
            if (res.ok) {
                toast('Progress saved to your account');
            } else {
                toast(payload.error || 'Save failed', 'err');
            }
        } catch (err) {
            console.error(err);
            toast('Save failed — network error', 'err');
        } finally {
            saveBtn.disabled = false;
        }
    });

    // Hotkeys: F/4=Collect Food, W/5=Collect Wood, S/6=Collect Stone, 1=House, 2=Farm, 3=Wall, Space=Save
    document.addEventListener('keydown', (e) => {
        const tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || e.altKey || e.ctrlKey || e.metaKey) return;
        switch (e.key) {
            case '1': buildHouse(); break;
            case '2': buildFarm(); break;
            case '3': buildWall(); break;
            case '4': collectFood(); break;
            case '5': collectWood(); break;
            case '6': collectStone(); break;
            case 'f': case 'F': collectFood(); break;
            case 'w': case 'W': collectWood(); break;
            case 's': case 'S': collectStone(); break;
            case ' ': if (saveBtn) { e.preventDefault(); saveBtn.click(); } break;
        }
    });

    update();
})();
</script>
