<?php

namespace App\Console\Commands;

use App\Support\Docs\DocsRegistry;
use App\Support\Docs\DocsScreenshotManifest;
use Illuminate\Console\Command;

/**
 * P32 — Validasi registry artikel: slug unik, kategori sah, referensi
 * screenshot dikenal, related link valid, front-matter minimum lengkap.
 */
class DocsBuildCommand extends Command
{
    protected $signature = 'docs:build {--strict : Gagal bila ada warning}';

    protected $description = 'Validasi registry dokumentasi & manifest screenshot';

    public function handle(DocsRegistry $registry): int
    {
        $errors = [];
        $warnings = [];
        $articles = $registry->all();

        if ($articles->isEmpty()) {
            $errors[] = 'Tidak ada artikel ditemukan di resources/docs/articles.';
        }

        $seenSlug = [];
        foreach ($articles as $article) {
            $id = $article['category'].'/'.$article['slug'];
            if (isset($seenSlug[$id])) {
                $errors[] = "Duplikat slug: {$id}";
            }
            $seenSlug[$id] = true;
            foreach (['title', 'description'] as $field) {
                if (trim((string) $article[$field]) === '') {
                    $warnings[] = "{$id}: {$field} kosong.";
                }
            }
            // Screenshot directive harus ada di manifest.
            preg_match_all('/!\[[^\]]*\]\(([a-z0-9\-]+)\)/i', $article['body'], $m);
            foreach ($m[1] as $key) {
                if (! isset(config('docs-screenshots.shots')[$key]) && ! config("docs-screenshots.shots.{$key}")) {
                    $errors[] = "{$id}: screenshot key tidak dikenal '{$key}'.";
                }
            }
            foreach ($article['related'] as $rel) {
                [$rc, $rs] = array_pad(explode('/', $rel, 2), 2, '');
                if ($registry->find($rc, $rs) === null && ! ($rc === '_special')) {
                    $warnings[] = "{$id}: related link tidak valid '{$rel}'.";
                }
            }
        }

        // Orphan screenshot: terdaftar manifest tanpa key registry.
        $knownKeys = array_keys((array) config('docs-screenshots.shots'));
        foreach (array_keys(DocsScreenshotManifest::all()) as $manifestKey) {
            if (! in_array($manifestKey, $knownKeys, true)) {
                $warnings[] = "Orphan screenshot di manifest: '{$manifestKey}'.";
            }
        }

        foreach ($warnings as $w) {
            $this->warn($w);
        }
        foreach ($errors as $e) {
            $this->error($e);
        }

        if ($errors === [] && $warnings === []) {
            $this->info('PASS: '.$articles->count().' artikel valid.');

            return self::SUCCESS;
        }

        $failed = $errors !== [] || ($this->option('strict') && $warnings !== []);
        $this->line($failed ? 'FAIL' : 'PASS dengan warning.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
