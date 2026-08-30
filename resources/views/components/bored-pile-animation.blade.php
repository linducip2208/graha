@props(['fill' => false])
<div {{ $attributes->merge(['class' => 'bored-pile-canvas-scene'.($fill ? ' bp-scene-fill' : '')]) }} data-bored-pile-scene role="img" aria-label="Animasi lanskap lokasi konstruksi bored pile: rig mengebor berpindah antar titik, cage besi turun, dan beton dipompa melalui tremie">
    <canvas data-bored-pile-canvas aria-hidden="true"></canvas>
    <div class="bored-pile-canvas-hud" aria-hidden="true"><span data-bp-phase>DRILLING</span><span data-bp-depth>DEPTH 00.0 M</span></div>
    <div class="bored-pile-canvas-caption" aria-hidden="true">LIVE FOUNDATION SEQUENCE <i></i></div>
    <button type="button" class="bored-pile-motion-optin" data-bp-motion-optin hidden>Aktifkan animasi</button>
</div>
<script>
(() => {
    const scene = document.querySelector('[data-bored-pile-scene]:not([data-bp-ready])');
    if (!scene) return;
    scene.dataset.bpReady = '1';
    const canvas = scene.querySelector('[data-bored-pile-canvas]');
    const ctx = canvas.getContext('2d');
    const phaseEl = scene.querySelector('[data-bp-phase]');
    const depthEl = scene.querySelector('[data-bp-depth]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let motion = !reduced;
    const optin = scene.querySelector('[data-bp-motion-optin]');
    if (reduced && optin) optin.hidden = false;
    let W = 0, H = 0, dpr = 1, start = performance.now(), raf = 0;
    const pointer = { x: -1, y: -1, pulse: 0, active: false };
    const resize = () => {
        const box = scene.getBoundingClientRect();
        dpr = Math.min(window.devicePixelRatio || 1, 1.75);
        W = box.width; H = box.height;
        canvas.width = Math.max(1, Math.round(W * dpr)); canvas.height = Math.max(1, Math.round(H * dpr));
        canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    const rnd = (n) => { const x = Math.sin(n * 127.1 + 311.7) * 43758.5453; return x - Math.floor(x); };
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
    const seg = (a, b, u) => clamp((u - a) / (b - a), 0, 1);
    const ease = (u) => u * u * (3 - 2 * u);
    const stroke = (x1, y1, x2, y2, c, w = 1, dash = []) => { ctx.beginPath(); ctx.setLineDash(dash); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.strokeStyle = c; ctx.lineWidth = w; ctx.stroke(); ctx.setLineDash([]); };
    const SPAN = 14, PILES = 4, LOOP = SPAN * PILES;
    const D_TARGET = [21.5, 24, 26.5, 24];
    const TRAVEL_END = 1.4, CASING_END = 2.2, DRILL_END = 6.6, BITUP_END = 7.2, CAGE_END = 9.8, POUR_END = 13.3;
    const PHASES = [['RIG TRAVEL', 0], ['CASING', TRAVEL_END], ['DRILLING', CASING_END], ['BIT OUT', DRILL_END], ['REBAR CAGE', BITUP_END], ['TREMIE POUR', CAGE_END], ['COMPLETED', POUR_END]];
    const draw = (now) => {
        if (!document.contains(scene)) { cancelAnimationFrame(raf); return; }
        const tRaw = reduced ? 24.6 : (now - start) / 1000;
        const t = ((tRaw % LOOP) + LOOP) % LOOP;
        const idx = Math.min(PILES - 1, Math.floor(t / SPAN));
        const u = t - idx * SPAN;
        const u1 = clamp(H / 340, .6, 1.4);
        const platformY = Math.round(H * .52), soilH = H - platformY;
        const shaftW = Math.max(15, H * .072);
        const holeDepth = soilH * .86, holeBottom = platformY + holeDepth;
        const casingH = holeDepth * .14;
        const pileX = [.15, .37, .585, .795].map((f) => f * W);
        const cageW = shaftW * .58;
        let depthM = 0, phaseName = 'RIG TRAVEL', pourP = 0;
        for (const [nm, from] of PHASES) if (u >= from) phaseName = nm;
        const drillP = seg(CASING_END, DRILL_END, u);
        const cageP = ease(seg(BITUP_END, CAGE_END, u));
        pourP = ease(seg(CAGE_END, POUR_END, u));
        depthM = D_TARGET[idx] * drillP;

        const sky = ctx.createLinearGradient(0, 0, 0, platformY);
        sky.addColorStop(0, '#040a18'); sky.addColorStop(.55, '#0a1c33'); sky.addColorStop(1, '#2a4560');
        ctx.fillStyle = sky; ctx.fillRect(0, 0, W, platformY);
        if (!reduced) { for (let i = 0; i < 34; i++) { ctx.globalAlpha = .18 + .2 * Math.abs(Math.sin(t * 1.2 + i * 1.7)); ctx.fillStyle = '#e0f2fe'; ctx.fillRect(rnd(i) * W, rnd(i + 61) * platformY * .5, 1.4, 1.4); } ctx.globalAlpha = 1; }
        let sg = ctx.createRadialGradient(W * .8, platformY * .84, 0, W * .8, platformY * .84, 130 * u1);
        sg.addColorStop(0, 'rgba(251,191,36,.34)'); sg.addColorStop(1, 'rgba(251,191,36,0)');
        ctx.fillStyle = sg; ctx.fillRect(W * .8 - 140 * u1, platformY * .84 - 140 * u1, 280 * u1, 280 * u1);
        ctx.fillStyle = 'rgba(253,230,138,.85)'; ctx.beginPath(); ctx.arc(W * .8, platformY * .84, 9 * u1, 0, Math.PI * 2); ctx.fill();
        const cloudBanks = [[.16, 9, .12], [.32, 15, .17], [.46, 24, .22]];
        for (const [fy, spd, al] of cloudBanks) {
            const cy = platformY * fy, cw = (W + 260 * u1);
            for (let c = 0; c < 3; c++) {
                const cx = (((c * 430 + fy * 900) * u1 + t * spd * u1) % cw) - 130 * u1;
                ctx.fillStyle = 'rgba(148,163,184,' + al + ')';
                for (let k = 0; k < 4; k++) { ctx.beginPath(); ctx.ellipse(cx + k * 20 * u1, cy + (k % 2) * 4 * u1, (16 - Math.abs(k - 1.5) * 4) * u1, 8 * u1, 0, 0, Math.PI * 2); ctx.fill(); }
            }
        }
        ctx.fillStyle = '#0c1e33';
        for (let bx = 0, bi = 0; bx < W; bi++) { const bw = (22 + rnd(bi * 7) * 52) * u1, bh = (14 + rnd(bi * 13) * platformY * .22); ctx.fillRect(bx, platformY - bh, bw + 2, bh); bx += bw + 8 * u1; }
        ctx.fillStyle = '#081527';
        for (let bx = 0, bi = 40; bx < W; bi++) { const bw = (30 + rnd(bi * 5) * 66) * u1, bh = (26 + rnd(bi * 11) * platformY * .3); ctx.fillRect(bx, platformY - bh, bw + 2, bh); for (let wl = 0; wl < 4; wl++) { if (rnd(bi * 31 + wl) > .72) { ctx.fillStyle = 'rgba(251,191,36,.4)'; ctx.fillRect(bx + 6 + rnd(bi + wl) * (bw - 10), platformY - bh + 5 + rnd(bi * 3 + wl) * (bh - 10), 2, 2); ctx.fillStyle = '#081527'; } } bx += bw + 12 * u1; }
        const crX = W * .68, crTop = platformY - platformY * .8;
        stroke(crX, platformY, crX, crTop, '#10263f', 3 * u1); stroke(crX - 8 * u1, platformY, crX, crTop - 16 * u1, '#10263f', 1.5 * u1); stroke(crX + 8 * u1, platformY, crX, crTop - 16 * u1, '#10263f', 1.5 * u1);
        const jibA = Math.sin(t * .12) * .3 - .25;
        stroke(crX, crTop, crX + Math.cos(jibA) * W * .11, crTop + Math.sin(jibA) * W * .05, '#10263f', 2 * u1);
        stroke(crX, crTop, crX - Math.cos(jibA + 2.6) * W * .05, crTop + Math.sin(jibA + 2.6) * W * .03, '#10263f', 2 * u1);
        stroke(crX, platformY, crX - Math.cos(jibA + 2.6) * W * .05, crTop + Math.sin(jibA + 2.6) * W * .03, '#10263f', 1 * u1);
        stroke(crX + Math.cos(jibA) * W * .075, crTop + Math.sin(jibA) * W * .034, crX + Math.cos(jibA) * W * .075, crTop + 46 * u1, '#10263f', 1 * u1);
        ctx.fillStyle = '#10263f'; ctx.fillRect(crX + Math.cos(jibA) * W * .075 - 3 * u1, crTop + 46 * u1, 6 * u1, 5 * u1);
        for (let b = 0; b < 3; b++) { const bx = ((t * 26 + b * 300) % (W + 90)) - 45, by = platformY * (.22 + .07 * b) + Math.sin(t * 2 + b * 2) * 7 * u1; ctx.strokeStyle = 'rgba(203,213,225,.4)'; ctx.lineWidth = 1.2 * u1; ctx.beginPath(); ctx.moveTo(bx - 5 * u1, by); ctx.quadraticCurveTo(bx - 2 * u1, by - 3 * u1 - Math.sin(t * 9 + b) * 2, bx, by); ctx.quadraticCurveTo(bx + 2 * u1, by - 3 * u1 - Math.sin(t * 9 + b) * 2, bx + 5 * u1, by); ctx.stroke(); }

        const bands = [[.16, '#241f18', 'URUGAN'], [.18, '#2b241b', 'LEMPUNG KERAS'], [.2, '#262622', 'PASIR'], [.22, '#221f23', 'LANAU'], [.24, '#1d1a22', 'BATULEMPUNG']];
        let by = platformY;
        bands.forEach(([f, c, label], bi2) => {
            const bh = f * soilH;
            ctx.fillStyle = c; ctx.fillRect(0, by, W, bh + 1);
            for (let k = 0; k < W / 12; k++) { ctx.fillStyle = 'rgba(203,213,225,.05)'; ctx.fillRect(rnd(bi2 * 97 + k) * W, by + rnd(k * 31 + bi2 * 7) * bh, 1.6, 1.6); }
            if (bi2 === 4) { ctx.strokeStyle = 'rgba(203,213,225,.05)'; ctx.lineWidth = 1; for (let hx = -bh; hx < W; hx += 16) { ctx.beginPath(); ctx.moveTo(hx, by + bh); ctx.lineTo(hx + bh, by); ctx.stroke(); } }
            stroke(0, by, W, by, 'rgba(125,211,252,.12)', 1);
            ctx.fillStyle = 'rgba(148,163,184,.6)'; ctx.font = '600 ' + Math.round(8 * u1) + 'px ui-monospace, monospace'; ctx.textAlign = 'right';
            ctx.fillText(label, W - 10 * u1, by + bh * .5 + 3); ctx.textAlign = 'left';
            by += bh;
        });
        const wty = platformY + soilH * .3;
        ctx.strokeStyle = 'rgba(56,189,248,.4)'; ctx.lineWidth = 1.2; ctx.setLineDash([6, 5]); ctx.beginPath();
        for (let wx = 0; wx <= W; wx += 8) { const wy = wty + Math.sin(wx * .04 + t * 1.8) * 1.6; wx === 0 ? ctx.moveTo(wx, wy) : ctx.lineTo(wx, wy); }
        ctx.stroke(); ctx.setLineDash([]);
        ctx.fillStyle = 'rgba(56,189,248,.55)'; ctx.font = '700 ' + Math.round(8 * u1) + 'px ui-monospace, monospace';
        ctx.fillText('MTA ' + '\u25BC', 10 * u1, wty - 5 * u1);

        ctx.fillStyle = '#45413a'; ctx.fillRect(0, platformY, W, 6 * u1);
        stroke(0, platformY, W, platformY, 'rgba(248,250,252,.16)', 1);
        for (let fx = 20 * u1; fx < W; fx += 64 * u1) { ctx.fillStyle = 'rgba(148,163,184,.3)'; ctx.fillRect(fx, platformY - 9 * u1, 2, 9 * u1); ctx.fillStyle = 'rgba(251,146,60,.35)'; ctx.fillRect(fx - 1, platformY - 10 * u1, 4, 2); }

        const lightTower = (lx) => {
            stroke(lx, platformY, lx, platformY - 62 * u1, '#5b6b7d', 2.5 * u1);
            stroke(lx - 9 * u1, platformY - 62 * u1, lx + 9 * u1, platformY - 62 * u1, '#5b6b7d', 2 * u1);
            const lg = ctx.createLinearGradient(lx, platformY - 62 * u1, lx, platformY + 40 * u1);
            lg.addColorStop(0, 'rgba(253,224,71,.1)'); lg.addColorStop(1, 'rgba(253,224,71,0)');
            ctx.fillStyle = lg; ctx.beginPath(); ctx.moveTo(lx - 9 * u1, platformY - 60 * u1); ctx.lineTo(lx + 9 * u1, platformY - 60 * u1); ctx.lineTo(lx + 42 * u1, platformY + 46 * u1); ctx.lineTo(lx - 42 * u1, platformY + 46 * u1); ctx.closePath(); ctx.fill();
            for (const dx of [-8, 8]) { ctx.fillStyle = '#fde68a'; ctx.beginPath(); ctx.arc(lx + dx * u1, platformY - 63 * u1, 2.4 * u1, 0, Math.PI * 2); ctx.fill(); }
        };
        lightTower(W * .035); lightTower(W * .965);

        const office = { x: W * .07, y: platformY - 26 * u1, w: 66 * u1, h: 26 * u1 };
        ctx.fillStyle = '#25384d'; ctx.fillRect(office.x, office.y, office.w, office.h);
        ctx.fillStyle = '#1b2c3e'; ctx.fillRect(office.x, office.y, office.w, 4 * u1);
        ctx.fillStyle = 'rgba(253,224,71,.75)'; ctx.fillRect(office.x + 8 * u1, office.y + 9 * u1, 12 * u1, 8 * u1); ctx.fillRect(office.x + 28 * u1, office.y + 9 * u1, 12 * u1, 8 * u1);
        stroke(office.x + office.w - 14 * u1, office.y + office.h, office.x + office.w - 6 * u1, platformY, '#5b6b7d', 1.5 * u1);
        const spareCage = { x: W * .475, y: platformY - 9 * u1 };
        ctx.strokeStyle = 'rgba(251,191,36,.65)'; ctx.lineWidth = 1.4 * u1;
        for (let r = 0; r < 5; r++) { ctx.beginPath(); ctx.ellipse(spareCage.x + r * 9 * u1, spareCage.y, 3 * u1, 8 * u1, 0, 0, Math.PI * 2); ctx.stroke(); }
        stroke(spareCage.x - 4 * u1, spareCage.y, spareCage.x + 40 * u1, spareCage.y, 'rgba(251,191,36,.5)', 1.2 * u1);

        const drawPile = (i) => {
            const px = pileX[i];
            let state = 'done', dp = 1, cp = 1, pp = 1;
            if (i === idx) {
                if (u < DRILL_END) { state = 'drill'; dp = drillP; cp = 0; pp = 0; }
                else if (u < CAGE_END) { state = 'cage'; dp = 1; cp = cageP; pp = 0; }
                else if (u < POUR_END) { state = 'pour'; dp = 1; cp = 1; pp = pourP; }
                else { state = 'done'; dp = 1; cp = 1; pp = 1; }
            }
            const drillDepthPx = holeDepth * .9;
            const heapP = i < idx ? 1 : i === idx ? drillP : 0;
            if (heapP > 0) {
                const hx = px + shaftW * 1.7, hw = shaftW * 1.5 * heapP, hh = 11 * u1 * heapP;
                ctx.fillStyle = '#37301f'; ctx.beginPath(); ctx.ellipse(hx, platformY + 1, hw, hh, 0, Math.PI, 0); ctx.fill();
                for (let k = 0; k < 9; k++) { ctx.fillStyle = 'rgba(148,163,184,.16)'; ctx.fillRect(hx - hw + rnd(i * 41 + k) * hw * 2, platformY - rnd(k * 17 + i) * hh, 1.6, 1.6); }
            }
            if (state !== 'future') {
                ctx.fillStyle = '#0a0e15'; ctx.fillRect(px - shaftW / 2, platformY, shaftW, holeDepth);
                const bitY = platformY + Math.max(casingH, drillDepthPx * dp);
                if (state === 'drill' && dp > .04) { ctx.fillStyle = 'rgba(45,212,191,.26)'; ctx.fillRect(px - shaftW / 2 + 1, platformY + casingH * .4, shaftW - 2, holeDepth - casingH * .4); }
                if (cp > 0 && state !== 'drill') {
                    const cageTop = state === 'cage' ? platformY - 70 * u1 + cageP * (casingH + 78 * u1) : platformY + casingH + 6 * u1;
                    const sway = state === 'cage' ? Math.sin(t * 2.4) * 4 * u1 * (1 - cageP) : 0;
                    const cl = holeDepth - casingH - 14 * u1, cb = Math.min(cageTop + cl, holeBottom - 5);
                    stroke(px - cageW / 2 + sway, cageTop, px - cageW / 2 + sway, cb, '#fbbf24', 1.6); stroke(px + cageW / 2 + sway, cageTop, px + cageW / 2 + sway, cb, '#fbbf24', 1.6);
                    for (let ry = cageTop + 8; ry < cb; ry += 11 * u1) stroke(px - cageW / 2 + sway, ry, px + cageW / 2 + sway, ry, 'rgba(251,191,36,.75)', 1.2);
                    if (state === 'cage') stroke(px + sway, cageTop - 4, px, Math.min(cageTop - 30 * u1, platformY - 90 * u1), 'rgba(226,232,240,.8)', 1.2);
                }
                if (pp > 0) {
                    const cTop = holeBottom - pp * holeDepth;
                    ctx.fillStyle = 'rgba(148,163,178,.82)'; ctx.fillRect(px - shaftW / 2 + 1, cTop, shaftW - 2, holeBottom - cTop);
                    ctx.fillStyle = 'rgba(203,213,225,.5)'; ctx.fillRect(px - shaftW / 2 + 1, cTop, shaftW - 2, 2.5);
                    if (cTop > platformY) { ctx.fillStyle = 'rgba(45,212,191,.22)'; ctx.fillRect(px - shaftW / 2 + 1, platformY + casingH * .4, shaftW - 2, cTop - platformY - casingH * .4); }
                    if (!reduced) for (let k = 0; k < 5; k++) { const bub = ((t * 34 + k * 29) % 26) / 26; ctx.globalAlpha = (1 - bub) * .5; ctx.fillStyle = '#e2e8f0'; ctx.beginPath(); ctx.arc(px + (rnd(i * 7 + k) - .5) * shaftW * .7, platformY + 4 - bub * 10 * u1, 1.8 * u1, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1; }
                }
                if (state === 'drill' && dp > .02) {
                    ctx.fillStyle = 'rgba(56,189,248,.5)'; ctx.fillRect(px - shaftW / 2 - 3, platformY, 3, casingH);
                    ctx.fillStyle = 'rgba(56,189,248,.5)'; ctx.fillRect(px + shaftW / 2, platformY, 3, casingH);
                    stroke(px - shaftW / 2 - 3, platformY, px + shaftW / 2 + 3, platformY, '#7dd3fc', 2);
                }
                if (state === 'done' || (state === 'pour' && pp > .96)) {
                    for (let b = -1.5; b <= 1.5; b++) stroke(px + b * shaftW * .18, platformY - 16 * u1, px + b * shaftW * .18, platformY + 26 * u1, '#fbbf24', 1.4);
                    ctx.fillStyle = 'rgba(203,213,225,.5)'; ctx.beginPath(); ctx.ellipse(px, platformY + 1, shaftW * .62, 3 * u1, 0, 0, Math.PI * 2); ctx.fill();
                }
                if (state === 'drill' && !reduced && dp > .02 && dp < 1) {
                    for (let k = 0; k < 6; k++) { const kp = (t * 1.6 + k / 6) % 1; ctx.globalAlpha = (1 - kp) * .5; ctx.fillStyle = '#94a3b8'; ctx.beginPath(); ctx.arc(px + shaftW * .9 - kp * 30 * u1, platformY - 4 - kp * 16 * u1 + Math.sin(kp * 9 + k) * 3, 1.7 * u1, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1; }
                    ctx.globalAlpha = .14; ctx.fillStyle = '#cbd5e1'; ctx.beginPath(); ctx.arc(px, platformY - 6 * u1, 13 * u1 + Math.sin(t * 6) * 3, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1;
                }
            }
            return { px, bitY: platformY + Math.max(casingH, drillDepthPx * (i === idx ? drillP : i < idx ? 1 : 0)) };
        };
        const active = drawPile(idx);
        for (let i = idx - 1; i >= 0; i--) drawPile(i);

        const prevX = idx === 0 ? -W * .12 : pileX[idx - 1];
        const rigX = u < TRAVEL_END ? prevX + (pileX[idx] - prevX) * ease(seg(0, TRAVEL_END, u)) : pileX[idx];
        const trackH = 9 * u1, bodyH = 12 * u1, trackW = 60 * u1, bodyW = 46 * u1;
        const mastBase = platformY - trackH - bodyH, mastH = Math.min(H * .34, platformY - trackH - bodyH - 26 * u1), mastTop = mastBase - mastH;
        ctx.fillStyle = '#1f2c3d';
        ctx.beginPath(); ctx.roundRect ? ctx.roundRect(rigX - trackW / 2, platformY - trackH, trackW, trackH, 4) : ctx.rect(rigX - trackW / 2, platformY - trackH, trackW, trackH); ctx.fill();
        ctx.fillStyle = 'rgba(125,211,252,.35)';
        const treadPh = (u < TRAVEL_END ? t * 90 : 0) % 7;
        for (let tx = rigX - trackW / 2 + treadPh; tx < rigX + trackW / 2; tx += 7) stroke(tx, platformY - trackH + 1, tx, platformY - 1, 'rgba(125,211,252,.3)', 1.2);
        ctx.fillStyle = '#28507a'; ctx.fillRect(rigX - bodyW * .38, mastBase, bodyW * .76, bodyH);
        ctx.fillStyle = '#1c3452'; ctx.fillRect(rigX - bodyW * .5, mastBase + 1, bodyW * .16, bodyH - 2);
        ctx.fillStyle = 'rgba(253,224,71,.85)'; ctx.fillRect(rigX + bodyW * .12, mastBase + 3, 9 * u1, 6 * u1);
        stroke(rigX - 4 * u1, mastBase, rigX - 1.5 * u1, mastTop, '#9fb3c8', 2 * u1);
        stroke(rigX + 4 * u1, mastBase, rigX + 1.5 * u1, mastTop, '#9fb3c8', 2 * u1);
        for (let my = mastBase - 10; my > mastTop + 6; my -= 13 * u1) stroke(rigX - 4 * u1 + (mastBase - my) / mastH * 2.5, my, rigX + 4 * u1 - (mastBase - my) / mastH * 2.5, my - 8 * u1, 'rgba(159,179,200,.55)', 1);
        ctx.fillStyle = '#9fb3c8'; ctx.fillRect(rigX - 5 * u1, mastTop - 5 * u1, 10 * u1, 5 * u1);
        ctx.fillStyle = 'rgba(226,232,240,.9)'; ctx.beginPath(); ctx.arc(rigX, mastTop - 6 * u1, 3.4 * u1, 0, Math.PI * 2); ctx.fill();
        if (!reduced && (t * 2.4) % 1 < .5) { ctx.fillStyle = '#f87171'; ctx.beginPath(); ctx.arc(rigX, mastTop - 10 * u1, 2 * u1, 0, Math.PI * 2); ctx.fill(); }
        ctx.fillStyle = '#ef4444'; ctx.fillRect(rigX + 9 * u1, mastTop + 6 * u1, 5 * u1, 3.6 * u1);
        ctx.fillStyle = '#f8fafc'; ctx.fillRect(rigX + 9 * u1, mastTop + 6 * u1, 5 * u1, 1.6 * u1);
        if (u >= CASING_END && u < DRILL_END) {
            stroke(rigX, mastTop - 6 * u1, rigX, platformY - 26 * u1, 'rgba(226,232,240,.85)', 1.6);
            const headY = mastBase - mastH * .22 + drillP * mastH * .3;
            ctx.fillStyle = '#f59e0b'; ctx.fillRect(rigX - 7 * u1, headY, 14 * u1, 10 * u1);
            stroke(rigX, headY + 10 * u1, rigX, active.bitY, '#e2e8f0', 2.2);
            const spin = t * 11;
            for (let s = 0; s < 3; s++) { ctx.strokeStyle = 'rgba(245,158,11,.85)'; ctx.lineWidth = 1.6 * u1; ctx.beginPath(); ctx.ellipse(rigX, active.bitY - s * 7 * u1, 8 * u1, 2.6 * u1, Math.sin(spin + s * 2.1) * .9, 0, Math.PI * 2); ctx.stroke(); }
            ctx.fillStyle = '#f59e0b'; ctx.beginPath(); ctx.moveTo(rigX - 3 * u1, active.bitY + 8 * u1); ctx.lineTo(rigX + 3 * u1, active.bitY + 8 * u1); ctx.lineTo(rigX, active.bitY + 14 * u1); ctx.closePath(); ctx.fill();
        } else if (u >= DRILL_END && u < BITUP_END) {
            const lift = ease(seg(DRILL_END, BITUP_END, u));
            stroke(rigX, mastTop - 6 * u1, rigX, mastBase - mastH * (1 - lift) * .5, 'rgba(226,232,240,.7)', 1.4);
            ctx.fillStyle = '#f59e0b'; ctx.fillRect(rigX - 7 * u1, mastBase - mastH * (1 - lift) * .5, 14 * u1, 10 * u1);
        } else if (u >= BITUP_END && u < CAGE_END) {
            stroke(rigX, mastTop - 6 * u1, rigX, platformY - 74 * u1, 'rgba(226,232,240,.85)', 1.4);
        } else if (u >= CAGE_END && u < POUR_END) {
            stroke(rigX, mastTop - 6 * u1, rigX, platformY - 46 * u1, 'rgba(226,232,240,.85)', 1.6);
            ctx.fillStyle = '#475569'; ctx.beginPath(); ctx.moveTo(rigX - 11 * u1, platformY - 46 * u1); ctx.lineTo(rigX + 11 * u1, platformY - 46 * u1); ctx.lineTo(rigX, platformY - 32 * u1); ctx.closePath(); ctx.fill();
            stroke(rigX, platformY - 32 * u1, rigX, platformY + casingH * .4, 'rgba(203,213,225,.85)', 2);
            ctx.strokeStyle = 'rgba(203,213,225,.5)'; ctx.lineWidth = 2; ctx.setLineDash([5, 4]); ctx.lineDashOffset = -t * 40;
            ctx.beginPath(); ctx.moveTo(rigX, platformY - 2 * u1); ctx.lineTo(W * .93, platformY - 2 * u1); ctx.stroke(); ctx.setLineDash([]); ctx.lineDashOffset = 0;
        }
        const pumpX = W * .93;
        ctx.fillStyle = '#2b3f57'; ctx.fillRect(pumpX - 22 * u1, platformY - 15 * u1, 34 * u1, 15 * u1);
        ctx.fillStyle = '#3b5573'; ctx.fillRect(pumpX + 12 * u1, platformY - 12 * u1, 9 * u1, 12 * u1);
        ctx.fillStyle = 'rgba(253,224,71,.6)'; ctx.fillRect(pumpX + 15 * u1, platformY - 10 * u1, 4 * u1, 4 * u1);
        ctx.fillStyle = '#111827'; ctx.beginPath(); ctx.arc(pumpX - 14 * u1, platformY, 3.4 * u1, 0, Math.PI * 2); ctx.arc(pumpX + 16 * u1, platformY, 3.4 * u1, 0, Math.PI * 2); ctx.fill();
        stroke(pumpX - 5 * u1, platformY - 15 * u1, pumpX - 5 * u1, platformY - 30 * u1, '#64748b', 2 * u1);
        stroke(pumpX - 5 * u1, platformY - 30 * u1, pumpX + 14 * u1, platformY - 22 * u1, '#64748b', 2 * u1);
        const tankX = W * .865;
        ctx.fillStyle = '#1f3346'; ctx.fillRect(tankX - 13 * u1, platformY - 38 * u1, 26 * u1, 38 * u1);
        ctx.fillStyle = '#16283a'; ctx.beginPath(); ctx.ellipse(tankX, platformY - 38 * u1, 13 * u1, 4 * u1, 0, 0, Math.PI * 2); ctx.fill();
        const lvl = .55 + (u >= CAGE_END && u < POUR_END ? Math.sin(t * 3) * .12 : 0);
        ctx.fillStyle = 'rgba(45,212,191,.55)'; ctx.fillRect(tankX - 3 * u1, platformY - 34 * u1 + (1 - lvl) * 26 * u1, 6 * u1, lvl * 26 * u1);
        ctx.fillStyle = 'rgba(148,163,184,.5)'; ctx.font = '600 ' + Math.round(7 * u1) + 'px ui-monospace, monospace'; ctx.textAlign = 'center';
        ctx.fillText('SLURRY', tankX, platformY + 14 * u1); ctx.textAlign = 'left';

        const rulerX = active.px - shaftW * 1.15 - 10 * u1;
        stroke(rulerX, platformY, rulerX, holeBottom, 'rgba(148,163,184,.35)', 1);
        const mPerPx = D_TARGET[idx] / holeDepth;
        for (let m = 0; m <= D_TARGET[idx]; m += 4) { const ry = platformY + m / mPerPx; stroke(rulerX - 4 * u1, ry, rulerX, ry, 'rgba(148,163,184,.5)', 1); ctx.fillStyle = 'rgba(148,163,184,.55)'; ctx.font = '600 ' + Math.round(7 * u1) + 'px ui-monospace, monospace'; ctx.fillText('-' + m, rulerX - 18 * u1, ry + 2.5); }
        const markY = platformY + depthM / mPerPx;
        ctx.fillStyle = '#fbbf24'; ctx.beginPath(); ctx.moveTo(rulerX - 2, markY); ctx.lineTo(rulerX - 9 * u1, markY - 4 * u1); ctx.lineTo(rulerX - 9 * u1, markY + 4 * u1); ctx.closePath(); ctx.fill();

        if (pointer.x >= 0) {
            const gl = ctx.createRadialGradient(pointer.x, pointer.y, 0, pointer.x, pointer.y, 90 * u1);
            gl.addColorStop(0, 'rgba(125,211,252,.2)'); gl.addColorStop(1, 'rgba(125,211,252,0)');
            ctx.fillStyle = gl; ctx.fillRect(pointer.x - 90 * u1, pointer.y - 90 * u1, 180 * u1, 180 * u1);
            if (pointer.y > platformY && pointer.y < holeBottom) {
                stroke(0, pointer.y, W, pointer.y, 'rgba(56,189,248,.25)', 1, [4, 5]);
                const pm = (pointer.y - platformY) * mPerPx;
                ctx.fillStyle = '#7dd3fc'; ctx.font = '700 ' + Math.round(9 * u1) + 'px ui-monospace, monospace';
                ctx.fillText('EL -' + pm.toFixed(1) + ' M', Math.min(pointer.x + 10, W - 70), pointer.y - 6);
            }
            ctx.beginPath(); ctx.arc(pointer.x, pointer.y, 12 * u1 + Math.sin(t * 5) * 2.5 + pointer.pulse * 14, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(125,211,252,.55)'; ctx.lineWidth = 1; ctx.stroke(); pointer.pulse *= .92;
        }
        const capP = ease(seg(LOOP - 4.6, LOOP - 2.6, t));
        if (capP > 0) { ctx.globalAlpha = capP; ctx.fillStyle = '#64748b'; ctx.fillRect(pileX[0] - shaftW, platformY - 13 * u1, pileX[3] - pileX[0] + shaftW * 2, 12 * u1); ctx.fillStyle = '#94a3b8'; ctx.fillRect(pileX[0] - shaftW, platformY - 13 * u1, pileX[3] - pileX[0] + shaftW * 2, 2.5); ctx.globalAlpha = 1; }
        const veil = seg(LOOP - 2, LOOP, t);
        if (veil > 0 && !reduced) { ctx.fillStyle = 'rgba(5,13,28,' + (veil * .92) + ')'; ctx.fillRect(0, 0, W, H); }

        phaseEl.textContent = 'BP-0' + (idx + 1) + ' \u00B7 ' + phaseName;
        depthEl.textContent = pourP > 0 && pourP < 1 ? 'POUR ' + Math.round(pourP * 100) + '%' : 'DEPTH ' + depthM.toFixed(1).padStart(4, '0') + ' M';
        if (motion) raf = requestAnimationFrame(draw);
    };
    resize(); draw(performance.now());
    window.addEventListener('resize', resize, { passive: true });
    const ro = new ResizeObserver(() => { resize(); if (!motion) draw(performance.now()); });
    ro.observe(scene);
    canvas.addEventListener('pointermove', (e) => { const b = canvas.getBoundingClientRect(); pointer.x = e.clientX - b.left; pointer.y = e.clientY - b.top; pointer.active = true; if (!motion) draw(performance.now()); });
    canvas.addEventListener('pointerleave', () => { pointer.x = -1; pointer.y = -1; pointer.active = false; if (!motion) draw(performance.now()); });
    canvas.addEventListener('pointerdown', () => { pointer.pulse = 1; });
    optin?.addEventListener('click', () => { motion = true; optin.hidden = true; start = performance.now(); draw(start); });
})();
</script>
