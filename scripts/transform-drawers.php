<?php

/**
 * Generic surgical transformer: pindahkan form POST permanen ke x-ui.drawer.
 * Usage: php transform-drawers.php <spec.json>
 * spec: [{file, action, id, title, description?, button, buttonTone?, anchor?}]
 * - Form dicari berdasarkan action (kemunculan pertama), dipindah utuh ke drawer
 *   di akhir file, tombol opener disisipkan sebelum anchor (default: @if(session('status'))).
 */
$specFile = $argv[1] ?? exit("spec json required\n");
$specs = json_decode(file_get_contents($specFile), true) ?: exit("invalid spec\n");
$base = 'D:/project laravel/grahapondasi/resources/views/';

foreach ($specs as $s) {
    $path = $base.$s['file'];
    $raw = file_get_contents($path);
    if (strpos($raw, 'data-drawer-open="'.$s['id'].'"') !== false) {
        echo "SKIP (sudah) {$s['file']}\n";

        continue;
    }
    $action = str_replace('{{', '{{', $s['action']);
    // cari form berdasarkan action (literal atau prefix sebelum karakter dinamis)
    $needle = 'action="'.$s['action'].'"';
    $pos = strpos($raw, $needle);
    if ($pos === false) {
        echo "ACTION NOT FOUND: {$s['action']} di {$s['file']}\n";

        continue;
    }
    $formStart = strrpos(substr($raw, 0, $pos), '<form');
    if ($formStart === false) {
        echo "FORM START NOT FOUND: {$s['file']}\n";

        continue;
    }
    $formEnd = strpos($raw, '</form>', $pos);
    if ($formEnd === false) {
        echo "FORM END NOT FOUND: {$s['file']}\n";

        continue;
    }
    $formEnd += 7;
    $form = substr($raw, $formStart, $formEnd - $formStart);
    $form = preg_replace('/^<form method="post"([^>]*) class="[^"]*"/', '<form method="post"$1 class="grid gap-4"', $form);

    // hapus dari posisi asli
    $raw = substr($raw, 0, $formStart).substr($raw, $formEnd);

    // tombol opener: sisip sebelum anchor (default session status) atau sebelum drawer lain miliknya
    $anchor = $s['anchor'] ?? "@if(session('status'))";
    $tone = $s['buttonTone'] ?? 'btn-brand';
    $btn = '<button type="button" class="'.$tone.' inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="'.$s['id'].'" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />'.$s['button'].'</button>';
    if (($ai = strpos($raw, $anchor)) !== false) {
        $raw = substr_replace($raw, $btn."\n", $ai, 0);
    } else {
        echo "ANCHOR NOT FOUND di {$s['file']} — tombol ditempel sebelum drawer\n";
    }

    // drawer di akhir file
    $desc = isset($s['description']) ? ' description="'.$s['description'].'"' : '';
    $drawer = "\n<x-ui.drawer id=\"{$s['id']}\" title=\"{$s['title']}\"$desc>\n{$form}\n</x-ui.drawer>\n";
    $tail = '</x-layouts.app>';
    $ti = strrpos($raw, $tail);
    $raw = substr($raw, 0, $ti).$drawer.substr($raw, $ti);

    file_put_contents($path, $raw);
    echo "OK {$s['file']} :: {$s['id']}\n";
}
