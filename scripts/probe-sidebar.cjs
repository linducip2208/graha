const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://127.0.0.1:8899/login', { waitUntil: 'networkidle' });
  await page.type('input[type=email]', 'admin@grahapondasi.test', { delay: 20 });
  await page.type('input[type=password]', 'password', { delay: 20 });
  await page.locator('button[type=submit]').first().click();
  await page.waitForTimeout(1200);
  const probe = await page.evaluate(() => {
    const cs = getComputedStyle(document.documentElement);
    const link = document.querySelector('#admin-sidebar .shell-link');
    return {
      textSidebarVar: cs.getPropertyValue('--text-sidebar'),
      surfaceSidebarVar: cs.getPropertyValue('--surface-sidebar'),
      linkColor: link ? getComputedStyle(link).color : 'no-link',
      styleTags: [...document.querySelectorAll('head style')].map(s => s.textContent.slice(0, 60)),
      linkCount: document.querySelectorAll('#admin-sidebar .shell-link').length,
    };
  });
  console.log(JSON.stringify(probe, null, 2));
  await browser.close();
})();
