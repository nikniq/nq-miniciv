<section class="card">
    <style>
        :root { --mc-bg: var(--bg, #071028); --mc-panel: var(--panel, #0b1220); --mc-accent: var(--primary, #ff3b81); --mc-action: var(--accent, #00d1ff); }
        .miniciv-wrapper { display:flex; flex-direction:column; gap:1rem; align-items:stretch; }
        .miniciv-panel { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06)); padding:1rem; border-radius:12px; border:1px solid rgba(255,255,255,0.03); width:100%; }
        .miniciv-controls { display:flex;flex-direction:column;gap:0.75rem; }
        .miniciv-resources { display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.25rem; }
        .miniciv-badge { background: linear-gradient(90deg,var(--mc-accent), rgba(255,59,129,0.85)); color:#fff; padding:0.35rem 0.6rem; border-radius:8px; font-weight:800; }
        .miniciv-actions { display:flex;flex-direction:column;gap:0.5rem;margin-top:0.5rem; }
        .miniciv-actions .play-btn { padding:0.6rem 0.8rem; border-radius:10px; font-weight:800; box-shadow:0 8px 20px rgba(0,0,0,0.45); width:100%; text-align:left; }
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
    </style>

    <div class="miniciv-wrapper">
        <div class="miniciv-panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <div>
                    <div class="miniciv-badge">MINICIV</div>
                    <div style="font-weight:800;font-size:1.05rem;margin-top:0.35rem;">Tiny Civilization</div>
                </div>
                <div style="text-align:right;color:rgba(255,255,255,0.8);font-weight:700;">Turn <span id="turn-number">1</span></div>
            </div>

            <div class="miniciv-controls">
                <div class="miniciv-resources" id="resources"></div>

                <div style="display:flex;gap:0.5rem;align-items:center;justify-content:space-between;">
                    <div class="miniciv-actions">
                        <button id="select-house" class="play-btn">🏠 House (5W)</button>
                        <button id="select-farm" class="play-btn">🌾 Farm (8W)</button>
                        <button id="select-wall" class="play-btn">🧱 Wall (6S)</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;align-items:stretch;">
                        <button id="collect-food" class="play-btn" style="background:#ffd6a6;color:#021122;width:100%;">🍎 Collect Food</button>
                        <button id="collect-wood" class="play-btn" style="background:#ffd1ff;color:#021122;width:100%;">🪵 Collect Wood</button>
                        <button id="collect-stone" class="play-btn" style="background:#e6e6e6;color:#021122;width:100%;">🪨 Collect Stone</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;align-items:flex-end;">
                        <button id="end-turn" class="play-btn" style="background:var(--mc-action);color:#021122;">End Turn</button>
                        <button id="reset" class="link button-reset" style="color:var(--mc-accent);">Reset</button>
                    </div>
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
        walls: 0
    };

    function load() { try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || defaults; } catch(e){ return defaults; } }
    function save(s){ localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); }
    let state = load();

    const resEl = document.getElementById('resources');
    const turnEl = document.getElementById('turn-number');

    function fmtBadge(label, value) {
        return `<div style="background:rgba(255,255,255,0.03);padding:0.4rem 0.6rem;border-radius:8px;font-weight:700;">${label}: <strong style=\"margin-left:0.4rem;color:var(--mc-accent)\">${value}</strong></div>`;
    }

    function renderResources(){
        resEl.innerHTML = `
            ${fmtBadge('Pop', state.population + '/' + (state.houses*2 + 1))}
            ${fmtBadge('Food', state.food)}
            ${fmtBadge('Wood', state.wood)}
            ${fmtBadge('Stone', state.stone)}
        `;
        turnEl.textContent = state.turn;
    }

    function update(){ renderResources(); save(state); }

    function buildHouse(){ if(state.wood < 5) return alert('Not enough wood'); state.wood -= 5; state.houses += 1; update(); }
    function buildFarm(){ if(state.wood < 8) return alert('Not enough wood'); state.wood -= 8; state.farms += 1; update(); }
    function buildWall(){ if(state.stone < 6) return alert('Not enough stone'); state.stone -= 6; state.walls += 1; update(); }

    function collectFood(){ state.food += 3; update(); }
    function collectWood(){ state.wood += 5; update(); }
    function collectStone(){ state.stone += 4; update(); }

    document.getElementById('select-house').addEventListener('click', buildHouse);
    document.getElementById('select-farm').addEventListener('click', buildFarm);
    document.getElementById('select-wall').addEventListener('click', buildWall);

    document.getElementById('collect-food').addEventListener('click', collectFood);
    document.getElementById('collect-wood').addEventListener('click', collectWood);
    document.getElementById('collect-stone').addEventListener('click', collectStone);

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

    update();
})();
</script>
