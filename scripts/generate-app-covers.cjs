// Generate premium local cover art untuk /apps launcher (1200x675 WebP).
// Grafis digenerate internal (gradient mesh + pattern + icon stroke) â€” tanpa asset pihak ketiga.
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const OUT = path.join(__dirname, '..', 'public', 'images', 'apps');

const icon = (paths) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
const ICONS = {
  commercial: icon('<path d="M5 21V4"/><path d="M5 4.5c4.5-2 8.5 2 14 .5v9c-5.5 1.5-9.5-2.5-14-.5"/>'),
  project: icon('<path d="M12 2.5l8.5 4.75v9.5L12 21.5l-8.5-4.75v-9.5L12 2.5z"/><path d="M3.5 7.25L12 12l8.5-4.75"/><path d="M12 12v9.5"/>'),
  'supply-chain': icon('<path d="M3 4h18v5H3z"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/><path d="M10 13h4"/>'),
  operations: icon('<circle cx="12" cy="12" r="3.4"/><path d="M12 2.5v3m0 13v3m-9.5-9.5h3m13 0h3M4.9 4.9L7 7m10 10l2.1 2.1m.001-14.2L17 7M7 17l-2.1 2.1"/>'),
  finance: icon('<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5h.01M18 14.5h.01"/>'),
  'quality-hse': icon('<path d="M12 2.5l8 3v6.2c0 4.8-3.4 8.2-8 10.3-4.6-2.1-8-5.5-8-10.3V5.5l8-3z"/><path d="M8.8 11.8l2.4 2.4 4.2-4.7"/>'),
  'documents-approval': icon('<path d="M6 2.5h7.5L18 7v14.5H6V2.5z"/><path d="M13.5 2.5V7H18"/><path d="M9 12.5h6M9 16.5h6"/>'),
  reports: icon('<path d="M4 20h16"/><path d="M6.5 20v-6M11.5 20V8M16.5 20V4.5"/>'),
  settings: icon('<path d="M4 8h10m4 0h2M4 16h4m4 0h8"/><circle cx="16" cy="8" r="2.2"/><circle cx="10" cy="16" r="2.2"/>'),
};

const WORKSPACES = [
  { key: 'commercial', accent: '#3b82f6', accent2: '#1d4ed8', label: 'Komersial' },
  { key: 'project', accent: '#f59e0b', accent2: '#b45309', label: 'Proyek' },
  { key: 'supply-chain', accent: '#10b981', accent2: '#047857', label: 'Supply Chain' },
  { key: 'operations', accent: '#0ea5e9', accent2: '#0369a1', label: 'Workshop & Equipment' },
  { key: 'finance', accent: '#38bdf8', accent2: '#0e7490', label: 'Keuangan' },
  { key: 'quality-hse', accent: '#22c55e', accent2: '#15803d', label: 'Quality & HSE' },
  { key: 'documents-approval', accent: '#8b5cf6', accent2: '#6d28d9', label: 'Dokumen & Approval' },
  { key: 'reports', accent: '#2dd4bf', accent2: '#0f766e', label: 'Laporan' },
  { key: 'settings', accent: '#94a3b8', accent2: '#475569', label: 'Pengaturan' },
];

const renderPage = (ws) => `<!doctype html><html><head><meta charset="utf-8"><style>
  *{margin:0;padding:0}
  body{width:1200px;height:675px;overflow:hidden}
  .cover{
    position:relative;width:1200px;height:675px;
    background:
      radial-gradient(900px 520px at 82% 18%, ${ws.accent}44, transparent 62%),
      radial-gradient(700px 500px at 12% 88%, ${ws.accent2}66, transparent 60%),
      linear-gradient(128deg, #0b1220 8%, #101a2c 52%, ${ws.accent2} 130%);
    font-family:'Segoe UI',system-ui,sans-serif;
  }
  .grid-lines{position:absolute;inset:0;opacity:.16;
    background-image:linear-gradient(${ws.accent} 1px, transparent 1px),linear-gradient(90deg, ${ws.accent} 1px, transparent 1px);
    background-size:56px 56px;
    -webkit-mask-image:radial-gradient(760px 480px at 70% 30%, black, transparent 78%);
            mask-image:radial-gradient(760px 480px at 70% 30%, black, transparent 78%);}
  .diag{position:absolute;inset:0;opacity:.10;background:repeating-linear-gradient(118deg, transparent 0 26px, #ffffff 26px 27px)}
  .glow{position:absolute;right:-120px;top:-160px;width:620px;height:620px;border-radius:9999px;
    background:radial-gradient(circle at 38% 38%, ${ws.accent}55, transparent 68%)}
  .ring{position:absolute;border:2px solid ${ws.accent}55;border-radius:9999px}
  .r1{width:420px;height:420px;right:70px;top:110px;opacity:.5}
  .r2{width:280px;height:280px;right:140px;top:180px;opacity:.35;border-style:dashed}
  .glyph{position:absolute;right:196px;top:236px;width:168px;height:168px;color:#ffffff;opacity:.96;
    filter:drop-shadow(0 18px 42px rgba(0,0,0,.45))}
  .glyph svg{width:100%;height:100%}
  .bar{position:absolute;left:0;top:0;bottom:0;width:14px;background:linear-gradient(180deg, ${ws.accent}, ${ws.accent2})}
  .brand{position:absolute;left:64px;top:64px;display:flex;align-items:center;gap:18px}
  .chip{width:64px;height:64px;border-radius:18px;display:grid;place-items:center;color:#fff;
    background:linear-gradient(135deg, ${ws.accent}, ${ws.accent2});box-shadow:0 16px 40px -10px ${ws.accent}88}
  .chip svg{width:34px;height:34px}
  .name{color:#e6edf7;font-size:44px;font-weight:800;letter-spacing:-.5px}
  .sub{color:${ws.accent};font-size:17px;font-weight:700;letter-spacing:.32em;text-transform:uppercase;margin-top:6px;opacity:.9}
  .foot{position:absolute;left:64px;bottom:58px;display:flex;align-items:center;gap:14px;color:#8fa3bd;font-size:19px;font-weight:600;letter-spacing:.06em}
  .dot{width:10px;height:10px;border-radius:9999px;background:${ws.accent};box-shadow:0 0 0 6px ${ws.accent}33}
</style></head><body>
<div class="cover">
  <div class="grid-lines"></div><div class="diag"></div><div class="glow"></div>
  <div class="ring r1"></div><div class="ring r2"></div>
  <div class="glyph">${ICONS[ws.key] || ICONS.settings}</div>
  <div class="bar"></div>
  <div class="brand">
    <div class="chip">${ICONS[ws.key] || ICONS.settings}</div>
    <div><div class="name">${ws.label}</div><div class="sub">Workspace</div></div>
  </div>
  <div class="foot"><span class="dot"></span> Graha Pondasi ERP</div>
</div>
</body></html>`;

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 675 }, deviceScaleFactor: 1 });
  for (const ws of WORKSPACES) {
    const target = path.join(OUT, `${ws.key}.webp`);
    await page.setContent(renderPage(ws), { waitUntil: 'load' });
    try {
      await page.screenshot({ path: target, type: 'webp', quality: 84, clip: { x: 0, y: 0, width: 1200, height: 675 } });
    } catch (e) {
      // Fallback: Chromium tanpa webp -> png (registry menyesuaikan).
      await page.screenshot({ path: target.replace('.webp', '.png'), type: 'png', clip: { x: 0, y: 0, width: 1200, height: 675 } });
      console.log('PNG fallback untuk ' + ws.key);
    }
    console.log('OK ' + ws.key);
  }
  await browser.close();
})();

