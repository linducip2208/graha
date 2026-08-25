const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
const SHOTS = [
  ['organization-v3-1440', '/admin/organization', false],
  ['roles-v3-1440', '/admin/organization/roles', false],
  ['finance-accounts-v3-1440', '/admin/finance/accounts', false],
  ['project-costing-v3-1440', '/admin/project-costing', false],
  ['manufacturing-v3b-1440', '/admin/manufacturing', false],
  ['qms-tabs-v3-1440', '/admin/qms?tab=risk', false],
  ['hse-tabs-v3-1440', '/admin/hse?tab=incident', false],
  ['tenders-v3b-1440', '/admin/tenders', false],
  ['billing-v3b-1440', '/admin/billing', false],
  ['cash-bank-v3b-1440', '/admin/cash-bank', false],
  ['fixed-assets-v3b-1440', '/admin/fixed-assets', false],
  ['approvals-v3b-1440', '/admin/approvals', false],
  ['signatures-v3b-1440', '/admin/signatures', false],
  ['rfq-v3b-1440', '/admin/procurement/rfq', false],
  ['organization-dark-v3-1440', '/admin/organization', true],
  ['procurement-dark-v3-1440', '/admin/procurement', true],
  ['qms-dark-v3-1440', '/admin/qms', true],
  ['finance-dark-v3-1440', '/admin/finance/overview', true],
];
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 10 });
  await page.type('input[type=password]', 'password', { delay: 10 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1300);
  for (const [name, url, dark] of SHOTS) {
    try {
      await page.goto(BASE + url, { waitUntil: 'networkidle' });
      if (dark) { await page.evaluate(() => document.documentElement.classList.add('dark')); }
      await page.waitForTimeout(400);
      await page.screenshot({ path: path.join(OUT, name + '.png') });
      console.log('OK ' + name);
    } catch (e) { console.log('FAIL ' + name); }
  }
  const MOBILE = [['organization-mobile-v3-390', '/admin/organization'], ['tenders-mobile-v3-390', '/admin/tenders'], ['procurement-mobile-v3-390', '/admin/procurement'], ['qms-mobile-v3-390', '/admin/qms'], ['cash-bank-mobile-v3-390', '/admin/cash-bank']];
  for (const [name, url] of MOBILE) {
    const m = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    const mp = await m.newPage();
    await mp.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await mp.type('input[type=email]', 'admin@grahapondasi.test', { delay: 10 });
    await mp.type('input[type=password]', 'password', { delay: 10 });
    await mp.locator('button[type=submit]').first().click();
    await mp.waitForTimeout(1100);
    await mp.goto(BASE + url, { waitUntil: 'networkidle' });
    await mp.waitForTimeout(400);
    await mp.screenshot({ path: path.join(OUT, name + '.png') });
    console.log('OK ' + name);
    await m.close();
  }
  await browser.close();
})();
