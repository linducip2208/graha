<?php

namespace App\Console\Commands;

use App\Support\Docs\DocsRegistry;
use App\Support\Docs\DocsScreenshotManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * P33 — Audit kesehatan dokumentasi: screenshot hilang/stale, artikel tanpa
 * screenshot, feature route tidak resolve. Output PASS/WARNING/FAIL.
 */
class DocsAuditCommand extends Command
{
    protected $signature = 'docs:audit';

    protected $description = 'Audit kesehatan documentation center (screenshot, link, cakupan)';

    public function handle(DocsRegistry $registry): int
    {
        $disk = Storage::disk(config('docs.disk', 'local'));
        $manifest = DocsScreenshotManifest::all();
        $articles = $registry->all();

        $failures = [];
        $warnings = [];

        // 1. Screenshot rusak: manifest ready tapi file fisik hilang.
        foreach ($manifest as $key => $entry) {
            if (($entry['status'] ?? '') !== 'ready') {
                continue;
            }
            if (! $disk->exists($entry['path'])) {
                $failures[] = "Screenshot '{$key}' manifest ready tetapi file hilang: {$entry['path']}";
            } elseif (isset($entry['commit_sha']) && filled($entry['captured_at']) && now()->subDays(90)->isAfter($entry['captured_at'] ?? now())) {
                $warnings[] = "Screenshot '{$key}' >90 hari — pertimbangkan docs:capture --only={$key}";
            }
        }

        // 2. Artikel tanpa screenshot sama sekali.
        foreach ($articles as $article) {
            preg_match_all('/!\[[^\]]*\]\(([a-z0-9\-]+)\)/i', $article['body'], $m);
            $missing = array_filter($m[1], fn ($key) => ($manifest[$key]['status'] ?? '') !== 'ready');
            if (count($missing) > 0) {
                $warnings[] = "{$article['category']}/{$article['slug']}: ".count($missing).' screenshot belum capture ('.implode(', ', $missing).')';
            }
        }

        // 3. Feature route tidak dapat di-resolve.
        foreach ($articles as $article) {
            if (filled($article['feature_route'])) {
                $url = DocsRegistry::resolveFeatureUrl($article);
                if ($url === null) {
                    $warnings[] = "{$article['category']}/{$article['slug']}: fixture feature route tidak resolve.";
                }
            }
        }

        // 4. Kategori kosong.
        foreach (DocsRegistry::CATEGORIES as $cat => $label) {
            if ($registry->byCategory($cat)->isEmpty()) {
                $warnings[] = "Kategori '{$label}' masih kosong.";
            }
        }

        foreach ($warnings as $w) {
            $this->warn($w);
        }
        foreach ($failures as $f) {
            $this->error($f);
        }
        $this->line("Artikel: {$articles->count()} · Screenshot ready: ".count(array_filter($manifest, fn ($e) => ($e['status'] ?? '') === 'ready')));

        if ($failures !== []) {
            $this->line('FAIL');

            return self::FAILURE;
        }
        if ($warnings !== []) {
            $this->line('PASS dengan WARNING');

            return self::SUCCESS;
        }
        $this->line('PASS');

        return self::SUCCESS;
    }
}
