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
    { name: "inventory-forms-1440", url: "/admin/inventory" },
    { name: "procurement-forms-1440", url: "/admin/procurement" },
    { name: "operations-forms-1440", url: "/admin/operations" },
    { name: "rfq-form-1440", url: "/admin/procurement/rfq" },
  ];
  for (const shot of shots) {
    await page.goto(BASE + shot.url, { waitUntil: "networkidle" });
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(OUT, shot.name + ".png") });
    console.log("OK " + shot.name);
  }
  await browser.close();
})();
