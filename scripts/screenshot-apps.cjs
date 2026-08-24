const { chromium } = require('playwright');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8899';
const OUT = path.join(__dirname, '..', 'public', 'marketing', 'screens');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.locator('input[type="email"]').first().fill('');
  await page.type('input[type="email"]', 'admin@grahapondasi.test', { delay: 30 });
  await page.locator('input[type="password"]').first().fill('');
  await page.type('input[type="password"]', 'password', { delay: 30 });
  await page.locator('button[type="submit"]').first().click();
  await page.waitForTimeout(1500);

  const shots = [
    { name: 'apps-launcher-1440', width: 1440, height: 900, dark: false },
    { name: 'apps-launcher-768', width: 768, height: 1024, dark: false },
    { name: 'apps-launcher-375', width: 375, height: 812, dark: false },
    { name: 'apps-launcher-dark-1440', width: 1440, height: 900, dark: true },
    { name: 'apps-launcher-dark-375', width: 375, height: 812, dark: true },
    { name: 'dashboard-after-1440', width: 1440, height: 900, dark: false, url: '/dashboard' },
    { name: 'experience-studio-1440', width: 1440, height: 900, dark: false, url: '/admin/experience' },
  ];

  for (const shot of shots) {
    await page.setViewportSize({ width: shot.width, height: shot.height });
    await page.goto(BASE + (shot.url || '/apps'), { waitUntil: 'networkidle' });
    if (shot.dark) {
      await page.evaluate(() => document.documentElement.classList.add('dark'));
      await page.waitForTimeout(300);
    }
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(OUT, shot.name + '.png'), fullPage: false });
    console.log('OK ' + shot.name);
  }

  await browser.close();
})();
