<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * UI Legacy Detector (UI V3 migration QA).
 * Memindai view admin untuk pola lama & markup rusak: container sempit, form
 * permanen single-before-table, confirm() native, drawer id literal {…},
 * data-drawer-open tak tertutup, mojibake encoding, h1 manual di admin page,
 * nested <form>. Laporan hanya temuan — review manual, TANPA replace otomatis.
 */
class UiAuditLegacy extends Command
{
    protected $signature = 'ui:audit-legacy';

    protected $description = 'Audit view admin untuk pola UI legacy & markup rusak (drawer, mojibake, h1 manual, nested form)';

    private const PATTERNS = [
        'container-lama' => '/mx-auto\s+max-w-(7xl|5xl|4xl)\b/',
        'confirm-native' => '/[^.\w]confirm\s*\(/',
        'drawer-id-literal' => '/id="\{[a-z-]+\}"/',
        'drawer-open-tak-tutup' => '/data-drawer-open="[^"]*</',
        'mojibake' => '/\xc3\x82\xc2\xb7|\xc3\xa2\xe2\x82\xac|[\xc3][\x82][\xc2]|Ã‚Â|â†|âœ\x8d/',
    ];

    public function handle(): int
    {
        $dir = resource_path('views');
        $files = collect(File::allFiles($dir))
            ->filter(fn ($file) => str_contains($file->getPathname(), 'components'.DIRECTORY_SEPARATOR) === false
                && str_contains($file->getPathname(), 'vendor') === false
                && ! in_array(str($file->getFilenameWithoutExtension())->before('.blade')->toString(), ['welcome', 'docs', 'verify'], true));
        $findings = [];
        foreach ($files as $file) {
            $content = $file->getContents();
            $relative = str_replace($dir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $isAdminPage = str_starts_with($relative, 'components') === false;

            foreach (self::PATTERNS as $label => $pattern) {
                if (preg_match($pattern, $content)) {
                    $findings[] = ['file' => $relative, 'pattern' => $label];
                }
            }

            // H1 manual di admin major page (seharusnya x-ui.page-header).
            if ($isAdminPage && preg_match('/<h1 class="[^"]*text-2xl/', $content)) {
                $findings[] = ['file' => $relative, 'pattern' => 'h1-manual'];
            }

            // Nested <form> invalid: <form ...> sebelum </form> sebelumnya ditutup.
            $opens = preg_match_all('/<form\b/', $content, $om);
            $closes = preg_match_all('/<\/form>/', $content, $cm);
            if ($opens !== $closes) {
                $findings[] = ['file' => $relative, 'pattern' => 'form-tak-seimbang'];
            }

            // Form POST permanen tunggal sebelum tabel utama pada halaman index.
            // Page dengan >= 2 form ditangani workspace-tools auto-toolbar (app.js);
            // yang bermasalah adalah SINGLE form permanen.
            $postFormCount = substr_count($content, '<form method="post"');
            if (str_contains($relative, 'index.blade.php')
                && $postFormCount === 1
                && ($formPos = strpos($content, '<form method="post"')) !== false
                && ($tablePos = strpos($content, '<table')) !== false
                && $formPos < $tablePos) {
                $findings[] = ['file' => $relative, 'pattern' => 'form-permanen-index'];
            }
        }

        $lines = $files->count().' view dipindai.'.PHP_EOL;
        if ($findings === []) {
            $lines .= 'TIDAK ADA pola legacy terdeteksi.'.PHP_EOL;
        } else {
            $lines .= count($findings).' temuan (review manual — tidak diubah otomatis):'.PHP_EOL;
            foreach ($findings as $f) {
                $lines .= sprintf('  [%s] %s%s', $f['pattern'], $f['file'], PHP_EOL);
            }
        }
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/ui-legacy-report.txt'), $lines);
        $this->line(trim($lines));
        $this->info('Laporan: storage/app/ui-legacy-report.txt');

        return self::SUCCESS;
    }
}
