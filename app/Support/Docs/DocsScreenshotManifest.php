<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\Storage;

/**
 * Manifest screenshot (P14): single source of truth metadata hasil capture.
 * Disimpan di DOCS_DISK lokal: docs/manifests/screenshots.json.
 */
class DocsScreenshotManifest
{
    public static function path(): string
    {
        return trim(config('docs.manifests_path', 'docs/manifests'), '/').'/screenshots.json';
    }

    public static function all(): array
    {
        $disk = Storage::disk(config('docs.disk', 'local'));

        return $disk->exists(self::path())
            ? (json_decode($disk->get(self::path()), true) ?: [])
            : [];
    }

    public static function save(array $manifest): void
    {
        Storage::disk(config('docs.disk', 'local'))->put(self::path(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function resolve(string $key): ?array
    {
        $entry = self::all()[$key] ?? null;
        if ($entry === null || ($entry['status'] ?? '') !== 'ready') {
            return null;
        }
        $entry['key'] = $key;

        return $entry;
    }

    public static function set(string $key, array $data): void
    {
        $all = self::all();
        $all[$key] = array_merge($all[$key] ?? [], $data, ['status' => $data['status'] ?? 'ready', 'captured_at' => now()->toIso8601String()]);
        self::save($all);
    }
}
