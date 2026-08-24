const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
const SHOTS = [['dashboard-dark-v3-1440','/dashboard'],['documents-dark-v3-1440','/admin/documents'],['projects-dark-v3-1440','/admin/projects'],['finance-dark-v3-1440','/admin/finance/overview'],['qms-dark-v3-1440','/admin/qms']];
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 10 });
  await page.type('input[type=password]', 'password', { delay: 10 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1200);
  for (const [name, url] of SHOTS) {
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.waitForTimeout(350);
    await page.screenshot({ path: path.join(OUT, name + '.png') });
    console.log('OK ' + name);
  }
  await browser.close();
})();
