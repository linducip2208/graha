// Screenshot matrix V3 — seluruh workspace + publik + mobile.
const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
const ADMIN = [
  ['dashboard-v3-1440', '/dashboard'],
  ['my-work-v3-1440', '/admin/my-work'],
  ['apps-v3-1440', '/apps'],
  ['commercial-tenders-v3-1440', '/admin/tenders'],
  ['commercial-contracts-v3-1440', '/admin/contracts'],
  ['projects-v3-1440', '/admin/projects'],
  ['field-ops-v3-1440', '/admin/projects/field-ops'],
  ['foundation-control-v3-1440', '/admin/projects/1/foundation-control'],
  ['pile-detail-v3-1440', '/admin/bored-piles/1/passport'],
  ['inventory-v3-1440', '/admin/inventory'],
  ['material-request-v3-1440', '/admin/inventory/material-requests'],
  ['procurement-v3-1440', '/admin/procurement'],
  ['rfq-comparison-v3-1440', '/admin/procurement/rfq'],
  ['manufacturing-v3-1440', '/admin/manufacturing'],
  ['manufacturing-costing-v3-1440', '/admin/manufacturing/costing'],
  ['equipment-v3-1440', '/admin/operations'],
  ['fuel-v3-1440', '/admin/fuel-tanks'],
  ['finance-overview-v3-1440', '/admin/finance/overview'],
  ['general-ledger-v3-1440', '/admin/finance'],
  ['journals-v3-1440', '/admin/finance/journals'],
  ['billing-v3-1440', '/admin/billing'],
  ['tax-v3-1440', '/admin/taxes'],
  ['cash-bank-v3-1440', '/admin/cash-bank'],
  ['project-costing-v3-1440', '/admin/project-costing'],
  ['fixed-assets-v3-1440', '/admin/fixed-assets'],
  ['qms-v3-1440', '/admin/qms'],
  ['hse-v3-1440', '/admin/hse'],
  ['documents-v3-1440', '/admin/documents'],
  ['approvals-v3-1440', '/admin/approvals'],
  ['signatures-v3-1440', '/admin/signatures'],
  ['audit-v3-1440', '/admin/audit'],
  ['reports-v3-1440', '/admin/reports/executive'],
  ['report-aging-v3-1440', '/admin/reports/aging'],
  ['settings-v3-1440', '/admin/settings'],
  ['roles-v3-1440', '/admin/organization/roles'],
  ['experience-v3-1440', '/admin/experience'],
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
  for (const [name, url] of ADMIN) {
    try {
      await page.goto(BASE + url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(420);
      await page.screenshot({ path: path.join(OUT, name + '.png') });
      console.log('OK ' + name);
    } catch (e) { console.log('FAIL ' + name); }
  }
  // Launcher settings (section studio)
  await page.goto(BASE + '/admin/experience', { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  const fs = page.locator('fieldset', { has: page.locator('legend', { hasText: 'App Launcher' }) });
  await fs.scrollIntoViewIfNeeded();
  await page.waitForTimeout(300);
  await fs.screenshot({ path: path.join(OUT, 'launcher-settings-v3-1440.png') });
  console.log('OK launcher-settings-v3-1440');

  // ===== PUBLIC (guest) =====
  const gctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const g = await gctx.newPage();
  await g.goto(BASE + '/', { waitUntil: 'networkidle' });
  await g.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 90)); } window.scrollTo(0, 0); });
  await g.waitForTimeout(700);
  await g.screenshot({ path: path.join(OUT, 'frontend-home-v3-1440.png'), fullPage: true });
  console.log('OK frontend-home-v3-1440');
  await g.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await g.waitForTimeout(400);
  await g.screenshot({ path: path.join(OUT, 'frontend-login-v3-1440.png') });
  console.log('OK frontend-login-v3-1440');
  await g.goto(BASE + '/docs', { waitUntil: 'networkidle' });
  await g.waitForTimeout(500);
  await g.screenshot({ path: path.join(OUT, 'frontend-docs-v3-1440.png') });
  console.log('OK frontend-docs-v3-1440');

  // ===== MOBILE =====
  const shots = [
    ['projects-v3-390', '/admin/projects'],
    ['inventory-v3-390', '/admin/inventory'],
    ['finance-v3-390', '/admin/finance/overview'],
    ['qms-v3-390', '/admin/qms'],
    ['documents-v3-390', '/admin/documents'],
    ['settings-v3-390', '/admin/settings'],
  ];
  for (const [name, url] of shots) {
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
  const mh = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mp = await mh.newPage();
  await mp.goto(BASE + '/', { waitUntil: 'networkidle' });
  await mp.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 80)); } window.scrollTo(0, 0); });
  await mp.waitForTimeout(600);
  await mp.screenshot({ path: path.join(OUT, 'frontend-home-v3-390.png'), fullPage: true });
  console.log('OK frontend-home-v3-390');
  await mp.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await mp.waitForTimeout(300);
  await mp.screenshot({ path: path.join(OUT, 'frontend-login-v3-390.png') });
  console.log('OK frontend-login-v3-390');
  await mp.goto(BASE + '/docs', { waitUntil: 'networkidle' });
  await mp.waitForTimeout(400);
  await mp.screenshot({ path: path.join(OUT, 'frontend-docs-v3-390.png') });
  console.log('OK frontend-docs-v3-390');

  await browser.close();
})();
