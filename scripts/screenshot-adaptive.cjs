const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 20 });
  await page.type('input[type=password]', 'password', { delay: 20 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1400);

  // Sidebar expanded per workspace (desktop)
  const sidebarShots = [
    { name: 'sidebar-documents-expanded-1440', url: '/admin/documents' },
    { name: 'sidebar-project-expanded-1440', url: '/admin/projects' },
    { name: 'sidebar-finance-expanded-1440', url: '/admin/finance/journals' },
  ];
  for (const shot of sidebarShots) {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(BASE + shot.url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(OUT, shot.name + '.png') });
    console.log('OK ' + shot.name);
  }

  // Documents index v2 (full page)
  await page.goto(BASE + '/admin/documents', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(OUT, 'documents-index-v2-1440.png') });
  console.log('OK documents-index-v2-1440');

  // Documents create drawer
  await page.click('[data-drawer-open="document-create-drawer"]');
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, 'documents-create-v2-1440.png') });
  console.log('OK documents-create-v2-1440');

  // Document record workspace (versions tab)
  await page.keyboard.press('Escape');
  await page.waitForTimeout(300);
  await page.locator('table tbody tr').first().locator('a[href*="/admin/documents/"]').first().click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(OUT, 'documents-record-workspace-1440.png') });
  console.log('OK documents-record-workspace-1440');

  // Mobile: drawer sidebar dengan workspace aktif expanded (iPhone-ish viewport)
  const mobile = await browser.newContext({ viewport: { width: 375, height: 812 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mpage = await mobile.newPage();
  await mpage.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await mpage.type('input[type=email]', 'admin@grahapondasi.test', { delay: 20 });
  await mpage.type('input[type=password]', 'password', { delay: 20 });
  await mpage.locator('button[type=submit]').first().click();
  await mpage.waitForTimeout(1400);
  await mpage.goto(BASE + '/admin/documents', { waitUntil: 'networkidle' });
  await mpage.waitForTimeout(400);
  await mpage.click('[data-sidebar-open]');
  await mpage.waitForTimeout(600);
  await mpage.screenshot({ path: path.join(OUT, 'mobile-sidebar-expanded-375.png') });
  console.log('OK mobile-sidebar-expanded-375');

  await browser.close();
})();
