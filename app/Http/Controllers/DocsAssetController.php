<?php

namespace App\Http\Controllers;

use App\Services\Storage\ObjectStorageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serving aman aset dokumentasi (P3): hanya file di bawah root docs,
 * whitelist mime, anti path traversal, anti-enumeration.
 */
class DocsAssetController extends Controller
{
    private const ALLOWED_MIME = [
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    public function show(string $path, ObjectStorageService $storage)
    {
        // Anti traversal: tolak segmen berbahaya sebelum normalisasi.
        if (str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/')) {
            abort(404);
        }
        $path = str_replace('//', '/', $path);
        foreach (explode('/', $path) as $segment) {
            if (in_array(strtolower($segment), ['.', '..'], true)) {
                abort(404);
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(self::ALLOWED_MIME[$extension]), 404);

        $diskName = (string) config('docs.disk', 'docs');
        $fullKey = $path; // sudah relatif terhadap root disk 'docs' (storage/app/docs)

        try {
            $disk = $storage->disk($diskName);
        } catch (\Throwable) {
            abort(404);
        }
        abort_if(! $disk->exists($fullKey), 404); // 404 anti-enumeration

        return new BinaryFileResponse($disk->path($fullKey), 200, [
            'Content-Type' => self::ALLOWED_MIME[$extension],
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
