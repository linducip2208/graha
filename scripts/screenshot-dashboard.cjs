const { chromium } = require("playwright");
const path = require("path");
const BASE = "http://127.0.0.1:8899";
const OUT = path.join(__dirname, "..", "public", "marketing", "screens");
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await page.goto(BASE + "/login", { waitUntil: "networkidle" });
  await page.locator("input[type=email]").first().fill("");
  await page.type("input[type=email]", "admin@grahapondasi.test", { delay: 30 });
  await page.locator("input[type=password]").first().fill("");
  await page.type("input[type=password]", "password", { delay: 30 });
  await page.locator("button[type=submit]").first().click();
  await page.waitForTimeout(1500);
  const shots = [
    { name: 'settings-hub-1440', width: 1440, height: 900, dark: false, url: '/admin/settings' },
    { name: "dashboard-premium-1440", width: 1440, height: 900, dark: false, url: "/dashboard" },
    { name: "dashboard-premium-dark-1440", width: 1440, height: 900, dark: true, url: "/dashboard" },
    { name: "dashboard-premium-375", width: 375, height: 812, dark: false, url: "/dashboard" },
    { name: "finance-overview-1440", width: 1440, height: 900, dark: false, url: "/admin/finance/overview" },
  ];
  for (const shot of shots) {
    await page.setViewportSize({ width: shot.width, height: shot.height });
    await page.goto(BASE + shot.url, { waitUntil: "networkidle" });
    if (shot.dark) { await page.evaluate(() => document.documentElement.classList.add("dark")); await page.waitForTimeout(300); }
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(OUT, shot.name + ".png") });
    console.log("OK " + shot.name);
  }
  await browser.close();
})();

