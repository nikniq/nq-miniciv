// Hotkeys for resource gathering
document.addEventListener('keydown', function(e) {
    if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) return;
    if (e.key === 'f' || e.key === 'F') {
        gather('food');
        e.preventDefault();
    } else if (e.key === 'w' || e.key === 'W') {
        gather('wood');
        e.preventDefault();
    } else if (e.key === 's' || e.key === 'S') {
        gather('stone');
        e.preventDefault();
    }
});
let state = {
    population: 1,
    food: 10,
    wood: 10,
    stone: 5,
    houses: 0,
    farms: 0,
    barracks: 0,
    soldiers: 0,
    walls: 0,
    explored: 0,
    civilisationsFound: 0,
    turn: 1
};
// IndexedDB setup
const DB_NAME = 'miniciv_db';
const DB_VERSION = 1;
const STORE_NAME = 'game_state';
let db;

function openDB() {
    return new Promise((resolve, reject) => {
        const request = window.indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = function(e) {
            db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME);
            }
        };
        request.onsuccess = function(e) {
            db = e.target.result;
            resolve();
        };
        request.onerror = function(e) {
            reject(e);
        };
    });
}

function saveState() {
    if (!db) return;
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.put(state, 'current');
}

function loadState() {
    return new Promise((resolve) => {
        if (!db) return resolve();
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);
        const req = store.get('current');
        req.onsuccess = function(e) {
            if (e.target.result) {
                state = e.target.result;
            }
            resolve();
        };
        req.onerror = function() {
            resolve();
        };
    });
}

// Session storage support
function saveSessionState() {
    try {
        sessionStorage.setItem('miniciv_state', JSON.stringify(state));
    } catch (e) {
        console.warn('Session storage save failed:', e);
    }
}

function loadSessionState() {
    try {
        const data = sessionStorage.getItem('miniciv_state');
        if (data) {
            state = JSON.parse(data);
        }
    } catch (e) {
        console.warn('Session storage load failed:', e);
    }
}

// Save after every action
function saveAfterAction() {
    saveState();
}

// Barbarian attack event (5% chance per turn)
function barbarianAttack() {
    if (state.turn > 1 && Math.random() < 0.05) {
        let barbarians = Math.floor(Math.random() * 2) + 1; // 1-2 barbarians
        if (state.soldiers >= barbarians) {
            log(`Barbarians attacked (${barbarians}) but your soldiers defended successfully!`);
            state.soldiers -= barbarians;
        } else {
            let lostPop = Math.min(state.population - 1, barbarians * 2);
            state.population -= lostPop;
            state.food = Math.max(0, state.food - barbarians * 5);
            log(`Barbarians attacked (${barbarians})! You lost ${lostPop} population and ${barbarians * 5} food.`);
        }
        updateUI();
    }
}

function explore() {
    // Require at least 1 soldier to explore
    if (state.soldiers < 1) {
        log('You need at least 1 soldier to explore.');
        return;
    }
    state.explored++;
    state.turn++;
    // 30% chance to find a civilisation per explore
    let found = false;
    if (Math.random() < 0.3) {
        state.civilisationsFound++;
        found = true;
    }
    if (found) {
        log('You discovered a new civilisation! Total encountered: ' + state.civilisationsFound);
    } else {
        log('Exploration yielded no new civilisations. Total encountered: ' + state.civilisationsFound);
    }
    updateUI();
    saveAfterAction();
    saveSessionState();
}

function updateUI() {
    document.getElementById('population').textContent = state.population;
    document.getElementById('food').textContent = state.food;
    document.getElementById('wood').textContent = state.wood;
    document.getElementById('stone').textContent = state.stone;
    // Dashboard extra fields
    if (document.getElementById('soldiers')) document.getElementById('soldiers').textContent = state.soldiers;
    if (document.getElementById('explored')) document.getElementById('explored').textContent = state.explored;
    if (document.getElementById('civilisations')) document.getElementById('civilisations').textContent = state.civilisationsFound;
    // Asset dashboard fields
    if (document.getElementById('houses')) document.getElementById('houses').textContent = state.houses;
    if (document.getElementById('barracks')) document.getElementById('barracks').textContent = state.barracks;
    if (document.getElementById('farms')) document.getElementById('farms').textContent = state.farms;
    if (document.getElementById('walls')) document.getElementById('walls').textContent = state.walls;
    // Exploration progress bar (arbitrary max 20 for demo)
    let maxExplored = 20;
    let percent = Math.min(100, Math.round((state.explored / maxExplored) * 100));
    let bar = document.getElementById('explore-bar');
    if (bar) bar.style.width = percent + '%';
    // Update turn counter
    if (document.getElementById('turn')) document.getElementById('turn').textContent = state.turn;
    // Show/hide train soldier button
    var trainBtn = document.getElementById('train-soldier-btn');
    if (trainBtn) trainBtn.style.display = (state.barracks > 0) ? '' : 'none';
    // Show/hide explore button
    var exploreBtn = document.getElementById('explore-btn');
    if (exploreBtn) exploreBtn.style.display = (state.soldiers > 0) ? '' : 'none';
    updateGraphics();
    barbarianAttack();
}
// Show SVG images for buildings
function updateGraphics() {
    // Only show graphics visuals, not asset counts
    let html = `<div style='text-align:center;padding:16px;'>`;
    // Optionally, you can show images for each asset if desired, but not counts
    for (let i = 0; i < state.houses; i++) {
        html += `<img src='assets/house.svg' alt='House' style='width:40px;height:28px;margin:2px;vertical-align:middle;'>`;
    }
    for (let i = 0; i < state.farms; i++) {
        html += `<img src='assets/farm.svg' alt='Farm' style='width:54px;height:20px;margin:2px;vertical-align:middle;'>`;
    }
    for (let i = 0; i < state.barracks; i++) {
        html += `<img src='assets/barracks.svg' alt='Barracks' style='width:40px;height:28px;margin:2px;vertical-align:middle;'>`;
    }
    for (let i = 0; i < state.walls; i++) {
        html += `<img src='assets/wall.svg' alt='Wall' style='width:40px;height:14px;margin:2px;vertical-align:middle;'>`;
    }
    html += `</div>`;
    document.getElementById('graphics').innerHTML = html;
}
function trainSoldier() {
    if (state.barracks > 0 && state.food >= 5 && state.population > 1) {
        state.food -= 5;
        state.population--;
        state.soldiers++;
        state.turn++;
        log('You trained a soldier!');
        updateUI();
        saveAfterAction();
        saveSessionState();
    } else if (state.barracks === 0) {
        log('You need a barracks to train soldiers.');
    } else if (state.food < 5) {
        log('Not enough food to train a soldier.');
    } else if (state.population <= 1) {
        log('Not enough population to train a soldier.');
    }
}

function log(msg) {
    const logDiv = document.getElementById('log');
    logDiv.textContent = msg;
}

function gather(resource) {
    let amount = 2;
    if (resource === 'food' && state.farms > 0) {
        amount += state.farms * 3;
    }
    state[resource] += amount;
    state.turn++;
    log(`You gathered ${amount} ${resource}.`);
    updateUI();
    saveAfterAction();
    saveSessionState();
}

function build(type) {
    let acted = false;
    if (type === 'house') {
        if (state.wood >= 5 && state.stone >= 2) {
            state.wood -= 5;
            state.stone -= 2;
            state.houses++;
            state.population++;
            log('You built a house! Population increased.');
            acted = true;
        } else {
            log('Not enough resources to build a house.');
        }
    } else if (type === 'farm') {
        if (state.wood >= 8 && state.stone >= 5) {
            state.wood -= 8;
            state.stone -= 5;
            state.farms++;
            log('You built a farm! Food gathering improved.');
            acted = true;
        } else {
            log('Not enough resources to build a farm.');
        }
    } else if (type === 'barracks') {
        if (state.wood >= 12 && state.stone >= 10) {
            state.wood -= 12;
            state.stone -= 10;
            state.barracks++;
            log('You built a barracks!');
            acted = true;
        } else {
            log('Not enough resources to build a barracks.');
        }
    } else if (type === 'wall') {
        if (state.wood >= 6 && state.stone >= 8) {
            state.wood -= 6;
            state.stone -= 8;
            state.walls++;
            log('You built a wall!');
            acted = true;
        } else {
            log('Not enough resources to build a wall.');
        }
    }
    if (acted) state.turn++;
    updateUI();
    if (acted) saveAfterAction();
    if (acted) saveSessionState();
}

// Food consumption per population per turn
function nextTurn() {
    let consumption = state.population;
    state.food -= consumption;
    if (state.food < 0) {
        state.food = 0;
        log('Your people are starving!');
    }
    updateUI();
    saveAfterAction();
    saveSessionState();
}

// Advance turn every 20 seconds
setInterval(nextTurn, 20000);

// Load state on startup
openDB().then(loadState).then(() => { loadSessionState(); updateUI(); });
