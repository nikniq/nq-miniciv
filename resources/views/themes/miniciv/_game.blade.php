<section class="card">
    <style>
        :root { --mc-bg: #f4efe1; --mc-panel: #e6dcc3; --mc-accent: #b07a2b; --mc-action: #6b8a2f; }
        .miniciv-wrapper { display:flex; flex-direction:column; gap:1rem; align-items:stretch; }
        .miniciv-panel { background: var(--mc-panel); padding:0.6rem; border-radius:10px; border:1px solid rgba(0,0,0,0.06); width:100%; color:#1b1b1b; }
        .miniciv-controls { display:flex;flex-direction:column;gap:0.75rem; }
        .miniciv-resources { display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.25rem; }
        .miniciv-badge { background: linear-gradient(90deg,var(--mc-accent), rgba(255,59,129,0.85)); color:#fff; padding:0.35rem 0.6rem; border-radius:8px; font-weight:800; }
        .miniciv-actions { display:flex;flex-direction:column;gap:0.4rem;margin-top:0.35rem; }
        .miniciv-actions .play-btn { padding:0.45rem 0.6rem; border-radius:8px; font-weight:800; box-shadow:0 6px 14px rgba(0,0,0,0.35); width:100%; text-align:left; }
        .miniciv-actions .play-btn.active { outline:3px solid rgba(0,209,255,0.09); transform:translateY(-2px); }
        .miniciv-map-wrap { background:var(--mc-panel); padding:1rem; border-radius:12px; }
        .miniciv-map { display:grid; grid-template-columns: repeat(auto-fit, minmax(56px, 1fr)); gap:8px; }
        .miniciv-tile { aspect-ratio:1/1; min-height:64px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:800; cursor:pointer; color:#fff; background:linear-gradient(180deg,#081026,#0b1220); box-shadow: inset 0 -6px 12px rgba(0,0,0,0.4); }
        .miniciv-tile.empty { background: linear-gradient(180deg,#0b1220,#071028); color:rgba(255,255,255,0.6); }
        .miniciv-tile.house { background: linear-gradient(180deg,#3b8b3b,#2b6b2b); }
        .miniciv-tile.farm { background: linear-gradient(180deg,#8b6b2b,#6b4b1b); }
        .miniciv-tile.wall { background: linear-gradient(180deg,#6b6b6b,#3b3b3b); }
        .miniciv-tile:active { transform:scale(0.995); }
            .miniciv-footer { margin-top:0.75rem; color:rgba(255,255,255,0.75); font-size:0.95rem; }
            @media (max-width:920px) {  }
            .mc-icon { width:16px; height:16px; vertical-align:middle; margin-right:6px; }
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
            <!-- header removed per request -->

            <div class="miniciv-controls">
                <div class="miniciv-resources" id="resources"></div>

                <div class="miniciv-actions">
                    <button id="select-house" class="play-btn">🏠 Build House (5W)</button>
                    <button id="select-farm" class="play-btn">🌾 Build Farm (8W)</button>
                    <button id="select-wall" class="play-btn">🧱 Build Wall (6S)</button>
                    <button id="build-sawmill" class="play-btn" style="display:none">🪚 Build Sawmill (10W)</button>
                        <button id="collect-food" class="play-btn" style="background:#ffd6a6;color:#021122;width:100%;">🍎 Collect Food <span style="opacity:0.8;margin-left:8px;font-weight:700">(F)</span></button>
                        <button id="collect-wood" class="play-btn" style="background:#ffd1ff;color:#021122;width:100%;">🪵 Collect Wood <span style="opacity:0.8;margin-left:8px;font-weight:700">(W)</span></button>
                        <button id="collect-stone" class="play-btn" style="background:#e6e6e6;color:#021122;width:100%;">🪨 Collect Stone <span style="opacity:0.8;margin-left:8px;font-weight:700">(S)</span></button>
                    <button id="end-turn" class="play-btn" style="background:var(--mc-action);color:#021122;">End Turn</button>
                    <button id="reset" class="link button-reset" style="color:var(--mc-accent);">Reset</button>
                </div>
            </div>

            <div class="miniciv-footer">Select a building, then tap a tile to place it. Tap empty tile to preview.</div>
        </div>

        <div class="miniciv-map-wrap">
                <!-- map removed per request -->
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
        sawmills: 0
    };

    function load() { try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || defaults; } catch(e){ return defaults; } }
    function save(s){ localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); }
    let state = load();

    const resEl = document.getElementById('resources');

    function fmtBadge(label, value, iconId){
        const icon = iconId ? `<svg class="mc-icon" aria-hidden="true"><use href="#icon-${iconId}" /></svg>` : '';
        return `<div style="background:rgba(255,255,255,0.03);padding:0.28rem 0.45rem;border-radius:8px;font-weight:700;display:inline-flex;align-items:center;gap:0.35rem;">${icon}<span style=\"white-space:nowrap;font-size:0.95rem\">${label}: <strong style=\"margin-left:0.32rem;color:var(--mc-accent)\">${value}</strong></span></div>`;
    }

    function renderResources(){
        resEl.innerHTML = `
            ${fmtBadge('Turn', state.turn)}
            ${fmtBadge('Pop', state.population + '/' + (state.houses*2 + 1))}
            ${fmtBadge('Houses', state.houses, 'house')}
            ${fmtBadge('Farms', state.farms, 'farm')}
            ${fmtBadge('Walls', state.walls, 'wall')}
            ${fmtBadge('Sawmills', state.sawmills)}
            ${fmtBadge('Food', state.food)}
            ${fmtBadge('Wood', state.wood)}
            ${fmtBadge('Stone', state.stone)}
        `;
    }

    function update(){
        renderResources();
        // show sawmill button only when at least one farm exists
        const bs = document.getElementById('build-sawmill');
        if (bs) bs.style.display = (state.farms && state.farms > 0) ? 'block' : 'none';
        save(state);
    }

    function buildHouse(){ if(state.wood < 5) return alert('Not enough wood'); state.wood -= 5; state.houses += 1; update(); }
    function buildFarm(){ if(state.wood < 8) return alert('Not enough wood'); state.wood -= 8; state.farms += 1; update(); }
    function buildWall(){ if(state.stone < 6) return alert('Not enough stone'); state.stone -= 6; state.walls += 1; update(); }

    function collectFood(){ state.food += 3 + (state.farms || 0); state.turn += 1; update(); }
    function collectWood(){ state.wood += 5 + ((state.sawmills || 0) * 3); state.turn += 1; update(); }
    function collectStone(){ state.stone += 4; state.turn += 1; update(); }

    document.getElementById('select-house').addEventListener('click', buildHouse);
    document.getElementById('select-farm').addEventListener('click', buildFarm);
    document.getElementById('select-wall').addEventListener('click', buildWall);

    document.getElementById('collect-food').addEventListener('click', collectFood);
    document.getElementById('collect-wood').addEventListener('click', collectWood);
    document.getElementById('collect-stone').addEventListener('click', collectStone);

    // Build sawmill (available after at least one farm)
    function buildSawmill(){ if(state.wood < 10) return alert('Not enough wood'); state.wood -= 10; state.sawmills += 1; update(); }
    const sawBtn = document.getElementById('build-sawmill');
    if (sawBtn) sawBtn.addEventListener('click', buildSawmill);

    document.getElementById('end-turn').addEventListener('click', ()=>{
        state.turn += 1;
        state.food += Math.max(1, state.farms * 3);
        state.wood += 2;
        state.stone += 1;
        const foodNeeded = Math.max(1, state.population);
        if (state.food >= foodNeeded) { state.food -= foodNeeded; }
        else { state.population = Math.max(1, state.population - 1); }
        const cap = state.houses*2 + 1;
        if (state.population < cap && state.food >= 2) { state.population += 1; state.food -=2; }
        update();
    });

    document.getElementById('reset').addEventListener('click', ()=>{
        if (!confirm('Reset MiniCiv game?')) return;
        state = JSON.parse(JSON.stringify(defaults)); save(state); update();
    });

    // Hotkeys: 1=House, 2=Farm, 3=Wall, 4=Collect Food, 5=Collect Wood, 6=Collect Stone, Space=End Turn, R=Reset
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
            case ' ': e.preventDefault(); document.getElementById('end-turn').click(); break;
            case 'r': case 'R': document.getElementById('reset').click(); break;
        }
    });

    update();
})();
</script>
