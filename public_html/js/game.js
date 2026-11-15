/*
 * NQ Mahjong — mahjong solitaire that runs entirely in the browser.
 * The server is only contacted to record a finished game for a signed-in player.
 *
 * Coordinates are in half-tile units: a tile at (x, y, z) covers x..x+2, y..y+2
 * on layer z, which lets upper layers sit across the seams of the layer below.
 */
(function () {
    'use strict';

    /* ---------- random ---------- */

    function mulberry32(a) {
        return function () {
            a |= 0; a = (a + 0x6D2B79F5) | 0;
            let t = Math.imul(a ^ (a >>> 15), 1 | a);
            t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }

    function shuffle(arr, rnd) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(rnd() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }

    /* ---------- layouts ---------- */

    function rect(out, x0, y0, cols, rows, z) {
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) out.push({ x: x0 + c * 2, y: y0 + r * 2, z });
        }
    }

    const LAYOUTS = {
        // 48 tiles: a low three-step mound.
        garden() {
            const t = [];
            rect(t, 0, 0, 8, 4, 0);
            rect(t, 2, 2, 6, 2, 1);
            rect(t, 4, 3, 4, 1, 2);
            return t;
        },
        // 88 tiles: two stepped towers joined by a walkway.
        bridge() {
            const t = [];
            for (const x of [0, 16]) {
                rect(t, x, 0, 4, 6, 0);
                rect(t, x + 2, 2, 2, 4, 1);
                rect(t, x + 2, 4, 2, 2, 2);
                rect(t, x + 2, 5, 2, 1, 3);
            }
            rect(t, 8, 4, 4, 2, 0);
            rect(t, 8, 5, 4, 1, 1);
            return t;
        },
        // 144 tiles: the traditional turtle.
        turtle() {
            const t = [];
            const rows = [[1, 12], [3, 10], [2, 11], [1, 12], [1, 12], [2, 11], [3, 10], [1, 12]];
            rows.forEach(([from, to], r) => rect(t, from * 2, r * 2, to - from + 1, 1, 0));
            t.push({ x: 0, y: 7, z: 0 }, { x: 26, y: 7, z: 0 }, { x: 28, y: 7, z: 0 });
            rect(t, 8, 2, 6, 6, 1);
            rect(t, 10, 4, 4, 4, 2);
            rect(t, 12, 6, 2, 2, 3);
            t.push({ x: 13, y: 7, z: 4 });
            return t;
        },
    };

    /* ---------- faces ---------- */

    const FACES = {};
    const GROUPS = []; // each group: two pairs of face ids that match each other

    (function buildFaces() {
        const add = (id, props) => { FACES[id] = Object.assign({ id, key: id }, props); return id; };
        const quad = (id) => GROUPS.push([[id, id], [id, id]]);
        const suits = { dot: 'Dots', bam: 'Bamboo', chr: 'Characters' };
        for (const suit of Object.keys(suits)) {
            for (let n = 1; n <= 9; n++) quad(add(suit + n, { suit, n, label: n + ' of ' + suits[suit] }));
        }
        ['East', 'South', 'West', 'North'].forEach((w, i) => quad(add('wind' + i, { suit: 'wind', n: i, label: w + ' wind' })));
        ['Red', 'Green', 'White'].forEach((d, i) => quad(add('drag' + i, { suit: 'drag', n: i, label: d + ' dragon' })));
        // Bonus tiles: one of each, any flower matches any flower, likewise seasons.
        const bonus = (suit, names) => {
            const ids = names.map((nm, i) => add(suit + i, { suit, n: i, key: suit, label: nm + ' (' + suit + ')' }));
            GROUPS.push([[ids[0], ids[1]], [ids[2], ids[3]]]);
        };
        bonus('flower', ['Plum', 'Orchid', 'Chrysanthemum', 'Bamboo']);
        bonus('season', ['Spring', 'Summer', 'Autumn', 'Winter']);
    })();

    function pickPairs(count, rnd) {
        const pairs = [];
        for (const g of shuffle(GROUPS.slice(), rnd)) pairs.push(g[0], g[1]);
        return shuffle(pairs.slice(0, count), rnd);
    }

    /* ---------- rules ---------- */

    function isFree(t, live) {
        let left = false, right = false;
        for (const o of live) {
            if (o === t) continue;
            const dy = Math.abs(o.y - t.y);
            if (dy >= 2) continue;
            const dx = o.x - t.x;
            if (o.z === t.z + 1 && Math.abs(dx) < 2) return false;
            if (o.z === t.z) {
                if (dx === -2) left = true;
                else if (dx === 2) right = true;
            }
        }
        return !(left && right);
    }

    const freeTiles = (live) => live.filter((t) => isFree(t, live));

    /** All currently playable pairs. */
    function availablePairs(live) {
        const byKey = {};
        for (const t of freeTiles(live)) (byKey[FACES[t.face].key] = byKey[FACES[t.face].key] || []).push(t);
        const pairs = [];
        for (const k in byKey) {
            const g = byKey[k];
            for (let i = 0; i < g.length; i++) for (let j = i + 1; j < g.length; j++) pairs.push([g[i], g[j]]);
        }
        return pairs;
    }

    /**
     * Play the board backwards: repeatedly lift two random free tiles. Giving
     * each lifted pair matching faces guarantees at least one winning line.
     */
    function removalOrder(tiles, rnd) {
        for (let attempt = 0; attempt < 400; attempt++) {
            let live = tiles.slice();
            const order = [];
            while (live.length) {
                const free = freeTiles(live);
                if (free.length < 2) break;
                shuffle(free, rnd);
                const a = free[0], b = free[1];
                order.push([a, b]);
                live = live.filter((t) => t !== a && t !== b);
            }
            if (!live.length) return order;
        }
        return null;
    }

    function deal(layout, seed) {
        const rnd = mulberry32(seed);
        const tiles = LAYOUTS[layout]().map((p, id) => ({ id, x: p.x, y: p.y, z: p.z, face: null, removed: false }));
        const order = removalOrder(tiles, rnd);
        const pairs = pickPairs(tiles.length / 2, rnd);
        order.forEach(([a, b], i) => { a.face = pairs[i][0]; b.face = pairs[i][1]; });
        return tiles;
    }

    /** Re-deal the faces still on the board. Returns false if the shape itself is dead. */
    function reshuffle(live, rnd) {
        const byKey = {};
        for (const t of live) (byKey[FACES[t.face].key] = byKey[FACES[t.face].key] || []).push(t.face);
        const pairs = [];
        for (const k in byKey) {
            shuffle(byKey[k], rnd);
            for (let i = 0; i + 1 < byKey[k].length; i += 2) pairs.push([byKey[k][i], byKey[k][i + 1]]);
        }
        shuffle(pairs, rnd);
        const order = removalOrder(live, rnd);
        if (!order) return false;
        order.forEach(([a, b], i) => { a.face = pairs[i][0]; b.face = pairs[i][1]; });
        return true;
    }

    const api = { LAYOUTS, FACES, deal, reshuffle, isFree, availablePairs, mulberry32 };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (typeof document === 'undefined') return;

    /* ====================================================================
     * Browser game
     * ==================================================================== */

    const CFG = window.NQ;
    const RULES = CFG.scoring;
    const $ = (id) => document.getElementById(id);
    const store = {
        get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } },
        del(k) { try { localStorage.removeItem(k); } catch (e) { /* private mode */ } },
    };
    const SAVE = 'nq.save', BEST = 'nq.best', PENDING = 'nq.pending', LAYOUT = 'nq.layout';

    const el = {
        board: $('board'), wrap: $('board-wrap'), notice: $('notice'), toast: $('toast'),
        score: $('stat-score'), time: $('stat-time'), tiles: $('stat-tiles'), moves: $('stat-moves'),
        combo: $('stat-combo'), comboValue: $('stat-combo-value'), layout: $('layout-select'),
        best: $('best-line'), dialog: $('win-dialog'),
    };

    let G = null;        // game state
    let selected = null; // tile id
    let hinted = [];     // tile ids
    let tickTimer = null;

    const live = () => G.tiles.filter((t) => !t.removed);
    const clock = (s) => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    const fmt = (n) => n.toLocaleString('en-US');
    const elapsedMs = () => G.elapsed + (G.runningSince ? Date.now() - G.runningSince : 0);
    const multiplier = (streak) => Math.min(1 + RULES.combo_step * streak, RULES.combo_max);

    /* ---------- tile artwork ---------- */

    const INK = { r: '#c0392b', g: '#1d7a46', b: '#1f4e9c', k: '#1b1b1b' };
    const PIPS = {
        1: [[30, 40]],
        2: [[30, 22], [30, 58]],
        3: [[15, 17], [30, 40], [45, 63]],
        4: [[18, 22], [42, 22], [18, 58], [42, 58]],
        5: [[17, 19], [43, 19], [30, 40], [17, 61], [43, 61]],
        6: [[18, 16], [42, 16], [18, 40], [42, 40], [18, 64], [42, 64]],
        7: [[14, 11], [30, 18], [46, 25], [18, 46], [42, 46], [18, 67], [42, 67]],
        8: [[18, 11], [42, 11], [18, 30], [42, 30], [18, 50], [42, 50], [18, 69], [42, 69]],
        9: [[14, 16], [30, 16], [46, 16], [14, 40], [30, 40], [46, 40], [14, 64], [30, 64], [46, 64]],
    };
    const CJK = 'font-family="\'Songti SC\',\'Noto Serif CJK TC\',\'Source Han Serif TC\',\'PingFang TC\',\'Microsoft JhengHei\',serif" font-weight="700" text-anchor="middle"';
    const text = (ch, y, size, fill) => `<text x="30" y="${y}" font-size="${size}" fill="${fill}" ${CJK}>${ch}</text>`;
    const corner = (ch, fill) => `<text x="5" y="14" font-size="12" fill="${fill}" font-family="system-ui,sans-serif" font-weight="700">${ch}</text>`;
    const artCache = {};

    function art(faceId) {
        if (artCache[faceId]) return artCache[faceId];
        const f = FACES[faceId];
        let g = '';
        if (f.suit === 'dot') {
            const r = f.n === 1 ? 17 : f.n <= 2 ? 12 : f.n <= 5 ? 9.5 : f.n === 6 ? 8.5 : 7;
            PIPS[f.n].forEach(([x, y], i) => {
                const c = f.n === 1 ? INK.r : [INK.b, INK.r, INK.g][i % 3];
                g += `<circle cx="${x}" cy="${y}" r="${r}" fill="${c}"/>`
                   + `<circle cx="${x}" cy="${y}" r="${r * 0.55}" fill="none" stroke="#fff" stroke-width="${r * 0.18}"/>`
                   + `<circle cx="${x}" cy="${y}" r="${r * 0.2}" fill="#fff"/>`;
            });
        } else if (f.suit === 'bam') {
            const h = f.n === 1 ? 52 : f.n <= 5 ? 24 : f.n === 6 || f.n === 9 ? 19 : 15;
            const w = f.n === 1 ? 12 : 6.5;
            const mid = { 1: [0], 5: [2], 7: [0, 1, 2], 9: [1, 4, 7] }[f.n] || [];
            PIPS[f.n].forEach(([x, y], i) => {
                const c = mid.includes(i) ? INK.r : INK.g;
                g += `<rect x="${x - w / 2}" y="${y - h / 2}" width="${w}" height="${h}" rx="${w / 2}" fill="${c}"/>`
                   + `<rect x="${x - w / 2 - 1}" y="${y - 1.2}" width="${w + 2}" height="2.4" rx="1.2" fill="${c}"/>`
                   + `<rect x="${x - 0.6}" y="${y - h / 2 + 2.5}" width="1.2" height="${h - 5}" fill="#fff" opacity=".55"/>`;
            });
        } else if (f.suit === 'chr') {
            g = corner(f.n, INK.k) + text('一二三四五六七八九'[f.n - 1], 36, 30, INK.k) + text('萬', 70, 31, INK.r);
        } else if (f.suit === 'wind') {
            g = corner('ESWN'[f.n], INK.k) + text('東南西北'[f.n], 58, 46, INK.k);
        } else if (f.suit === 'drag') {
            if (f.n === 0) g = text('中', 58, 48, INK.r);
            else if (f.n === 1) g = text('發', 58, 46, INK.g);
            else g = `<rect x="10" y="12" width="40" height="56" rx="5" fill="none" stroke="${INK.b}" stroke-width="4"/>`
                   + `<rect x="17" y="19" width="26" height="42" rx="2" fill="none" stroke="${INK.b}" stroke-width="1.5"/>`;
        } else if (f.suit === 'flower') {
            g = corner(f.n + 1, INK.r) + text('梅蘭菊竹'[f.n], 52, 38, INK.r)
              + `<text x="30" y="72" font-size="9" fill="${INK.r}" text-anchor="middle" font-family="system-ui,sans-serif" font-weight="700">FLOWER</text>`;
        } else {
            g = corner(f.n + 1, INK.b) + text('春夏秋冬'[f.n], 52, 38, INK.b)
              + `<text x="30" y="72" font-size="9" fill="${INK.b}" text-anchor="middle" font-family="system-ui,sans-serif" font-weight="700">SEASON</text>`;
        }
        return (artCache[faceId] = `<svg viewBox="0 0 60 80" aria-hidden="true" focusable="false">${g}</svg>`);
    }

    /* ---------- rendering ---------- */

    function render() {
        const tiles = live();
        const all = G.tiles;
        const maxX = Math.max(...all.map((t) => t.x)) + 2;
        const maxY = Math.max(...all.map((t) => t.y)) + 2;
        const maxZ = Math.max(...all.map((t) => t.z));
        const DEPTH = 0.26; // layer offset, in units
        const availW = el.wrap.clientWidth - 12, availH = el.wrap.clientHeight - 12;
        const u = Math.max(9, Math.min(36, availW / (maxX + (maxZ + 1) * DEPTH), availH / (maxY * 4 / 3 + (maxZ + 1) * DEPTH)));
        const v = u * 4 / 3, d = u * DEPTH;

        el.board.style.width = (maxX * u + (maxZ + 1) * d) + 'px';
        el.board.style.height = (maxY * v + (maxZ + 1) * d) + 'px';
        el.board.style.setProperty('--d', d.toFixed(2) + 'px');

        const frag = document.createDocumentFragment();
        for (const t of tiles) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'tile' + (t.id === selected ? ' selected' : '') + (hinted.includes(t.id) ? ' hint' : '');
            b.dataset.id = t.id;
            b.setAttribute('aria-label', FACES[t.face].label + (isFree(t, tiles) ? '' : ', blocked'));
            b.style.cssText = `left:${t.x * u + (maxZ - t.z) * d}px;top:${t.y * v + (maxZ - t.z) * d}px;`
                + `width:${2 * u}px;height:${2 * v}px;z-index:${t.z * 10000 + t.y * 100 + t.x};`;
            b.innerHTML = art(t.face);
            frag.appendChild(b);
        }
        el.board.replaceChildren(frag);
        renderHud(tiles);
    }

    function renderHud(tiles) {
        tiles = tiles || live();
        const moves = availablePairs(tiles).length;
        el.score.textContent = fmt(G.score);
        el.tiles.textContent = tiles.length;
        el.moves.textContent = moves;
        renderTime();
        const streakLive = G.streak > 0 && Date.now() - G.lastMatchAt <= RULES.combo_window * 1000;
        el.combo.hidden = !streakLive;
        el.comboValue.textContent = '×' + multiplier(G.streak);
        $('btn-undo').disabled = !G.moves.length || G.done;
        $('btn-hint').disabled = $('btn-shuffle').disabled = G.done || tiles.length === 0;

        if (!G.done && tiles.length && moves === 0) {
            notice('No moves left. Shuffle the remaining tiles (−' + RULES.shuffle + ') or undo your last pair.');
        } else if (el.notice.dataset.kind === 'stuck') {
            el.notice.hidden = true;
        }
    }

    function renderTime() {
        el.time.textContent = clock(Math.floor(elapsedMs() / 1000));
    }

    function notice(msg) {
        el.notice.textContent = msg;
        el.notice.dataset.kind = 'stuck';
        el.notice.hidden = false;
    }

    let toastTimer = null;
    function toast(msg) {
        el.toast.textContent = msg;
        el.toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.toast.hidden = true; }, 4000);
    }

    function renderBest() {
        const best = (store.get(BEST) || {})[G.layout];
        el.best.textContent = best
            ? 'Your best on ' + CFG.layouts[G.layout].name + ': ' + fmt(best.score) + ' in ' + clock(best.duration) + '.'
            : '';
    }

    /* ---------- clock ---------- */

    function startClock() {
        if (G.done || G.runningSince) return;
        G.runningSince = Date.now();
    }

    function pauseClock() {
        if (!G.runningSince) return;
        G.elapsed += Date.now() - G.runningSince;
        G.runningSince = 0;
    }

    /* ---------- persistence ---------- */

    function save() {
        if (G.done) return store.del(SAVE);
        store.set(SAVE, {
            v: 1, layout: G.layout, seed: G.seed,
            faces: G.tiles.map((t) => t.face),
            removed: G.tiles.filter((t) => t.removed).map((t) => t.id),
            score: G.score, elapsed: elapsedMs(), moves: G.moves,
            hints: G.hints, shuffles: G.shuffles, undos: G.undos,
        });
    }

    function restore() {
        const s = store.get(SAVE);
        if (!s || s.v !== 1 || !LAYOUTS[s.layout] || !CFG.layouts[s.layout]) return false;
        const tiles = LAYOUTS[s.layout]().map((p, id) => ({ id, x: p.x, y: p.y, z: p.z, face: s.faces[id], removed: false }));
        if (tiles.some((t) => !FACES[t.face])) return false;
        for (const id of s.removed) if (tiles[id]) tiles[id].removed = true;
        G = {
            layout: s.layout, seed: s.seed, tiles, score: s.score | 0, elapsed: +s.elapsed || 0,
            runningSince: 0, streak: 0, lastMatchAt: 0, moves: Array.isArray(s.moves) ? s.moves : [],
            hints: s.hints | 0, shuffles: s.shuffles | 0, undos: s.undos | 0, done: false,
        };
        return true;
    }

    /* ---------- game flow ---------- */

    function newGame(layout, seed) {
        G = {
            layout, seed: seed === undefined ? (Math.random() * 0xFFFFFFFF) >>> 0 : seed,
            tiles: null, score: 0, elapsed: 0, runningSince: 0, streak: 0, lastMatchAt: 0,
            moves: [], hints: 0, shuffles: 0, undos: 0, done: false,
        };
        G.tiles = deal(layout, G.seed);
        selected = null;
        hinted = [];
        el.notice.hidden = true;
        el.layout.value = layout;
        store.set(LAYOUT, layout);
        save();
        render();
        renderBest();
    }

    /** Ask before throwing away a game that is under way. */
    function confirmAbandon() {
        return G.done || !G.moves.length || window.confirm('Abandon the current game?');
    }

    function onTile(id) {
        if (G.done) return;
        const tiles = live();
        const t = G.tiles[id];
        const node = el.board.querySelector('[data-id="' + id + '"]');
        if (!isFree(t, tiles)) {
            if (node) { node.classList.remove('shake'); void node.offsetWidth; node.classList.add('shake'); }
            return;
        }
        startClock();
        hinted = [];
        if (selected === id) { selected = null; return render(); }
        const other = selected === null ? null : G.tiles[selected];
        if (other && FACES[other.face].key === FACES[t.face].key) return match(other, t);
        selected = id;
        render();
    }

    function match(a, b) {
        const now = Date.now();
        G.streak = G.lastMatchAt && now - G.lastMatchAt <= RULES.combo_window * 1000 ? G.streak + 1 : 0;
        G.lastMatchAt = now;
        G.moves.push([a.id, b.id, G.score]);
        G.score += Math.round(RULES.pair * multiplier(G.streak));
        a.removed = b.removed = true;
        selected = null;

        // Fade the pair out, then redraw from state.
        for (const t of [a, b]) {
            const node = el.board.querySelector('[data-id="' + t.id + '"]');
            if (node) node.classList.add('gone');
        }
        renderHud();
        setTimeout(render, 170);

        if (live().length === 0) win();
        else save();
    }

    function undo() {
        if (G.done || !G.moves.length) return;
        const [a, b, prevScore] = G.moves.pop();
        G.tiles[a].removed = G.tiles[b].removed = false;
        G.score = Math.max(0, prevScore - RULES.undo);
        G.streak = 0;
        G.undos++;
        selected = null;
        hinted = [];
        save();
        render();
    }

    function hint() {
        if (G.done) return;
        const pairs = availablePairs(live());
        if (!pairs.length) return renderHud();
        startClock();
        const pair = pairs[Math.floor(Math.random() * pairs.length)];
        hinted = [pair[0].id, pair[1].id];
        selected = null;
        G.hints++;
        G.score = Math.max(0, G.score - RULES.hint);
        save();
        render();
    }

    function shuffleBoard() {
        if (G.done) return;
        const tiles = live();
        if (!tiles.length) return;
        if (!reshuffle(tiles, Math.random)) {
            return notice('These tiles are stacked so that no shuffle can free them. Undo a few pairs or start a new game.');
        }
        startClock();
        G.shuffles++;
        G.score = Math.max(0, G.score - RULES.shuffle);
        G.streak = 0;
        // Earlier pairs can no longer be taken back onto a reshuffled board.
        G.moves = [];
        selected = null;
        hinted = [];
        save();
        render();
    }

    function win() {
        pauseClock();
        G.done = true;
        const duration = Math.max(1, Math.round(G.elapsed / 1000));
        const par = CFG.layouts[G.layout].par;
        const bonus = Math.max(0, par - duration) * RULES.time_bonus;
        const base = G.score;
        G.score = base + bonus;
        store.del(SAVE);

        const bests = store.get(BEST) || {};
        const isBest = !bests[G.layout] || G.score > bests[G.layout].score;
        if (isBest) { bests[G.layout] = { score: G.score, duration }; store.set(BEST, bests); }
        renderBest();

        $('win-score').textContent = fmt(G.score);
        $('win-breakdown').innerHTML = [
            ['Layout', CFG.layouts[G.layout].name],
            ['Tiles & combos', fmt(base)],
            ['Time ' + clock(duration) + ' (par ' + clock(par) + ')', '+' + fmt(bonus)],
            ['Hints / shuffles / undos', G.hints + ' / ' + G.shuffles + ' / ' + G.undos],
            ['Best on this device', fmt(bests[G.layout].score) + (isBest ? ' — new best!' : '')],
        ].map(([k, val]) => '<div><dt>' + k + '</dt><dd>' + val + '</dd></div>').join('');

        const payload = {
            layout: G.layout, score: G.score, duration,
            hints: G.hints, shuffles: G.shuffles, undos: G.undos,
        };
        const status = $('win-save');
        $('win-guest').hidden = !!CFG.user;
        if (CFG.user) {
            status.textContent = 'Saving your score…';
            submit(payload).then((res) => { status.textContent = res.message; });
        } else {
            // Held on this device and sent once the player signs in.
            store.set(PENDING, payload);
            status.textContent = 'You are playing as a guest. Sign in and this score goes on the leaderboard.';
        }
        $('win-board').href = CFG.urls.leaderboard + '?layout=' + G.layout;
        renderHud();
        setTimeout(() => el.dialog.showModal(), 350);
    }

    /* ---------- server ---------- */

    function submit(payload) {
        return fetch(CFG.urls.scores, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(payload),
        }).then((r) => {
            if (r.ok) {
                return r.json().then((j) => {
                    store.del(PENDING);
                    return { ok: true, message: j.personal_best ? 'Score saved — a new personal best!' : 'Score saved. Your best is ' + fmt(j.best) + '.' };
                });
            }
            if (r.status === 422) {
                store.del(PENDING);
                return { ok: false, message: 'The server rejected this score.' };
            }
            // Signed out or session expired: keep it and retry on the next page load.
            store.set(PENDING, payload);
            return { ok: false, message: 'Could not save right now. Reload the page to retry.' };
        }).catch(() => {
            store.set(PENDING, payload);
            return { ok: false, message: 'You appear to be offline. The score will be sent next time you open the game.' };
        });
    }

    /* ---------- wiring ---------- */

    el.board.addEventListener('click', (e) => {
        const node = e.target.closest('.tile');
        if (node) onTile(+node.dataset.id);
    });
    $('btn-hint').addEventListener('click', hint);
    $('btn-undo').addEventListener('click', undo);
    $('btn-shuffle').addEventListener('click', shuffleBoard);
    $('btn-restart').addEventListener('click', () => { if (confirmAbandon()) newGame(G.layout, G.seed); });
    $('btn-new').addEventListener('click', () => { if (confirmAbandon()) newGame(el.layout.value); });
    $('win-new').addEventListener('click', () => { el.dialog.close(); newGame(el.layout.value); });
    el.layout.addEventListener('change', () => {
        if (confirmAbandon()) newGame(el.layout.value);
        else el.layout.value = G.layout;
    });

    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, select, textarea') || e.metaKey || e.ctrlKey || e.altKey || el.dialog.open) return;
        const k = e.key.toLowerCase();
        if (k === 'h') hint();
        else if (k === 'u') undo();
    });

    // The clock only runs while the game is actually on screen.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { pauseClock(); save(); } else if (G.moves.length && !G.done) startClock();
    });
    window.addEventListener('pagehide', () => { pauseClock(); save(); });

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(render, 80);
    });

    tickTimer = setInterval(() => {
        if (!G.runningSince) return;
        renderTime();
        if (!el.combo.hidden && Date.now() - G.lastMatchAt > RULES.combo_window * 1000) el.combo.hidden = true;
    }, 250);

    /* ---------- boot ---------- */

    if (restore()) {
        el.layout.value = G.layout;
        render();
        renderBest();
    } else {
        const preferred = store.get(LAYOUT);
        newGame(CFG.layouts[preferred] ? preferred : 'turtle');
    }

    const pending = store.get(PENDING);
    if (pending && CFG.user) submit(pending).then((res) => toast(res.message));
})();
