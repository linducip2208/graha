<?php

/** One-shot: bersihkan mojibake UTF-8 (em-dash/arrow rusak) di seluruh blade views. */
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../resources/views'));
$fixed = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $c = file_get_contents($path);
    $clean = preg_replace('/\xC3\xA2\xE2\x82\xAC(\xC2\x9D|\xC2\x9C|\xC2\x9E|\x9D|\x9C|\x9E)?/', '—', (string) $c);
    $clean = str_replace("\xC3\x97", '×', (string) $clean);
    $clean = str_replace("\xC3\xA2\xC2\x80\xC2\xA2", '·', (string) $clean);
    if ($clean !== $c) {
        file_put_contents($path, $clean);
        $fixed++;
        echo "FIX {$path}\n";
    }
}
echo "mojibake cleaned: {$fixed} files\n";
