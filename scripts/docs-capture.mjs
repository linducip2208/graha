/**
 * docs-capture.mjs (P13) — Playwright runner untuk screenshot dokumentasi.
 * Input : plan JSON (dibuat DocsCaptureCommand) — berisi viewport + shots.
 * Output: file capture-result-*.json berisi status per shot untuk manifest.
 *
 * Screenshot disimpan ke storage/app/docs/screenshots (LOCAL ONLY, P43).
 */
import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const planPath = process.argv[2];
if (! planPath) {
    console.error('Usage: node scripts/docs-capture.mjs <plan.json>');
    process.exit(1);
}
const plan = JSON.parse(readFileSync(planPath, 'utf8'));
const results = [];

const browser = await chromium.launch();
try {
    for (const shot of plan.shots) {
        const context = await browser.newContext({
            viewport: { width: plan.viewport[0], height: plan.viewport[1] },
            deviceScaleFactor: 1.5,
        });
        try {
            const page = await context.newPage();

            // Login via form demo actor (P12 step 3).
            const loginUrl = new URL('/login', shot.url).toString();
            await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });
            await page.fill('input[type="email"]', shot.actor);
            await page.fill('input[type="password"]', shot.password);
            await Promise.all([
                page.waitForLoadState('domcontentloaded'),
                page.click('button[type="submit"]'),
            ]);

            // Buka target route.
            await page.goto(shot.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForTimeout(shot.wait_ms ?? 900);

            // P30/P29: sembunyikan elemen tidak stabil & teks waktu relatif.
            await page.addStyleTag({ content: `
                .no-shot, [data-shot-hide], .animate-pulse { visibility: hidden !important; }
                #toast-root, #confirm-modal { display: none !important; }
            ` });

            // Simpan sebagai PNG (command mengonversi ke WebP bila GD tersedia).
            const pngOut = shot.output.replace(/\.webp$/, '.png');
            const physical = plan.physical_dir ? `${plan.physical_dir}/${pngOut}` : null;
            if (! physical) throw new Error('physical_dir missing in plan');

            await page.screenshot({ path: physical, fullPage: !! shot.full_page, type: 'png' });
            results.push({ key: shot.key, article: shot.article ?? null, url: shot.url, output: shot.output, status: 'ready', width: plan.viewport[0], height: plan.viewport[1] });
            console.log('PASS', shot.key);
        } catch (error) {
            results.push({ key: shot.key, status: 'failed', error: String(error.message || error).slice(0, 200) });
            console.error('FAIL', shot.key, error.message);
        } finally {
            await context.close().catch(() => {});
        }
    }
} finally {
    await browser.close().catch(() => {});
    writeFileSync(planPath.replace('capture-plan-', 'capture-result-'), JSON.stringify(results, null, 2));
}
