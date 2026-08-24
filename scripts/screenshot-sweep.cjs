const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
const SHOTS = [
  ['reports-executive-v2-1440', '/admin/reports/executive'],
  ['inventory-opname-v2-1440', '/admin/inventory/opname'],
  ['manufacturing-quality-v2-1440', '/admin/manufacturing/quality'],
  ['foundation-control-v2-1440', '/admin/projects/1/foundation-control'],
  ['pile-passport-v2-1440', '/admin/bored-piles/1/passport'],
  ['notifications-v2-1440', '/admin/notifications'],
  ['my-work-v2-1440', '/admin/my-work'],
  ['taxes-v2-1440', '/admin/taxes'],
];
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 12 });
  await page.type('input[type=password]', 'password', { delay: 12 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1200);
  for (const [name, url] of SHOTS) {
    try {
      await page.goto(BASE + url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(400);
      await page.screenshot({ path: path.join(OUT, name + '.png') });
      console.log('OK ' + name);
    } catch (e) { console.log('FAIL ' + name); }
  }
  await browser.close();
})();
