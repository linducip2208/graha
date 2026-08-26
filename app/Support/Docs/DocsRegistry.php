<?php

namespace App\Support\Docs;

use App\Models\BoredPile;
use App\Models\Project;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

/**
 * Registry dokumentasi file-based (P5): artikel markdown di
 * resources/docs/articles/<kategori>/<slug>.md dengan front-matter sederhana.
 * Tanpa CMS, tanpa database — cepat, versioned di Git.
 */
class DocsRegistry
{
    public const CATEGORIES = [
        'getting-started' => 'Mulai',
        'dashboard' => 'Dashboard & Navigasi',
        'commercial' => 'Komersial',
        'projects' => 'Proyek',
        'bored-pile' => 'Bored Pile / Foundation',
        'foundation' => 'Panduan Bored Pile',
        'supply-chain' => 'Supply Chain',
        'equipment' => 'Workshop & Equipment',
        'finance' => 'Keuangan',
        'qms' => 'Quality & HSE',
        'hse' => 'HSE',
        'documents' => 'Dokumen & Approval',
        'reports' => 'Laporan',
        'settings' => 'Pengaturan',
        'admin' => 'Admin Guide',
        'faq' => 'FAQ & Troubleshooting',
    ];

    private ?Collection $articles = null;

    public function __construct(private ObjectStorageService $storage) {}

    /** Semua artikel terurut per kategori lalu order lalu judul. */
    public function all(): Collection
    {
        if ($this->articles !== null) {
            return $this->articles;
        }
        $disk = $this->storage->disk(config('docs.disk', 'local'));
        $articles = collect();

        // Finder cross-platform (Windows/Linux) — glob bisa tidak konsisten.
        $finder = Finder::create()
            ->files()->name('*.md')->in(resource_path('docs/articles'))->sortByName();

        foreach ($finder as $file) {
            $category = $file->getRelativePath(); // '' = root (quick-start)
            $slug = $file->getBasename('.md');
            $raw = $file->getContents();
            [$meta, $body] = $this->parseFrontMatter($raw);

            if ($category === '') {
                // Artikel khusus root: quick-start.
                $articles->push([
                    'slug' => $meta['slug'] ?? $slug,
                    'category' => '_special',
                    'category_label' => 'Panduan Cepat',
                    'title' => $meta['title'] ?? 'Quick Start',
                    'description' => $meta['description'] ?? '',
                    'order' => -1,
                    'role_tags' => [], 'permission_tags' => [],
                    'feature_route' => null, 'visibility' => 'public',
                    'keywords' => [], 'related' => [],
                    'updated_at' => date('Y-m-d H:i', $file->getMTime()),
                    'body' => trim($body), 'path' => $file->getPathname(),
                ]);

                continue;
            }

            if (! isset(self::CATEGORIES[$category])) {
                continue;
            }
            $articles->push([
                'slug' => $slug,
                'category' => $category,
                'category_label' => self::CATEGORIES[$category],
                'title' => $meta['title'] ?? str($slug)->replace('-', ' ')->title(),
                'description' => $meta['description'] ?? '',
                'order' => (int) ($meta['order'] ?? 99),
                'role_tags' => $this->csv($meta['role_tags'] ?? ''),
                'permission_tags' => $this->csv($meta['permission_tags'] ?? ''),
                'feature_route' => $meta['feature_route'] ?? null,
                'fixture_project_code' => $meta['fixture_project_code'] ?? null,
                'fixture_pile_number' => $meta['fixture_pile_number'] ?? null,
                'visibility' => in_array($meta['visibility'] ?? 'authenticated', ['public', 'authenticated', 'admin'], true) ? ($meta['visibility'] ?? 'authenticated') : 'authenticated',
                'keywords' => $this->csv($meta['keywords'] ?? ''),
                'related' => $this->csv($meta['related'] ?? ''),
                'updated_at' => date('Y-m-d H:i', $file->getMTime()),
                'body' => trim($body),
                'path' => $file->getPathname(),
            ]);
        }

        return $this->articles = $articles
            ->sortBy([['category', 'asc'], ['order', 'asc'], ['title', 'asc']])
            ->values();
    }

    public function find(string $category, string $slug): ?array
    {
        return $this->all()->first(fn ($a) => $a['category'] === $category && $a['slug'] === $slug);
    }

    public function byCategory(string $category): Collection
    {
        return $this->all()->where('category', $category)->values();
    }

    /** Pencarian sederhana lintas judul/deskripsi/body/keyword (P8). */
    public function search(string $query): Collection
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return collect();
        }

        return $this->all()->filter(function ($a) use ($q) {
            $hay = mb_strtolower($a['title'].' '.$a['description'].' '.implode(' ', $a['keywords']).' '.mb_substr($a['body'], 0, 4000));

            return str_contains($hay, $q);
        })->values();
    }

    /** TOC dari heading ## / ### (P10). */
    public static function toc(string $markdown): array
    {
        $toc = [];
        preg_match_all('/^(#{2,3})\s+(.+)$/m', $markdown, $m, PREG_SET_ORDER);
        foreach ($m as $h) {
            $level = strlen($h[1]) === 2 ? 2 : 3;
            $toc[] = ['level' => $level, 'text' => trim(strip_tags($h[2])), 'anchor' => DocsMarkdown::anchor($h[2])];
        }

        return $toc;
    }

    private function parseFrontMatter(string $raw): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $raw, $m)) {
            $meta = [];
            foreach (explode("\n", $m[1]) as $line) {
                if (preg_match('/^(\w[\w_]*)\s*:\s*(.*)$/', trim($line), $kv)) {
                    $meta[trim($kv[1])] = trim($kv[2]);
                }
            }

            return [$meta, substr($raw, strlen($m[0]))];
        }

        return [[], $raw];
    }

    private function csv(?string $value): array
    {
        return collect(explode(',', (string) $value))->map(fn ($v) => trim($v))->filter()->values()->all();
    }

    /**
     * Resolve feature_route dengan placeholder stabil → URL nyata (P21).
     * Placeholder: {project_code}, {pile_number}. Tidak pernah hardcode DB id.
     */
    public static function resolveFeatureUrl(array $article, array $fixtureOverrides = []): ?string
    {
        $route = $article['feature_route'] ?? null;
        if (! filled($route)) {
            return null;
        }
        $fixture = array_merge(
            ['project_code' => $article['fixture_project_code'] ?? null, 'pile_number' => $article['fixture_pile_number'] ?? null],
            $fixtureOverrides
        );
        if (str_contains($route, '{project_id}')) {
            $code = $fixture['project_code'] ?? null;
            $id = $code ? Project::where('code', $code)->value('id') : null;
            if (! $id) {
                return null;
            }
            $route = str_replace('{project_id}', (string) $id, $route);
        }
        if (str_contains($route, '{pile_id}')) {
            $number = $fixture['pile_number'] ?? null;
            $id = $number ? BoredPile::where('pile_number', $number)->value('id') : null;
            if (! $id) {
                return null;
            }
            $route = str_replace('{pile_id}', (string) $id, $route);
        }

        return str_starts_with($route, '/') ? $route : '/'.$route;
    }
}
