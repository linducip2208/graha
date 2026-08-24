const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');

(async () => {
  const browser = await chromium.launch();
  const login = async (ctx) => {
    const p = await ctx.newPage();
    await p.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await p.type('input[type=email]', 'admin@grahapondasi.test', { delay: 15 });
    await p.type('input[type=password]', 'password', { delay: 15 });
    await p.locator('button[type=submit]').first().click();
    await p.waitForTimeout(1200);
    return p;
  };

  // Desktop visual (light)
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await login(ctx);
  await page.goto(BASE + '/apps', { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);
  // Scroll penuh dulu agar semua cover lazy-load sebelum fullPage capture.
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(OUT, 'apps-visual-1440.png'), fullPage: true });
  console.log('OK apps-visual-1440');

  // Dark
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(OUT, 'apps-visual-dark-1440.png'), fullPage: true });
  console.log('OK apps-visual-dark-1440');
  await page.evaluate(() => document.documentElement.classList.remove('dark'));

  // Compact
  await page.click('[data-view-btn="compact"]');
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(OUT, 'apps-compact-1440.png') });
  console.log('OK apps-compact-1440');

  // Tablet 2 kolom
  await page.setViewportSize({ width: 820, height: 1180 });
  await page.click('[data-view-btn="visual"]');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(OUT, 'apps-tablet-820.png') });
  console.log('OK apps-tablet-820');

  // Mobile 1 kolom
  const mctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mpage = await login(mctx);
  await mpage.goto(BASE + '/apps', { waitUntil: 'networkidle' });
  await mpage.waitForTimeout(700);
  await mpage.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); }
    window.scrollTo(0, 0);
  });
  await mpage.waitForTimeout(800);
  await mpage.screenshot({ path: path.join(OUT, 'apps-mobile-390.png'), fullPage: true });
  console.log('OK apps-mobile-390');

  await browser.close();
})();
