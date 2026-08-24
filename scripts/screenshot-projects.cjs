const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 15 });
  await page.type('input[type=password]', 'password', { delay: 15 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1300);

  // Portfolio (default)
  await page.goto(BASE + '/admin/projects', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, 'projects-portfolio-v2-1440.png') });
  console.log('OK portfolio');

  // Kanban
  await page.goto(BASE + '/admin/projects?view=kanban', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(OUT, 'projects-kanban-v2-1440.png') });
  console.log('OK kanban');

  // Timeline
  await page.goto(BASE + '/admin/projects?view=timeline', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, 'projects-timeline-v2-1440.png') });
  console.log('OK timeline');

  // Detail project — ambil id dari tabel portfolio
  await page.goto(BASE + '/admin/projects', { waitUntil: 'networkidle' });
  const href = await page.evaluate(() => document.querySelector('table a[href^="/admin/projects/"]')?.getAttribute('href'));
  if (href) {
    await page.goto(BASE + href, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(OUT, 'project-detail-v2-1440.png') });
    console.log('OK detail ' + href);

    // Bored Pile tab + drawer
    await page.goto(BASE + href + '?tab=piles', { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(OUT, 'project-bored-pile-v2-1440.png') });
    console.log('OK bored pile tab');
    const drawerBtn = page.locator('[data-drawer-open="pile-create-drawer"]');
    if (await drawerBtn.count()) {
      await drawerBtn.click();
      await page.waitForTimeout(500);
      await page.screenshot({ path: path.join(OUT, 'project-pile-create-drawer-1440.png') });
      console.log('OK pile drawer');
    }
  } else {
    console.log('NO PROJECT LINK FOUND');
  }

  // Mobile portfolio
  const mctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const mpage = await mctx.newPage();
  await mpage.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await mpage.type('input[type=email]', 'admin@grahapondasi.test', { delay: 15 });
  await mpage.type('input[type=password]', 'password', { delay: 15 });
  await mpage.locator('button[type=submit]').first().click();
  await mpage.waitForTimeout(1300);
  await mpage.goto(BASE + '/admin/projects', { waitUntil: 'networkidle' });
  await mpage.waitForTimeout(500);
  await mpage.screenshot({ path: path.join(OUT, 'projects-mobile-v2-390.png'), fullPage: true });
  console.log('OK mobile');

  await browser.close();
})();
