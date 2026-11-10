// Generative ambient score, synthesised in the browser with the Web Audio
// API. No audio files: a slow chord bed, a wandering pentatonic lead, and a
// low drone. Mood follows the page: at war the mode darkens and the pulse
// quickens. Off by default (browsers block autoplay); the preference and the
// position in the progression persist across the page loads each turn causes.

const KEY = 'miniciv.music';
const POS_KEY = 'miniciv.music.pos';

const MODES = {
    peace: { root: 220, scale: [0, 2, 3, 5, 7, 9, 10], chords: [[0, 2, 4], [5, 0, 2], [3, 5, 0], [4, 6, 1]], bpm: 54 },   // Dorian
    war:   { root: 196, scale: [0, 1, 3, 5, 7, 8, 10], chords: [[0, 2, 4], [1, 3, 5], [0, 2, 4], [5, 0, 2]], bpm: 72 },   // Phrygian
    victory: { root: 262, scale: [0, 2, 4, 5, 7, 9, 11], chords: [[0, 2, 4], [3, 5, 0], [4, 6, 1], [0, 2, 4]], bpm: 60 }, // Ionian
};

let ctx = null, master = null, timer = null, mood = 'peace', chordIdx = 0, leadStep = 0, lastLeadDeg = 4;

const degToHz = (deg, octave, mode) => {
    const scale = MODES[mode].scale;
    const n = scale.length;
    const o = Math.floor(deg / n) + octave;
    return MODES[mode].root * Math.pow(2, (scale[((deg % n) + n) % n] + 12 * o) / 12);
};

function ensureContext() {
    if (ctx) return;
    ctx = new (window.AudioContext || window.webkitAudioContext)();
    master = ctx.createGain();
    master.gain.value = 0;
    // A touch of space: feedback delay in parallel.
    const delay = ctx.createDelay(1.5);
    delay.delayTime.value = 0.42;
    const fb = ctx.createGain(); fb.gain.value = 0.35;
    const wet = ctx.createGain(); wet.gain.value = 0.25;
    const lp = ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 2200;
    master.connect(lp); lp.connect(ctx.destination);
    master.connect(delay); delay.connect(fb); fb.connect(delay); delay.connect(wet); wet.connect(ctx.destination);
}

function pad(freq, start, dur, vol = 0.05) {
    const g = ctx.createGain();
    g.gain.setValueAtTime(0, start);
    g.gain.linearRampToValueAtTime(vol, start + dur * 0.35);
    g.gain.linearRampToValueAtTime(0, start + dur);
    const f = ctx.createBiquadFilter(); f.type = 'lowpass'; f.frequency.value = 900; f.Q.value = 0.7;
    for (const det of [-6, 5]) {
        const o = ctx.createOscillator();
        o.type = 'sawtooth'; o.frequency.value = freq; o.detune.value = det;
        o.connect(f); o.start(start); o.stop(start + dur + 0.1);
    }
    f.connect(g); g.connect(master);
}

function pluck(freq, start, vol = 0.09) {
    const g = ctx.createGain();
    g.gain.setValueAtTime(vol, start);
    g.gain.exponentialRampToValueAtTime(0.0005, start + 1.6);
    const o = ctx.createOscillator(); o.type = 'triangle'; o.frequency.value = freq;
    const o2 = ctx.createOscillator(); o2.type = 'sine'; o2.frequency.value = freq * 2;
    const g2 = ctx.createGain(); g2.gain.value = 0.25;
    o.connect(g); o2.connect(g2); g2.connect(g); g.connect(master);
    o.start(start); o2.start(start); o.stop(start + 1.7); o2.stop(start + 1.7);
}

function drone(freq, start, dur) {
    const g = ctx.createGain();
    g.gain.setValueAtTime(0, start);
    g.gain.linearRampToValueAtTime(0.06, start + 1.5);
    g.gain.linearRampToValueAtTime(0, start + dur);
    const o = ctx.createOscillator(); o.type = 'sine'; o.frequency.value = freq / 2;
    o.connect(g); g.connect(master); o.start(start); o.stop(start + dur + 0.1);
}

function scheduleBar() {
    const m = MODES[mood];
    const beat = 60 / m.bpm;
    const barLen = beat * 4;
    const t = ctx.currentTime + 0.05;
    const chord = m.chords[chordIdx % m.chords.length];
    chord.forEach((deg, i) => pad(degToHz(deg, i === 0 ? 0 : 1, mood), t, barLen * 1.1));
    drone(degToHz(chord[0], -1, mood), t, barLen * 1.05);
    // Lead: a random walk that prefers chord tones and small steps, with rests.
    for (let b = 0; b < 8; b++) {
        if (Math.random() < (mood === 'war' ? 0.35 : 0.5)) continue;
        const step = [-2, -1, -1, 0, 1, 1, 2][Math.floor(Math.random() * 7)];
        lastLeadDeg = Math.max(2, Math.min(12, lastLeadDeg + step));
        const inChord = chord.some((d) => (lastLeadDeg - d) % 7 === 0);
        if (!inChord && Math.random() < 0.4) lastLeadDeg += 1;
        pluck(degToHz(lastLeadDeg, 2, mood), t + b * beat / 2, inChord ? 0.09 : 0.06);
    }
    chordIdx = (chordIdx + 1) % m.chords.length;
    try { sessionStorage.setItem(POS_KEY, String(chordIdx)); } catch {}
    timer = setTimeout(scheduleBar, barLen * 1000 - 60);
}

export function setMood(next) { if (MODES[next]) mood = next; }

export function isOn() { try { return localStorage.getItem(KEY) === 'on'; } catch { return false; } }

export async function start() {
    ensureContext();
    if (ctx.state === 'suspended') await ctx.resume();
    try { chordIdx = parseInt(sessionStorage.getItem(POS_KEY) || '0', 10) || 0; } catch {}
    master.gain.cancelScheduledValues(ctx.currentTime);
    master.gain.linearRampToValueAtTime(1, ctx.currentTime + 2.5);
    if (!timer) scheduleBar();
}

export function stop() {
    if (!ctx) return;
    master.gain.cancelScheduledValues(ctx.currentTime);
    master.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.8);
    clearTimeout(timer); timer = null;
}

/** Short synthesised cues, independent of the music toggle's volume. */
export function cue(kind) {
    if (!isOn()) return;
    ensureContext();
    const t = ctx.currentTime;
    const notes = { turn: [523, 659], found: [392, 523, 659], war: [196, 185], win: [523, 659, 784, 1047], achievement: [784, 988, 1175] }[kind] || [523];
    notes.forEach((f, i) => {
        const g = ctx.createGain(); g.gain.setValueAtTime(0.08, t + i * 0.12); g.gain.exponentialRampToValueAtTime(0.0005, t + i * 0.12 + 0.6);
        const o = ctx.createOscillator(); o.type = kind === 'war' ? 'sawtooth' : 'triangle'; o.frequency.value = f;
        o.connect(g); g.connect(ctx.destination); o.start(t + i * 0.12); o.stop(t + i * 0.12 + 0.7);
    });
}

/** Wire the nav toggle. Music can only begin after a user gesture. */
export function init() {
    const btn = document.getElementById('music-toggle');
    const body = document.body;
    setMood(body.dataset.mood || 'peace');
    const render = () => { if (btn) { btn.textContent = isOn() ? '🔊' : '🔇'; btn.title = isOn() ? 'Music on (click to mute)' : 'Music off (click to play)'; } };
    render();
    const kick = () => { if (isOn()) start(); document.removeEventListener('pointerdown', kick); document.removeEventListener('keydown', kick); };
    document.addEventListener('pointerdown', kick);
    document.addEventListener('keydown', kick);
    btn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const on = !isOn();
        try { localStorage.setItem(KEY, on ? 'on' : 'off'); } catch {}
        render();
        on ? start() : stop();
    });
    if (body.dataset.cue) setTimeout(() => cue(body.dataset.cue), 400);
}
