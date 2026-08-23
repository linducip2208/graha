const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens-mobile');

const PAGES = [
  { name: 'dashboard-mobile', url: '/dashboard' },
  { name: 'apps-launcher-mobile', url: '/apps' },
  { name: 'my-work-mobile', url: '/admin/my-work' },
  { name: 'field-ops-mobile', url: '/admin/projects/field-ops?project=1' },
  { name: 'project-detail-mobile', url: '/admin/projects/1?tab=overview' },
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 414, height: 896 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();

  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.locator('input[type="email"]').first().fill('');
  await page.type('input[type="email"]', 'admin@grahapondasi.test', { delay: 30 });
  await page.locator('input[type="password"]').first().fill('');
  await page.type('input[type="password"]', 'password', { delay: 30 });
  await page.waitForTimeout(400);
  await page.locator('form[action="/login"] button').first().click();
  await page.waitForTimeout(3000);

  let ok = 0;
  for (const target of PAGES) {
    try {
      await page.goto(BASE + target.url, { waitUntil: 'networkidle', timeout: 45000 });
      await page.waitForTimeout(1200);
      await page.screenshot({ path: path.join(OUT, target.name + '.png'), fullPage: true });
      console.log('OK   ' + target.name);
      ok++;
    } catch (err) {
      console.log('FAIL ' + target.name + ' :: ' + err.message.split('\n')[0]);
    }
  }
  console.log(`Done: ${ok}/${PAGES.length}`);
  await browser.close();
})();
