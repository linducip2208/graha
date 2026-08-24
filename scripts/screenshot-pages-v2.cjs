const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
const SHOTS = [
  { name: 'projects-index-v2-1440', url: '/admin/projects' },
  { name: 'field-ops-v2-1440', url: '/admin/projects/field-ops' },
  { name: 'finance-overview-v2-1440', url: '/admin/finance/overview' },
  { name: 'finance-journals-v2-1440', url: '/admin/finance/journals' },
  { name: 'billing-v2-1440', url: '/admin/billing' },
  { name: 'qms-v2-1440', url: '/admin/qms' },
  { name: 'hse-v2-1440', url: '/admin/hse' },
  { name: 'manufacturing-v2-1440', url: '/admin/manufacturing' },
  { name: 'operations-v2-1440', url: '/admin/operations' },
  { name: 'tenders-v2-1440', url: '/admin/tenders' },
  { name: 'contracts-v2-1440', url: '/admin/contracts' },
  { name: 'cages-v2-1440', url: '/admin/manufacturing/cages' },
  { name: 'casings-v2-1440', url: '/admin/casings' },
  { name: 'fuel-tanks-v2-1440', url: '/admin/fuel-tanks' },
  { name: 'fixed-assets-v2-1440', url: '/admin/fixed-assets' },
  { name: 'settings-v2-1440', url: '/admin/settings' },
  { name: 'organization-v2-1440', url: '/admin/organization' },
  { name: 'rfq-v2-1440', url: '/admin/procurement/rfq' },
  { name: 'approvals-v2-1440', url: '/admin/approvals' },
  { name: 'signatures-v2-1440', url: '/admin/signatures' },
  { name: 'audit-v2-1440', url: '/admin/audit' },
];
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 15 });
  await page.type('input[type=password]', 'password', { delay: 15 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1300);
  for (const shot of SHOTS) {
    try {
      await page.goto(BASE + shot.url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(450);
      await page.screenshot({ path: path.join(OUT, shot.name + '.png') });
      console.log('OK ' + shot.name);
    } catch (e) {
      console.log('FAIL ' + shot.name + ' :: ' + e.message.split('\n')[0]);
    }
  }
  await browser.close();
})();
