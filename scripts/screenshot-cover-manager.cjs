// Visual QA: Cover Manager di Experience Studio + custom/default covers di /apps.
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
const TMP = path.join(__dirname, 'tmp-custom-cover.png');

(async () => {
  // Buat gambar custom cover sederhana (PNG 1600x900) via canvas browser.
  const browser = await chromium.launch();
  const genCtx = await browser.newContext({ viewport: { width: 800, height: 450 } });
  const gen = await genCtx.newPage();
  await gen.setContent('<canvas id="c" width="1600" height="900"></canvas>');
  const b64 = await gen.evaluate(() => {
    const c = document.getElementById('c');
    const x = c.getContext('2d');
    const g = x.createLinearGradient(0, 0, 1600, 900);
    g.addColorStop(0, '#7c3aed'); g.addColorStop(1, '#0e7490');
    x.fillStyle = g; x.fillRect(0, 0, 1600, 900);
    x.fillStyle = 'rgba(255,255,255,.92)';
    x.font = '800 120px Segoe UI'; x.fillText('CUSTOM COVER', 90, 480);
    x.font = '600 44px Segoe UI'; x.fillStyle = 'rgba(255,255,255,.75)'; x.fillText('Graha Pondasi ERP · Workspace Proyek', 96, 560);
    return c.toDataURL('image/png');
  });
  fs.writeFileSync(TMP, Buffer.from(b64.split(',')[1], 'base64'));
  await genCtx.close();

  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 15 });
  await page.type('input[type=password]', 'password', { delay: 15 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1200);

  // 1) Studio — section App Launcher
  await page.goto(BASE + '/admin/experience', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const fieldset = page.locator('fieldset', { has: page.locator('legend', { hasText: 'App Launcher' }) });
  await fieldset.scrollIntoViewIfNeeded();
  await page.waitForTimeout(400);
  await fieldset.screenshot({ path: path.join(OUT, 'experience-app-launcher-settings-1440.png') });
  console.log('OK experience-app-launcher-settings-1440');

  // 2) Upload custom cover untuk proyek lewat form studio asli
  const proyekCard = page.locator('article[data-cover-key="proyek"]');
  await proyekCard.locator('input[type="file"]').setInputFiles(TMP);
  await page.waitForTimeout(1500); // submit + reload
  await page.goto(BASE + '/admin/experience', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await fieldset.scrollIntoViewIfNeeded();
  await page.waitForTimeout(300);
  await fieldset.screenshot({ path: path.join(OUT, 'experience-app-launcher-custom-1440.png') });
  console.log('OK experience-app-launcher-custom-1440');

  // 3) /apps dengan custom cover (scroll agar lazy-load termuat)
  await page.goto(BASE + '/apps', { waitUntil: 'networkidle' });
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 100)); } window.scrollTo(0, 0); });
  await page.waitForTimeout(700);
  await page.screenshot({ path: path.join(OUT, 'apps-custom-covers-1440.png'), fullPage: true });
  console.log('OK apps-custom-covers-1440');

  // 4) Reset proyek ke default (konfirmasi via modal custom #confirm-modal-ok)
  const resetCover = async () => {
    await page.goto(BASE + '/admin/experience', { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await proyekCard.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    await proyekCard.locator('form[action*="covers/delete"] button').click();
    await page.waitForTimeout(400);
    await page.locator('#confirm-modal-ok').click();
    await page.waitForTimeout(1200);
  };
  await resetCover();
  await page.goto(BASE + '/apps', { waitUntil: 'networkidle' });
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 100)); } window.scrollTo(0, 0); });
  await page.waitForTimeout(700);
  await page.screenshot({ path: path.join(OUT, 'apps-default-covers-1440.png'), fullPage: true });
  console.log('OK apps-default-covers-1440');

  // 5) Mobile custom covers — upload ulang lalu foto mobile
  await page.goto(BASE + '/admin/experience', { waitUntil: 'networkidle' });
  await proyekCard.locator('input[type="file"]').setInputFiles(TMP);
  await page.waitForTimeout(1500);
  const mctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mpage = await mctx.newPage();
  await mpage.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await mpage.type('input[type=email]', 'admin@grahapondasi.test', { delay: 15 });
  await mpage.type('input[type=password]', 'password', { delay: 15 });
  await mpage.locator('button[type=submit]').first().click();
  await mpage.waitForTimeout(1200);
  await mpage.goto(BASE + '/apps', { waitUntil: 'networkidle' });
  await mpage.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 100)); } window.scrollTo(0, 0); });
  await mpage.waitForTimeout(700);
  await mpage.screenshot({ path: path.join(OUT, 'apps-custom-covers-mobile-390.png'), fullPage: true });
  console.log('OK apps-custom-covers-mobile-390');

  // Bersihkan: reset lagi supaya state demo kembali default
  await resetCover();
  fs.rmSync(TMP, { force: true });
  await browser.close();
})();
