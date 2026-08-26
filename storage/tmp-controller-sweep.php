<?php

/**
 * Static sweep: deteksi referensi Kelas:: di controller yang tidak di-import
 * sehingga PHP salah resolve ke namespace App\Http\Controllers.
 */
$dir = 'D:/project laravel/graha/app/Http/Controllers';
$builtIn = [
    'Route', 'DB', 'Auth', 'Hash', 'Storage', 'Config', 'Cache', 'Log', 'Redirect',
    'Response', 'Validator', 'Gate', 'Notification', 'Schema', 'Artisan', 'Http',
    'Str', 'Arr', 'Carbon', 'Date', 'Crypt', 'Cookie', 'Session', 'File', 'Lang',
    'Password', 'Queue', 'Redis', 'URL', 'View', 'Blade', 'Broadcast', 'Bus',
    'Event', 'Exception', 'Throwable', 'DateTimeInterface', 'Request', 'Response',
    'self', 'static', 'parent', 'PHP_EOL', 'Illuminate', 'App', 'PDO', 'DateTime',
    'CarbonImmutable', 'Number', 'RateLimiter', 'Mail', 'Pdf', 'Excel', 'StdClass',
];
$issues = [];
foreach (glob($dir.'/*.php') as $file) {
    $content = file_get_contents($file);
    preg_match('/^namespace (.+);$/m', $content, $nsMatch);
    $namespace = trim($nsMatch[1] ?? 'App\\Http\\Controllers');
    // Kumpulkan alias use.
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $content, $m, PREG_SET_ORDER);
    $imports = [];
    foreach ($m as $u) {
        $short = $u[2] ?? substr($u[1], strrpos('\\'.$u[1], '\\') + 0 ? strrpos($u[1], '\\') + 1 : 0);
        $imports[$short] = $u[1];
    }
    // Cari semua Token:: pemakaian.
    preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::(\w+|\$)/', $content, $uses, PREG_SET_ORDER);
    foreach ($uses as $u) {
        $token = $u[1];
        if (isset($imports[$token])) {
            continue;
        }
        if (in_array($token, $builtIn, true)) {
            continue;
        }
        $fqcn = $namespace.'\\'.$token;
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! enum_exists($fqcn)) {
            $lineNum = substr_count(substr($content, 0, strpos($content, $u[0])), "\n") + 1;
            $issues[] = basename($file).":{$lineNum} -> {$token}:: (dicari sebagai {$fqcn})";
        }
    }
}
echo $issues ? implode("\n", $issues) : "TIDAK ADA MASALAH";
echo PHP_EOL;
