const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');

const PAGES = [
  { name: 'dashboard', url: '/dashboard' },
  { name: 'apps-launcher', url: '/apps' },
  { name: 'my-work', url: '/admin/my-work' },
  { name: 'contracts-administration', url: '/admin/contracts' },
  { name: 'finance-overview', url: '/admin/finance/overview' },
  { name: 'projects-gantt', url: '/admin/projects' },
  { name: 'project-detail-overview', url: '/admin/projects/1?tab=overview' },
  { name: 'project-detail-planning', url: '/admin/projects/1?tab=planning' },
  { name: 'tenders-kanban', url: '/admin/tenders?view=kanban' },
  { name: 'inventory', url: '/admin/inventory' },
  { name: 'procurement', url: '/admin/procurement' },
  { name: 'manufacturing-control', url: '/admin/manufacturing' },
  { name: 'manufacturing-costing', url: '/admin/manufacturing/costing' },
  { name: 'manufacturing-quality', url: '/admin/manufacturing/quality' },
  { name: 'operations-equipment', url: '/admin/operations' },
  { name: 'finance-coa', url: '/admin/finance' },
  { name: 'finance-mappings', url: '/admin/finance/accounting-mappings' },
  { name: 'billing-retention', url: '/admin/billing' },
  { name: 'cash-bank', url: '/admin/cash-bank' },
  { name: 'taxes', url: '/admin/taxes' },
  { name: 'project-costing', url: '/admin/project-costing' },
  { name: 'fixed-assets', url: '/admin/fixed-assets' },
  { name: 'approvals-center', url: '/admin/approvals' },
  { name: 'digital-signing', url: '/admin/signatures' },
  { name: 'document-control', url: '/admin/documents' },
  { name: 'qms-risk-ncr', url: '/admin/qms' },
  { name: 'hse-jsa-incident', url: '/admin/hse' },
  { name: 'notifications-inbox', url: '/admin/notifications' },
  { name: 'audit-trail', url: '/admin/audit' },
  { name: 'settings-hub', url: '/admin/settings' },
  { name: 'reports-executive', url: '/admin/reports/executive' },
  { name: 'reports-financial-statements', url: '/admin/reports/financial-statements' },
  { name: 'reports-aging', url: '/admin/reports/aging' },
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();

  // Guest pages
  const guest = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  const gp = await guest.newPage();
  await gp.goto(BASE + '/', { waitUntil: 'networkidle' });
  await gp.waitForTimeout(800);
  await gp.screenshot({ path: path.join(OUT, 'landing.png') });
  await gp.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await gp.waitForTimeout(600);
  await gp.screenshot({ path: path.join(OUT, 'login.png') });
  await guest.close();

  // Authenticated pages
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  const page = await ctx.newPage();
  const login = async () => {
    await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await page.locator('input[type="email"]').first().fill('');
    await page.type('input[type="email"]', 'admin@grahapondasi.test', { delay: 30 });
    await page.locator('input[type="password"]').first().fill('');
    await page.type('input[type="password"]', 'password', { delay: 30 });
    await page.waitForTimeout(400);
    await page.locator('form[action="/login"] button').first().click();
    await page.waitForTimeout(3000);
    return page.url().includes('/dashboard');
  };
  let loggedIn = await login();
  if (!loggedIn) {
    const alert = await page.locator('.bg-red-50').first().textContent().catch(() => '');
    console.log('Login attempt 1 gagal: ' + String(alert).trim());
    console.log('Menunggu 62s untuk melewati rate limit...');
    await page.waitForTimeout(62000);
    loggedIn = await login();
  }
  if (!loggedIn) throw new Error('Tidak bisa login setelah retry. URL: ' + page.url());
  await page.waitForTimeout(1500);

  let ok = 0, fail = 0;
  for (const target of PAGES) {
    try {
      await page.goto(BASE + target.url, { waitUntil: 'networkidle', timeout: 45000 });
      await page.waitForTimeout(1200);
      await page.screenshot({ path: path.join(OUT, target.name + '.png') });
      console.log('OK   ' + target.name);
      ok++;
    } catch (err) {
      console.log('FAIL ' + target.name + ' :: ' + err.message.split('\n')[0]);
      try {
        await page.screenshot({ path: path.join(OUT, target.name + '.png') });
        ok++;
      } catch (_) { fail++; }
    }
  }

  await browser.close();
  console.log(`Done: ${ok} captured, ${fail} failed -> ${OUT}`);
})();
