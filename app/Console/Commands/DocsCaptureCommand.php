<?php

namespace App\Console\Commands;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\Project;
use App\Support\Docs\DocsScreenshotManifest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * P12/P31 — Auto screenshot via Playwright (scripts/docs-capture.mjs).
 * Konvensi path:
 *   - shot['output'] relatif terhadap config('docs.screenshots_path').
 *   - Node menulis PNG ke physical_dir (= disk path dari screenshots_path).
 *   - Command mengonversi PNG → WebP bila GD ada, fallback tetap PNG.
 *   - Manifest menyimpan path lengkap setelah root docs.
 */
class DocsCaptureCommand extends Command
{
    protected $signature = 'docs:capture
        {--only=* : Filter key / kategori / artikel}
        {--mobile : Gunakan viewport mobile 390x844}
        {--force : Capture ulang walau manifest masih fresh}
        {--base-url= : Override base URL}';

    protected $description = 'Capture screenshot dokumentasi dari data DEMO ke storage lokal';

    public function handle(): int
    {
        if (! in_array(config('app.env'), (array) config('docs.capture_environments'), true) && ! config('docs.capture_allowed_override')) {
            $this->error('docs:capture hanya di local/demo/testing (atau set DOCS_CAPTURE_ALLOWED=true).');

            return self::FAILURE;
        }

        $demoCode = config('docs.demo_company_code');
        abort_unless(Company::where('code', $demoCode)->exists(), self::FAILURE, "Demo tenant '{$demoCode}' tidak ditemukan. Jalankan db:seed --class=DemoDataSeeder.");
        $demo = Company::where('code', $demoCode)->first();

        $baseUrl = $this->option('base-url') ?: rtrim(env('APP_URL') ?: 'http://127.0.0.1:8000', '/');
        $viewport = $this->option('mobile') ? config('docs.viewport_mobile') : config('docs.viewport_desktop');
        $screenshotsPath = trim(config('docs.screenshots_path'), '/');
        $diskName = (string) config('docs.disk', 'local');

        $plan = [];
        foreach ((array) config('docs-screenshots.shots', []) as $key => $shot) {
            if ($this->option('only') && ! $this->matches($key, $shot)) {
                continue;
            }
            if ($this->option('mobile')) {
                $key .= '-mobile';
                $shot['output'] = preg_replace('/(\.\w+)$/', '-mobile$1', $shot['output']);
            }
            $shot += ['actor' => config('docs-screenshots.defaults.actor'), 'password' => 'password'];

            // Resolve placeholder stabil → id nyata (P11/P30).
            $fixture = [];
            foreach (($shot['fixture'] ?? []) as $placeholder => $value) {
                if ($placeholder === 'project_code') {
                    $id = Project::where('company_id', $demo->id)->where('code', $value)->value('id');
                    if (! $id) {
                        $this->warn("SKIP {$key}: project {$value} tidak ditemukan.");

                        continue 2;
                    }
                    $fixture['{project_id}'] = (string) $id;
                }
                if ($placeholder === 'pile_number') {
                    $id = BoredPile::where('pile_number', $value)->value('id');
                    if (! $id) {
                        $this->warn("SKIP {$key}: pile {$value} tidak ditemukan.");

                        continue 2;
                    }
                    $fixture['{pile_id}'] = (string) $id;
                }
            }
            $resolved = strtr($shot['route'], $fixture);
            if (Str::contains($resolved, ['{', '}'])) {
                $this->warn("SKIP {$key}: placeholder belum ter-resolve di {$resolved}.");

                continue;
            }

            $manifestEntry = DocsScreenshotManifest::all()[$key] ?? null;
            $fresh = $manifestEntry !== null
                && ($manifestEntry['status'] ?? '') === 'ready'
                && filled($manifestEntry['captured_at'])
                && now()->diffInDays(Carbon::parse($manifestEntry['captured_at'])) < 7;
            if ($fresh && ! $this->option('force') && ! $this->option('mobile')) {
                continue;
            }

            $plan[] = [
                'key' => $key,
                'article' => $shot['article'] ?? null,
                'url' => $baseUrl.$resolved,
                'output' => $shot['output'],
                'wait_ms' => $shot['wait_ms'] ?? 900,
                'full_page' => $shot['full_page'] ?? true,
                'actor' => $shot['actor'],
                'password' => $shot['password'],
            ];
        }

        if ($plan === []) {
            $this->info('Tidak ada screenshot yang perlu dicapture.');

            return self::SUCCESS;
        }

        $disk = Storage::disk($diskName);
        $planRel = trim(config('docs.generated_path'), '/').'/capture-plan-'.now()->format('YmdHis').'.json';
        $disk->put($planRel, json_encode([
            'viewport' => $viewport,
            'physical_dir' => $disk->path($screenshotsPath),
            'shots' => $plan,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $script = base_path('scripts/docs-capture.mjs');
        if (! file_exists($script)) {
            $this->error('scripts/docs-capture.mjs tidak ditemukan.');

            return self::FAILURE;
        }

        $this->info('Menjalankan Playwright untuk '.count($plan).' target…');
        passthru('node '.escapeshellarg($script).' '.escapeshellarg($disk->path($planRel)), $exit);

        $resultPath = str_replace('capture-plan-', 'capture-result-', $disk->path($planRel));
        if (! file_exists($resultPath)) {
            $this->error('Hasil capture tidak ditemukan.');

            return self::FAILURE;
        }
        $results = json_decode((string) file_get_contents($resultPath), true) ?: [];

        // Konversi PNG → WebP bila GD mendukung (fallback tetap PNG).
        foreach ($results as &$rowRef) {
            if (($rowRef['status'] ?? '') !== 'ready') {
                continue;
            }
            $pngAbs = $disk->path($screenshotsPath.'/'.$pngRel = preg_replace('/\.webp$/', '.png', $rowRef['output']));
            if (str_ends_with($rowRef['output'], '.webp') && is_file($pngAbs) && function_exists('imagewebp')) {
                if ($img = @imagecreatefrompng($pngAbs)) {
                    imagesavealpha($img, true);
                    imagewebp($img, str_replace('.png', '.webp', $pngAbs), 82);
                    imagedestroy($img);
                    @unlink($pngAbs);
                }
            } elseif (str_ends_with($rowRef['output'], '.webp') && is_file($pngAbs)) {
                $rowRef['output'] = $pngRel; // fallback PNG
            }
        }
        unset($rowRef);

        $pass = 0;
        $failures = [];
        foreach ($results as $row) {
            if (($row['status'] ?? '') === 'ready') {
                DocsScreenshotManifest::set($row['key'], [
                    'article' => $row['article'],
                    'path' => $screenshotsPath.'/'.$row['output'],
                    'viewport' => implode('x', $viewport),
                    'width' => $row['width'] ?? null,
                    'height' => $row['height'] ?? null,
                    'route' => $row['url'],
                    'commit_sha' => substr((string) trim((string) @file_get_contents(base_path('.git/refs/heads/main'))), 0, 8),
                ]);
                $pass++;
                $this->info("PASS {$row['key']}");
            } else {
                $failures[] = $row['key'].' — '.($row['error'] ?? 'unknown');
                $this->error("FAIL {$row['key']} — ".($row['error'] ?? 'unknown'));
            }
        }

        $this->newLine();
        $this->line("Selesai: {$pass} PASS, ".count($failures).' FAIL.');

        return count($failures) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function matches(string $key, array $shot): bool
    {
        foreach ((array) $this->option('only') as $needle) {
            if ($key === $needle || str_contains($key, $needle)
                || ($shot['category'] ?? '') === $needle || ($shot['article'] ?? '') === $needle) {
                return true;
            }
        }

        return false;
    }
}
