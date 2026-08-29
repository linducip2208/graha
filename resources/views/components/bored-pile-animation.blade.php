<div class="bored-pile-canvas-scene" data-bored-pile-scene role="img" aria-label="Animasi teknis proses bored pile: rig mengebor, cage turun, dan beton mengisi pile">
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
    let motionEnabled = !reduced;
    const optin = scene.querySelector('[data-bp-motion-optin]');
    if (reduced && optin) optin.hidden = false;
    let width = 0, height = 0, dpr = 1, start = performance.now(), raf;
    const pointer = { x: -1, y: -1, pulse: 0, active: false };
    const resize = () => { const box = scene.getBoundingClientRect(); dpr = Math.min(window.devicePixelRatio || 1, 2); width = box.width; height = box.height; canvas.width = width * dpr; canvas.height = height * dpr; canvas.style.width = width + 'px'; canvas.style.height = height + 'px'; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); };
    const noise = (n) => { const x = Math.sin(n * 12.9898) * 43758.5453; return x - Math.floor(x); };
    const stroke = (x1, y1, x2, y2, color, weight = 1, dash = []) => { ctx.beginPath(); ctx.setLineDash(dash); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.strokeStyle = color; ctx.lineWidth = weight; ctx.stroke(); ctx.setLineDash([]); };
    const draw = (now) => {
        const t = reduced ? 3.2 : (now - start) / 1000;
        const ground = height * .43, shaftX = width * .5, shaftW = Math.max(28, width * .105), soilH = height - ground;
        ctx.clearRect(0, 0, width, height);
        const sky = ctx.createLinearGradient(0, 0, 0, ground); sky.addColorStop(0, '#071b35'); sky.addColorStop(1, '#123a5a'); ctx.fillStyle = sky; ctx.fillRect(0, 0, width, ground);
        ctx.fillStyle = '#1d2b3c'; ctx.fillRect(0, ground, width, soilH);
        for (let i = 0; i < 4; i++) { ctx.fillStyle = ['#26384a', '#223244', '#1d2c3e', '#182638'][i]; ctx.fillRect(0, ground + i * soilH / 4, width, soilH / 4); }
        ctx.globalAlpha = .12; for (let x = 0; x < width; x += 26) stroke(x, 0, x, height, '#7dd3fc'); for (let y = 0; y < height; y += 26) stroke(0, y, width, y, '#7dd3fc'); ctx.globalAlpha = 1; stroke(0, ground, width, ground, '#67e8f9', 1.5, [7, 6]);
        const sweepX = (t * 86) % (width + 80) - 40; stroke(sweepX, 0, sweepX, height, 'rgba(125,211,252,.22)', 1.2); stroke(sweepX - 14, ground, sweepX + 14, ground, '#fbbf24', 1.5);
        ctx.fillStyle = '#0b1729'; ctx.fillRect(shaftX - shaftW / 2, ground, shaftW, soilH);
        const cycle = t % 12, idleDrillProgress = Math.min(1, cycle / 4), mouseDrillProgress = pointer.active ? Math.max(0, Math.min(1, (pointer.y - ground * .28) / (height * .58))) : idleDrillProgress, drillProgress = pointer.active ? mouseDrillProgress : idleDrillProgress, cageProgress = Math.max(0, Math.min(1, (cycle - 4) / 3)), pourProgress = Math.max(0, Math.min(1, (cycle - 7) / 4));
        const depth = cycle < 4 ? drillProgress * 28 : cycle < 7 ? 28 : 28 * (1 - pourProgress * .12);
        const rigY = Math.sin(t * 7) * (cycle < 4 ? 1.8 : .35), headY = ground - 34 + drillProgress * 42 + rigY;
        ctx.save(); ctx.translate(shaftX, rigY); ctx.fillStyle = '#0ea5e9'; ctx.beginPath(); ctx.moveTo(-width * .11, 26); ctx.lineTo(width * .11, 26); ctx.lineTo(width * .07, ground - 5); ctx.lineTo(-width * .07, ground - 5); ctx.closePath(); ctx.fill(); stroke(-width * .16, ground, -width * .11, 26, '#bae6fd', 4); stroke(width * .16, ground, width * .11, 26, '#bae6fd', 4); ctx.fillStyle = '#0284c7'; ctx.fillRect(-width * .07, ground - 25, width * .14, 22); ctx.restore();
        stroke(shaftX, 32 + rigY, shaftX, headY, '#f8fafc', 4); ctx.fillStyle = '#f59e0b'; ctx.beginPath(); ctx.moveTo(shaftX - 11, headY); ctx.lineTo(shaftX + 11, headY); ctx.lineTo(shaftX, headY + 17); ctx.closePath(); ctx.fill();
        const cageY = ground + (1 - cageProgress) * 58; stroke(shaftX - 10, cageY, shaftX - 10, height, '#fbbf24', 2); stroke(shaftX + 10, cageY, shaftX + 10, height, '#fbbf24', 2); for (let y = cageY + 12; y < height; y += 17) stroke(shaftX - 10, y, shaftX + 10, y, '#fbbf24', 1.5);
        if (pourProgress > 0) { ctx.fillStyle = '#aebdcb'; ctx.globalAlpha = .88; ctx.fillRect(shaftX - shaftW / 2 + 5, ground + soilH * (1 - pourProgress), shaftW - 10, soilH * pourProgress); ctx.globalAlpha = 1; for (let i = 0; i < 5; i++) { const px = shaftX + (noise(i + Math.floor(t * 8)) - .5) * 24; const py = ground + 5 + ((t * 70 + i * 13) % Math.max(8, soilH * pourProgress)); ctx.fillStyle = '#e2e8f0'; ctx.beginPath(); ctx.arc(px, py, 2.2, 0, Math.PI * 2); ctx.fill(); } }
        if (cycle < 4) for (let i = 0; i < 9; i++) { const px = shaftX + (noise(i + Math.floor(t * 10)) - .5) * 54, py = headY + 18 + ((i * 17 + t * 60) % 38); ctx.fillStyle = i % 2 ? '#f59e0b' : '#cbd5e1'; ctx.fillRect(px, py, 2, 2); }
        if (pointer.x >= 0) { const glow = ctx.createRadialGradient(pointer.x, pointer.y, 0, pointer.x, pointer.y, 90); glow.addColorStop(0, 'rgba(125,211,252,.22)'); glow.addColorStop(1, 'rgba(125,211,252,0)'); ctx.fillStyle = glow; ctx.fillRect(pointer.x - 90, pointer.y - 90, 180, 180); ctx.beginPath(); ctx.arc(pointer.x, pointer.y, 14 + Math.sin(t * 5) * 3 + pointer.pulse * 12, 0, Math.PI * 2); ctx.strokeStyle = 'rgba(125,211,252,.55)'; ctx.lineWidth = 1; ctx.stroke(); pointer.pulse *= .9; }
        const phase = cycle < 4 ? 'DRILLING' : cycle < 7 ? 'REINFORCEMENT' : cycle < 11 ? 'CONCRETING' : 'COMPLETED'; phaseEl.textContent = phase; depthEl.textContent = `DEPTH ${depth.toFixed(1).padStart(4, '0')} M`;
        ctx.fillStyle = '#cbd5e1'; ctx.font = '600 9px ui-monospace, monospace'; ctx.fillText('PLATFORM LEVEL', 12, ground + 18); ctx.fillText('FOUNDATION / BP-01', Math.max(12, width - 142), height - 12);
        if (motionEnabled) raf = requestAnimationFrame(draw);
    };
    resize(); draw(performance.now()); window.addEventListener('resize', resize, { passive: true });
    canvas.addEventListener('pointermove', (event) => { const box = canvas.getBoundingClientRect(); pointer.x = event.clientX - box.left; pointer.y = event.clientY - box.top; pointer.active = true; });
    canvas.addEventListener('pointerleave', () => { pointer.x = -1; pointer.y = -1; pointer.active = false; });
    canvas.addEventListener('pointerdown', () => { pointer.pulse = 1; });
    optin?.addEventListener('click', () => { motionEnabled = true; optin.hidden = true; start = performance.now(); draw(start); });
    scene.addEventListener('DOMNodeRemoved', () => cancelAnimationFrame(raf), { once: true });
})();
</script>
