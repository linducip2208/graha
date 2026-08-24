<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * UI Legacy Detector (UI V3 migration QA).
 * Memindai view admin untuk pola lama: container sempit, form permanen besar,
 * confirm() native, warna hardcoded, search ganda. Laporan hanya temuan —
 * review manual, TANPA replace otomatis.
 */
class UiAuditLegacy extends Command
{
    protected $signature = 'ui:audit-legacy {--detail : Tampilkan potongan baris}';

    protected $description = 'Audit view admin untuk pola UI legacy (container lama, form permanen, confirm(), warna hardcoded)';

    private const PATTERNS = [
        'container-lama' => '/mx-auto\s+max-w-(7xl|5xl|4xl)\b/',
        'confirm-native' => '/confirm\s*\(/',
        'bg-white-mentah' => '/class="[^"]*bg-white(?![\w-])/',
        'search-ganda' => '/type="search"[\s\S]{0,400}?type="search"/',
    ];

    public function handle(): int
    {
        $dir = resource_path('views');
        $files = collect(File::allFiles($dir))
            ->filter(fn ($file) => str_contains($file->getPathname(), 'components'.DIRECTORY_SEPARATOR) === false
                && str_contains($file->getPathname(), 'vendor') === false
                && ! in_array($file->getFilenameWithoutExtension(), ['welcome', 'docs', 'verify'], true));
        $findings = [];
        foreach ($files as $file) {
            $content = $file->getContents();
            $relative = str_replace($dir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            foreach (self::PATTERNS as $label => $pattern) {
                if (preg_match($pattern, $content)) {
                    $findings[] = ['file' => $relative, 'pattern' => $label];
                }
            }
            // Form POST permanen sebelum tabel utama pada halaman index (pola lama).
            // Page dengan >= 2 form ditangani workspace-tools auto-toolbar (app.js) —
            // form tetap reachable; yang bermasalah adalah SINGLE form permanen.
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
