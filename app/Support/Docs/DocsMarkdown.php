<?php

namespace App\Support\Docs;

use Illuminate\Support\Str;

/**
 * Renderer markdown ringan (P6/P25) — escape-first, mendukung subset yang
 * dibutuhkan dokumentasi: heading, list, code, bold/italic/link/gambar,
 * blockquote, hr, tabel sederhana, dan blok workflow khusus:
 *
 * ```workflow
 * Planned -> Setting Out -> Drilling -> ...
 * ```
 */
class DocsMarkdown
{
    public static function anchor(string $heading): string
    {
        return Str::slug(trim(strip_tags($heading)));
    }

    public static function toHtml(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n"], ["\n"], $markdown));
        $html = [];
        $inCode = false;
        $codeLang = '';
        $codeBuf = [];
        $listType = null;
        $listBuf = [];
        $para = [];
        $table = [];

        $flushPara = function () use (&$para, &$html) {
            if ($para !== []) {
                $html[] = '<p>'.self::inline(implode(' ', $para)).'</p>';
                $para = [];
            }
        };
        $flushList = function () use (&$listType, &$listBuf, &$html) {
            if ($listType !== null && $listBuf !== []) {
                $tag = $listType === 'ul' ? 'ul' : 'ol';
                $html[] = "<$tag class=\"docs-list\">".implode('', array_map(fn ($i) => '<li>'.self::inline($i).'</li>', $listBuf))."</$tag>";
            }
            $listType = null;
            $listBuf = [];
        };
        $flushTable = function () use (&$table, &$html) {
            if (count($table) >= 2) {
                $head = array_map(fn ($c) => '<th>'.self::inline(trim($c)).'</th>', $table[0]);
                $rows = '';
                foreach (array_slice($table, 2) as $row) {
                    $rows .= '<tr>'.implode('', array_map(fn ($c) => '<td>'.self::inline(trim($c)).'</td>', $row)).'</tr>';
                }
                $html[] = '<div class="overflow-x-auto my-4"><table class="w-full text-sm table-sticky rounded-xl border"><thead><tr>'.implode('', $head).'</tr></thead><tbody>'.$rows.'</tbody></table></div>';
            }
            $table = [];
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                if (! $inCode) {
                    $flushPara();
                    $flushList();
                    $flushTable();
                    $inCode = true;
                    $codeLang = trim(substr(trim($line), 3));
                    if ($codeLang === 'workflow') {
                        // Blok workflow khusus (P25).
                        $inCode = false;
                        $steps = array_filter(array_map('trim', explode('->', trim(substr(trim($line), 11)))));
                        $items = '';
                        foreach ($steps as $i => $step) {
                            $items .= '<span class="rounded-lg bg-[var(--surface-muted)] px-3 py-1.5 text-xs font-bold">'.e($step).'</span>';
                            if ($i < count($steps) - 1) {
                                $items .= '<span class="text-[var(--brand-primary)]">→</span>';
                            }
                        }
                        $html[] = '<div class="my-5 flex flex-wrap items-center gap-2 rounded-2xl border p-4">'.$items.'</div>';
                    } else {
                        $codeBuf = [];
                    }
                } else {
                    $inCode = false;
                    if ($codeLang !== 'workflow') {
                        $html[] = '<pre class="my-4 overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100"><code>'.e(implode("\n", $codeBuf)).'</code></pre>';
                    }
                }

                continue;
            }
            if ($inCode) {
                $codeBuf[] = $line;

                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $hm)) {
                $flushPara();
                $flushList();
                $flushTable();
                $level = strlen($hm[1]);
                $text = self::inline($hm[2]);
                $id = self::anchor($hm[2]);
                $sizes = [1 => 'mt-8 text-2xl font-black', 2 => 'mt-8 scroll-mt-28 text-xl font-extrabold docs-h2', 3 => 'mt-6 scroll-mt-28 text-base font-bold docs-h3', 4 => 'mt-4 font-bold'];
                $html[] = "<h{$level} id=\"{$id}\" class=\"".$sizes[$level]."\">{$text}</h{$level}>";

                continue;
            }
            if (trim($line) === '') {
                $flushPara();
                $flushList();
                $flushTable();

                continue;
            }
            if (preg_match('/^---+$/', trim($line))) {
                $flushPara();
                $flushList();
                $flushTable();
                $html[] = '<hr class="my-6 border-[var(--border-subtle)]">';

                continue;
            }
            if (str_starts_with($line, '|') && str_ends_with(rtrim($line), '|')) {
                $flushPara();
                $flushList();
                $cells = array_slice(explode('|', $line), 1, -1);
                if (count($cells) > 0 && preg_match('/^[\s:-]+$/', $cells[0]) === 1 && count($table) === 0) {
                    continue; // separator baris header
                }
                $table[] = $cells;

                continue;
            }
            if (preg_match('/^[-*]\s+/', $line)) {
                $flushPara();
                $flushTable();
                if ($listType !== 'ul') {
                    $flushList();
                    $listType = 'ul';
                }
                $listBuf[] = preg_replace('/^[-*]\s+/', '', $line);

                continue;
            }
            if (preg_match('/^\d+[.)]\s+/', $line)) {
                $flushPara();
                $flushTable();
                if ($listType !== 'ol') {
                    $flushList();
                    $listType = 'ol';
                }
                $listBuf[] = preg_replace('/^\d+[.)]\s+/', '', $line);

                continue;
            }
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', trim($line), $im)) {
                $flushPara();
                $flushList();
                $flushTable();
                $html[] = self::screenshotHtml($im[1], $im[2]);

                continue;
            }
            if (str_starts_with($line, '> ')) {
                $flushPara();
                $flushList();
                $flushTable();
                $html[] = '<blockquote class="my-4 rounded-r-xl border-l-4 border-[var(--brand-primary)] bg-[var(--surface-muted)] p-3 text-sm">'.self::inline(substr($line, 2)).'</blockquote>';

                continue;
            }
            $para[] = trim($line);
        }
        $flushPara();
        $flushList();
        $flushTable();
        if ($inCode && $codeBuf !== []) {
            $html[] = '<pre class="my-4 overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100"><code>'.e(implode("\n", $codeBuf)).'</code></pre>';
        }

        return implode("\n", $html);
    }

    /** Render directive ![alt](key) menjadi figure screenshot dari manifest. */
    private static function screenshotHtml(string $altRaw, string $target): string
    {
        $isKey = ! str_contains($target, '/') && ! str_contains($target, '.');
        if (! $isKey) {
            return '<img src="'.e($target).'" alt="'.e($altRaw).'" class="rounded-xl border my-4">';
        }
        $key = str_starts_with($target, 'docs:') ? substr($target, 5) : $target;
        $manifest = DocsScreenshotManifest::resolve($key);
        if ($manifest === null) {
            return '<div class="my-6 rounded-2xl border border-dashed p-6 text-center text-xs text-slate-400" role="status">'
                .'📷 Screenshot <strong>'.e($key).'</strong> belum tersedia — jalankan <code>php artisan docs:capture --only='.e($key).'</code>.'
                .'</div>';
        }
        $url = route('docs.assets', ['path' => $manifest['path']]);
        $alt = e($altRaw !== '' && ! str_starts_with($altRaw, 'docs:') ? $altRaw : ($manifest['alt'] ?? $key));
        $dims = '';
        foreach (['width', 'height'] as $dim) {
            if (! empty($manifest[$dim])) {
                $dims .= ' '.$dim.'="'.(int) $manifest[$dim].'"';
            }
        }

        return '<figure class="docs-shot my-6">'
            .'<button type="button" class="block w-full cursor-zoom-in rounded-2xl border bg-white p-2 shadow-sm dark:bg-[#0f1a2e]" title="Klik untuk perbesar">'
            .'<img src="'.e($url).'" alt="'.$alt.'" loading="lazy" decoding="async"'.$dims.' class="mx-auto max-w-full rounded-xl">'
            .'</button>'
            .'<figcaption class="mt-2 text-center text-xs text-slate-500">Screenshot: '.e(str_replace('-', ' ', ucwords($key, '-'))).'</figcaption>'
            .'</figure>';
    }

    /** Inline: escape dulu lalu format aman. */
    public static function inline(string $text): string
    {
        $text = e($text);
        $text = preg_replace_callback('/`([^`]+)`/', fn ($m) => '<code class="rounded bg-[var(--surface-muted)] px-1.5 py-0.5 font-mono text-[12px]">'.$m[1].'</code>', $text);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(^|\s)\*([^*\s][^*]*)\*/', '$1<em>$2</em>', $text);
        // Link dokumen internal [[category/slug]] atau eksternal [teks](url).
        $text = preg_replace_callback('/\[\[([\w\-]+\/[\w\-]+)\]\]/', fn ($m) => '<a href="/docs/'.e($m[1]).'" class="font-semibold text-[var(--brand-primary)] hover:underline">'.e(str_replace('-', ' ', substr($m[1], strpos($m[1], '/') + 1))).'</a>', $text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', fn ($m) => '<a href="'.e($m[2]).'" class="font-semibold text-[var(--brand-primary)] hover:underline'.(str_starts_with($m[2], 'http') ? '" target="_blank" rel="noopener' : '').'">'.$m[1].'</a>', $text);

        return $text;
    }
}
