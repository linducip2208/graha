const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens-mobile');

const PAGES = [
  { name: 'dashboard', url: '/dashboard' },
  { name: 'projects-gantt', url: '/admin/projects' },
  { name: 'inventory', url: '/admin/inventory' },
  { name: 'billing-retention', url: '/admin/billing' },
  { name: 'taxes', url: '/admin/taxes' },
  { name: 'docs', url: '/docs' },
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: { width: 414, height: 896 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
  });
  const page = await ctx.newPage();

  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.locator('input[type="email"]').first().fill('');
  await page.type('input[type="email"]', 'admin@grahapondasi.test', { delay: 30 });
  await page.locator('input[type="password"]').first().fill('');
  await page.type('input[type="password"]', 'password', { delay: 30 });
  await page.waitForTimeout(400);
  await Promise.all([
    page.waitForURL('**/dashboard', { timeout: 30000 }),
    page.locator('button[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(1500);

  let ok = 0;
  for (const target of PAGES) {
    try {
      await page.goto(BASE + target.url, { waitUntil: 'networkidle', timeout: 45000 });
      await page.waitForTimeout(1200);
      await page.screenshot({ path: path.join(OUT, target.name + '.png') });
      console.log('OK   ' + target.name);
      ok++;
    } catch (err) {
      console.log('FAIL ' + target.name + ' :: ' + err.message.split('\n')[0]);
    }
  }

  // Mobile sidebar drawer proof
  await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
  await page.locator('[data-sidebar-open]').first().click().catch(() => {});
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, 'sidebar-drawer.png') });
  ok++;

  await browser.close();
  console.log(`Done: ${ok} captured -> ${OUT}`);
})();
