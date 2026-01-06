<section class="card">
    <style>
        .miniciv-controls { display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap; }
        .miniciv-resources { min-width:160px; }
        .miniciv-map { display:grid; grid-template-columns:repeat(8,1fr); gap:6px; }
        .miniciv-tile { aspect-ratio:1/1; min-height:48px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:700; cursor:pointer; }
        @media (max-width:720px) {
            .miniciv-map { grid-template-columns:repeat(auto-fit,minmax(44px,1fr)); gap:8px; }
            .miniciv-tile { min-height:56px; font-size:0.85rem; }
            .miniciv-controls { gap:0.6rem; }
        }
    </style>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="miniciv-controls">
            <div class="miniciv-resources">
                <p class="eyebrow">Resources</p>
                <div id="resources" style="display:flex;gap:0.75rem;flex-wrap:wrap;font-weight:700;"></div>
            </div>

            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button id="build-house" class="play-btn">Build House (wood:5)</button>
                <button id="build-farm" class="play-btn">Build Farm (wood:8)</button>
                <button id="build-wall" class="play-btn">Build Wall (stone:6)</button>
                <button id="end-turn" class="link button-reset" style="background:none;padding:0.6rem 0.9rem;border-radius:8px;border:1px solid rgba(255,255,255,0.06);">End Turn</button>
                <button id="reset" class="link button-reset" style="color:#ffddf0;">Reset</button>
            </div>
        </div>

        <div>
            <p class="eyebrow">Map</p>
            <div id="map" class="miniciv-map">
                <!-- tiles injected here -->
            </div>
        </div>

        <p class="lead">Build houses to increase population cap, farms to increase food income, and walls to reduce raid losses. Simple turn-based mechanics stored in localStorage.</p>
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
        tiles: Array.from({length:64}).map(()=>({type:'empty'}))
    };

    function load() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || defaults; } catch(e){ return defaults; }
    }
    function save(s){ localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); }
    let state = load();

    const mapEl = document.getElementById('map');
    const resEl = document.getElementById('resources');

    function renderResources(){
        resEl.innerHTML = `
            <span>Turn: ${state.turn}</span>
            <span>Pop: ${state.population}/${state.houses*2 + 1}</span>
            <span>Food: ${state.food}</span>
            <span>Wood: ${state.wood}</span>
            <span>Stone: ${state.stone}</span>
        `;
    }

    function renderMap(){
        mapEl.innerHTML = '';
        state.tiles.forEach((tile, idx) => {
            const d = document.createElement('div');
            d.className = 'miniciv-tile';
            d.style.background = tile.type === 'empty' ? 'rgba(255,255,255,0.03)' : 'linear-gradient(180deg,#0b1220,#071028)';
            d.style.color = tile.type === 'empty' ? 'var(--muted)' : '#fff';
            d.textContent = tile.type === 'empty' ? '' : tile.type.toUpperCase().slice(0,3);
            d.title = tile.type === 'empty' ? 'Empty' : tile.type;
            d.onclick = () => {
                if (tile.type === 'empty') {
                    // place selected building if enough resources
                    // noop for now
                }
            };
            mapEl.appendChild(d);
        });
    }

    function update(){ renderResources(); renderMap(); save(state); }

    document.getElementById('build-house').addEventListener('click', ()=>{
        if (state.wood < 5) return alert('Not enough wood');
        state.wood -= 5; state.houses += 1; state.population = Math.min(state.population + 0, state.population);
        update();
    });

    document.getElementById('build-farm').addEventListener('click', ()=>{
        if (state.wood < 8) return alert('Not enough wood');
        state.wood -= 8; state.farms += 1;
        update();
    });

    document.getElementById('build-wall').addEventListener('click', ()=>{
        if (state.stone < 6) return alert('Not enough stone');
        state.stone -= 6; state.walls += 1;
        update();
    });

    document.getElementById('end-turn').addEventListener('click', ()=>{
        state.turn += 1;
        state.food += Math.max(1, state.farms * 3);
        state.wood += 2;
        state.stone += 1;
        const foodNeeded = Math.max(1, state.population);
        if (state.food >= foodNeeded) {
            state.food -= foodNeeded;
        } else {
            state.population = Math.max(1, state.population - 1);
        }
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
